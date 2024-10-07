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

use CurlHandle;
use Valvoid\Fusion\Hub\APIs\Remote\Remote as RemoteApi;
use Valvoid\Fusion\Hub\Cache;
use Valvoid\Fusion\Hub\Requests\Request;
use Valvoid\Fusion\Log\Events\Errors\Request as RequestError;
use Valvoid\Fusion\Log\Log;

/**
 * Remote synchronizations request.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
abstract class Remote extends Request
{
    /** @var RemoteApi API */
    protected RemoteApi $api;

    /** @var int Connection attempts. */
    protected int $attempts = 0;

    /** @var CurlHandle Curl handle. */
    protected CurlHandle $handle;

    /** @var int[] Cache request ID. */
    protected array $cacheIds;

    /** @var array{
     *     request: string[],
     *     response: string[]
     * } HTTP headers.
     */
    protected array $headers = [
        "request" => [],
        "response" => []
    ];

    /** @var string Current URL */
    protected string $url;

    /** @var string[] Bearer tokens. */
    protected array $tokens;

    /** @var string  Auth header prefix. */
    protected string $auth;

    /**
     * Constructs the remote synchronization request.
     *
     * @param Cache $cache Hub cache.
     * @param int $id Request ID.
     * @param array $source Structure source.
     */
    public function __construct(int $id, Cache $cache, array $source, RemoteApi $api)
    {
        parent::__construct($id, $cache, $source);

        $this->api = $api;
        $this->tokens = $api->getTokens($source["path"]);
        $this->auth = $api->getAuthHeaderPrefix();
        $this->handle = curl_init();

        curl_setopt_array($this->handle, [
            CURLOPT_PRIVATE => $id,
            CURLOPT_HEADERFUNCTION => function (CurlHandle $handle, string $header)
            {
                $this->headers["response"][] = $header;

                return strlen($header);
            }
        ]);
    }

    /**
     * Returns URL.
     *
     * @return string URL.
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * Sets individual cURL options.
     *
     * @param array $options Options.
     */
    protected function setOptions(array $options): void
    {
        foreach ($options as $option => $value)
            if ($option != CURLOPT_HTTPHEADER)
                curl_setopt($this->handle, $option, $value);

            else foreach ($value as $header)
                if (!str_starts_with($this->auth, $header))
                    $this->headers["request"][] = $header;

        $headers = $this->headers["request"];
        $token = reset($this->tokens);

        if ($token)
            $headers[] = $this->auth . $token;

        curl_setopt($this->handle, CURLOPT_HTTPHEADER,

            // optional
            $headers);
    }

    /**
     * Exchanges invalid token.
     *
     * @param string $message Error message.
     * @throws RequestError Invalid request exception.
     */
    protected function exchangeInvalidToken(string $message): void
    {
        // redundancy
        // remove invalid from stack
        $token = array_shift($this->tokens);
        $preview = substr($token, 0, 15);
        $this->headers["response"] = [];

        // has more
        // exchange and notify
        if ($this->tokens) {
            if ($this->api->addInvalidToken($token))
                Log::notice(new RequestError(
                    array_key_first($this->cacheIds),
                    "Invalid \"$preview...\" token. $message Trying another one.",
                    [$this->url]
                ));

            $headers = $this->headers["request"];
            $headers[] = $this->auth . reset($this->tokens);

            curl_setopt($this->handle, CURLOPT_HTTPHEADER, $headers);

        } else
            $this->throwError(
                "Invalid \"$preview...\" token. $message",
                $this->url
            );
    }

    /**
     * Exchanges auth token.
     *
     * @param string $message Error message.
     * @throws RequestError Invalid request exception.
     */
    protected function exchangeToken(string $message): void
    {
        // redundancy
        // remove invalid from stack
        $token = array_shift($this->tokens);
        $preview = substr($token, 0, 15);
        $this->headers["response"] = [];

        // has more
        // exchange and notify
        if ($this->tokens) {
            Log::notice(new RequestError(
                array_key_first($this->cacheIds),
                "Bad \"$preview...\" token. $message Trying another one.",
                [$this->url]
            ));

            $headers = $this->headers["request"];
            $headers[] = $this->auth . reset($this->tokens);

            curl_setopt($this->handle, CURLOPT_HTTPHEADER, $headers);

        } else
            $this->throwError(
                "Bad \"$preview...\" token. $message",
                $this->url
            );
    }

    /**
     * Adds cache request ID that wait for sync done.
     *
     * @param int $id ID.
     */
    public function addCacheId(int $id): void
    {
        $this->cacheIds[$id] = time();
    }

    /**
     * Returns cache IDs.
     *
     * @return int[] IDs.
     */
    public function getCacheIds(): array
    {
        return array_keys($this->cacheIds);
    }

    /**
     * Returns lifecycle.
     *
     * @param int $result Result.
     * @return Lifecycle Lifecycle.
     */
    abstract public function getLifecycle(int $result, string $content): Lifecycle;

    /**
     * Returns handle.
     *
     * @return CurlHandle Handle.
     */
    public function getHandle(): CurlHandle
    {
        return $this->handle;
    }

    /**
     * Destroys the request.
     */
    public function __destruct()
    {
        curl_close($this->handle);
    }

    /**
     * Throws request error.
     *
     * @param string $message Error message.
     * @param string $source URL.
     * @throws RequestError Invalid request exception.
     */
    protected function throwError(string $message, string $source): void
    {
        throw new RequestError(
            array_key_first($this->cacheIds),
            $message,
            [$source]
        );
    }
}