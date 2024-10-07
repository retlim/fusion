<?php
/**
 * Fusion. A package manager for PHP-based projects.
 * Copyright Valvoid
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

namespace Valvoid\Fusion\Hub\Requests\Remote;

use Valvoid\Fusion\Hub\APIs\Remote\Remote as RemoteApi;
use Valvoid\Fusion\Hub\APIs\Remote\Status;
use Valvoid\Fusion\Hub\Cache;
use Valvoid\Fusion\Hub\Requests\Remote\Remote as RemoteRequest;
use Valvoid\Fusion\Log\Events\Errors\Request;
use Valvoid\Fusion\Log\Log;
use Valvoid\Fusion\Util\Version\Interpreter;

/**
 * Remote references synchronization request.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class References extends RemoteRequest
{
    /** @var int Reference prefix length. */
    private int $prefix;

    /**
     * Constructs the request.
     *
     * @param int $id Request ID.
     * @param Cache $cache Hub cache.
     * @param array $source Structure source.
     * @param RemoteApi $api API.
     */
    public function __construct(int $id, Cache $cache, array $source, RemoteApi $api)
    {
        parent::__construct($id, $cache, $source, $api);

        $this->prefix = strlen($source["prefix"]);
        $this->url = $this->api->getReferencesUrl($source["path"]);

        $this->cache->lockReferences($source, $id);
        $this->setOptions($this->api->getReferencesOptions());
        curl_setopt_array($this->handle, [
            CURLOPT_URL => $this->url,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_RETURNTRANSFER => true
        ]);
    }

    /**
     * Evaluates the response and returns lifecycle action.
     *
     * @param int $result
     * @param string $content
     * @return Lifecycle Action.
     * @throws Request Invalid request exception.
     */
    public function getLifecycle(int $result, string $content): Lifecycle
    {
        // individual execution state
        // group does not cover it
        if ($result != CURLE_OK) {

            // tolerate fragile or whatever connections
            // retry up to 10 times then drop error
            // multi select block should be enough timeout/delay
            if (++$this->attempts < 10) {
                $this->headers["response"] = [];

                return Lifecycle::RELOAD;
            }

            $this->throwError(
                curl_strerror($result),
                $this->url
            );
        }

        $this->attempts = 0;
        $code = curl_getinfo($this->handle, CURLINFO_RESPONSE_CODE);
        $headers = $this->headers["response"];

        Log::debug(new Request(
            array_key_first($this->cacheIds),
            $content,
            [$this->url]
        ));

        switch ($this->api->getStatus($code, $headers)) {
            case Status::OK:
                $content = json_decode($content, true);

                if ($content === null)
                    $this->throwError(
                        json_last_error_msg(),
                        $this->url
                    );

                $response = $this->api->getReferences($this->source["path"],
                    $headers, $content);

                $next = $response->getUrl();
                $this->headers["response"] = [];

                // validate
                // only inflatable semantic versions
                foreach ($response->getEntries() as $entry) {
                    $entry = substr($entry, $this->prefix);

                    if (Interpreter::isSemanticVersion($entry))
                        if (!$this->cache->addVersion($this->source["api"],
                            $this->source["path"], $entry))
                            $this->throwError(
                                "The offset ($entry) conflicts " .
                                "with an existing version. Remove it from the " .
                                "source or create an other one. Offset must be a " .
                                "non-existing pseudo version.",
                                $this->url
                            );
                }

                if ($next) {
                    curl_setopt($this->handle, CURLOPT_URL, $next);

                    // keep cache lock and
                    // get next chunk
                    return Lifecycle::RELOAD;
                }

                // clear callback
                // enable destruct
                curl_reset($this->handle);
                $this->cache->unlockReferences($this->source);

                return Lifecycle::DONE;

            // invalid token
            case Status::UNAUTHORIZED:
                $this->exchangeInvalidToken(
                    $this->api->getErrorMessage(
                        $code,
                        $headers,
                        $content
                    ));

                return Lifecycle::RELOAD;

            // rate limit
            case Status::TO_MANY_REQUESTS:
                $this->api->setDelay(
                    $this->api->getRateLimitReset($headers, $content),
                    $this->id
                );

                $this->headers["response"] = [];

                return Lifecycle::DELAY;

            // token scope for other resource or
            // resource does not exist
            // drop error if no tokens left
            case Status::FORBIDDEN:
            case Status::NOT_FOUND:
                $this->exchangeToken(
                    $this->api->getErrorMessage(
                        $code,
                        $headers,
                        $content
                    ));

                return Lifecycle::RELOAD;

            // case Status::ERROR:
            // string - error message
            // fatal API response
            default:
                $message = $this->api->getErrorMessage($code, $headers, $content);

                $this->throwError(
                    $message,
                    $this->url
                );
        }
    }
}