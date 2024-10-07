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

use Valvoid\Fusion\Dir\Dir;
use Valvoid\Fusion\Hub\APIs\Remote\Remote as RemoteApi;
use Valvoid\Fusion\Hub\APIs\Remote\Status;
use Valvoid\Fusion\Hub\Cache;
use Valvoid\Fusion\Log\Events\Errors\Error;
use Valvoid\Fusion\Log\Events\Errors\Request;
use Valvoid\Fusion\Log\Log;

/**
 * Remote archive synchronization request.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Archive extends Remote
{
    /** @var string Absolute cache archive file directory. */
    private string $dir;

    /** @var resource Archive file stream. */
    private $stream;

    /**
     * Constructs the request.
     *
     * @param int $id Request ID.
     * @param Cache $cache Hub cache.
     * @param array $source Structure source.
     * @param RemoteApi $api API.
     * @throws Error Internal error.
     */
    public function __construct(int $id, Cache $cache, array $source, RemoteApi $api)
    {
        parent::__construct($id, $cache, $source, $api);

        $reference = $this->source["reference"];

        // add prefix
        if (!$this->cache->isOffset($this->source))
            $reference = $this->source["prefix"] . $reference;

        // use temp name
        // enable cache to clear broken files
        $this->dir = $cache->getRemoteDir($source);
        $this->stream = fopen("$this->dir/archive", "w+");
        $this->url = $api->getArchiveUrl(
            $source["path"],
            $reference
        );

        if ($this->stream === false)
            throw new Error(
                "Can't create temp archive file " .
                "\"$this->dir/archive\" stream."
            );

        $cache->lockFile($source, "/archive.zip", $id);
        $this->setOptions($api->getArchiveOptions());
        curl_setopt_array($this->handle, [
            CURLOPT_URL => $this->url,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 10,

            // keep order
            // works only if set before CURLOPT_FILE
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FILE => $this->stream
        ]);
    }

    /**
     * Evaluates the request.
     *
     * @param int $result
     * @param string $content No content.
     * @return Lifecycle Lifecycle.
     * @throws Request Request error.
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
                $this->rewindStream();
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

        switch ($this->api->getStatus($code, $headers)) {
            case Status::OK:
                if (!fclose($this->stream))
                    throw new Error(
                        "Can't close the stream \"$this->dir/archive\"."
                    );

                // clear header callback
                // enable destruct
                curl_reset($this->handle);
                Dir::rename("$this->dir/archive", "$this->dir/archive.zip");
                $this->cache->unlockFile($this->source, "/archive.zip");

                return Lifecycle::DONE;

            // invalid token
            case Status::UNAUTHORIZED:
                $this->exchangeInvalidToken(
                    $this->api->getErrorMessage(
                        $code,
                        $headers,
                        $this->getContent()
                    ));

                return Lifecycle::RELOAD;

            // timestamp
            case Status::TO_MANY_REQUESTS:
                $content = $this->getContent();

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
                        $this->getContent()
                    ));

                return Lifecycle::RELOAD;

            // case Status::ERROR:
            // string - error message
            // fatal API response
            default:
                $content = $this->getContent();
                $message = $this->api->getErrorMessage($code, $headers, $content);

                $this->throwError(
                    $message,
                    $this->url
                );
        }
    }

    /**
     * Returns content.
     *
     * @return string Content.
     * @throws Error Internal error.
     */
    private function getContent(): string
    {
        // pointer and read
        $this->rewindStream();
        $content = stream_get_contents($this->stream);

        if ($content === false)
            throw new Error(
                "Can't get contents from the stream " .
                "\"$this->dir/archive\"."
            );

        // pointer for override
        $this->rewindStream();
        Log::debug(new Request(
            array_key_first($this->cacheIds),
            $content,
            [$this->url]
        ));

        return $content;
    }

    /**
     * Resets stream.
     *
     * @throws Error Internal error.
     */
    private function rewindStream(): void
    {
        if (rewind($this->stream) === false)
            throw new Error(
                "Can't reset the stream " .
                "\"$this->dir/archive.zip\"."
            );
    }
}