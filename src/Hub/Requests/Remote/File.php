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
use Valvoid\Fusion\Log\Events\Errors\Error;
use Valvoid\Fusion\Log\Events\Errors\Request;
use Valvoid\Fusion\Log\Log;

/**
 * Remote file synchronization request.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class File extends Remote
{
    /** @var string Cache file. */
    private string $file;

    /** @var string Filename. */
    private string $filename;

    /**
     * Constructs the request.
     *
     * @param int $id Request ID.
     * @param Cache $cache Hub cache.
     * @param array $source Structure source.
     * @param string $filename Filename.
     * @param RemoteApi $api API.
     * @throws Error Internal error.
     */
    public function __construct(int $id, Cache $cache, array $source, string $path,
                                string $filename, RemoteApi $api)
    {
        parent::__construct($id, $cache, $source, $api);

        $pointer = $this->source["reference"];

        // add prefix
        if (!$this->cache->isOffset($this->source))
            $pointer = $this->source["prefix"] . $pointer;

        // non-nested [api]/[path]/[pointer]/fusion.json and
        // [api]/[path]/[pointer]/snapshot.json
        // cache structure like registry
        $this->file = $cache->getRemoteDir($source) . $filename;
        $this->filename = $filename;
        $this->url = $api->getFileUrl(
            $source["path"],
            $pointer,

            // fusion.json or nested snapshot.json
            $path . $filename
        );

        $cache->lockFile($source, $filename, $id);
        $this->setOptions($api->getFileOptions());
        curl_setopt_array($this->handle, [
            CURLOPT_URL => $this->url,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_RETURNTRANSFER => true
        ]);
    }

    /**
     * Evaluates the request.
     *
     * @param int $result
     * @param string $content
     * @return Lifecycle
     * @throws Request
     * @throws Error Internal error.
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
                if (file_put_contents($this->file, $content) === false)
                    throw new Error(
                        "Can't write file \"$this->file\"."
                    );

                // clear callback
                // enable destruct
                curl_reset($this->handle);
                $this->cache->unlockFile($this->source, $this->filename);

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

            // timestamp
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
                $this->throwError(
                    $this->api->getErrorMessage($code, $headers, $content),
                    $this->url
                );
        }
    }
}