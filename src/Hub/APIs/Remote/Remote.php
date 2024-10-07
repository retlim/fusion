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

namespace Valvoid\Fusion\Hub\APIs\Remote;

use Valvoid\Fusion\Hub\Responses\Remote\References;

/**
 * Remote API.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
abstract class Remote
{
    /** @var array Config. */
    protected array $config;

    /** @var string[] Invalid bearer tokens. */
    protected array $tokens = [];

    /** @var array{
     *      requests: int[],
     *      timestamp: int
     * } Rate limit.
     * */
    protected array $delay = [
        "timestamp" => null,
        "requests" => [] // sync IDs
    ];

    /**
     * Constructs the remote API.
     *
     * @param array $config Config.
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Returns authorization header prefix. The part before the access
     * token with trailing whitespace.
     *
     * @return string Header prefix.
     */
    public function getAuthHeaderPrefix(): string
    {
        return "Authorization: Bearer ";
    }

    /**
     * Returns references URL.
     *
     * @param string $path Project path.
     * @return string URL.
     */
    abstract public function getReferencesUrl(string $path): string;

    /**
     * Returns cURL options for the references endpoint. Endpoint that
     * provides potential package versions.
     *
     * @return array<int, mixed> Options.
     */
    abstract public function getReferencesOptions(): array;

    /**
     * Returns normalized response status.
     *
     * @param int $code API response code.
     * @param string[] $headers Server response headers.
     * @return Status Status.
     */
    abstract public function getStatus(int $code, array $headers): Status;

    /**
     * Returns normalized references response.
     *
     * @param string $path Project path.
     * @param string[] $headers Server response headers.
     * @param array $content Decoded server response content.
     * @return References Response.
     */
    abstract public function getReferences(string $path, array $headers, array $content): References;

    /**
     * Returns file URL.
     *
     * @param string $path Project path.
     * @param string $reference Reference.
     * @param string $file Relative to package root file.
     * @return string URL.
     */
    abstract public function getFileUrl(string $path, string $reference, string $file): string;

    /**
     * Returns cURL options for the raw file content endpoint.
     *
     * @return array<int, mixed> Options.
     */
    abstract public function getFileOptions(): array;

    /**
     * Returns archive URL.
     *
     * @param string $path Project path.
     * @param string $reference Reference.
     * @return string URL.
     */
    abstract public function getArchiveUrl(string $path, string $reference): string;

    /**
     * Returns cURL options for the archive endpoint.
     *
     * @return array<int, mixed> Options.
     */
    abstract public function getArchiveOptions(): array;

    /**
     * Returns rate limit reset. A unix timestamp.
     *
     * @param array $headers Server response headers.
     * @param string $content Server response content.
     * @return int Timestamp.
     */
    abstract public function getRateLimitReset(array $headers, string $content): int;

    /**
     * Returns error message.
     *
     * @param int $code Server response code.
     * @param array $headers Server response headers.
     * @param string $content Raw server response.
     * @return string Message.
     */
    abstract public function getErrorMessage(int $code, array $headers, string $content): string;

    /**
     * Returns bearer auth tokens.
     *
     * @param string $path Project.
     * @return string[] Tokens.
     */
    public function getTokens(string $path): array
    {
        $tokens = [];

        if ($this->config["tokens"]) {

            // leading slash
            $path = substr($path, 1);
            $path = explode('/', $path);

            $this->assignTokens($path, $this->config["tokens"], $tokens);

            $tokens = array_unique($tokens);
        }

        return $tokens;
    }

    /**
     * Returns filtered bearer auth tokens.
     *
     * @param array $tokens All tokens.
     * @param array $path Path.
     * @param array $selection Subset.
     */
    private function assignTokens(array $path, array $tokens, array &$selection): void
    {
        $pathPrefix = array_shift($path);

        // assign prefixed nested tokens
        // sub tier
        if (isset($tokens[$pathPrefix]))
            if (is_array($tokens[$pathPrefix]))
                $this->assignTokens($path, $tokens[$pathPrefix], $selection);

            // is not invalid
            elseif (!in_array($tokens[$pathPrefix], $this->tokens))
                $selection[] = $tokens[$pathPrefix];

        // assign seq tokens
        // current higher tier
        foreach ($tokens as $key => $value)
            if (is_numeric($key) && is_string($value) &&

                // is not invalid
                !in_array($value, $this->tokens))
                $selection[] = $value;
    }

    /**
     * Adds invalid token.
     *
     * @param string $token Token.
     */
    public function addInvalidToken(string $token): bool
    {
        // already
        if (in_array($token, $this->tokens))
            return false;

        $this->tokens[] = $token;

        return true;
    }

    /**
     * Sets delay.
     *
     * @param int $timestamp Timestamp.
     * @param int $id Sync request ID.
     */
    public function setDelay(int $timestamp, int $id): void
    {
        if ($this->delay["timestamp"] == null ||
            $this->delay["timestamp"] < $timestamp)
            $this->delay["timestamp"] = $timestamp;

        $this->addDelayRequest($id);
    }

    /**
     * Returns delay indicator.
     *
     * @return bool Indicator.
     */
    public function hasDelay(): bool
    {
        return $this->delay["timestamp"] != null;
    }

    /**
     * Adds sync request ID to delay.
     *
     * @param int $id ID.
     */
    public function addDelayRequest(int $id): void
    {
        $this->delay["requests"][] = $id;
    }

    /**
     * Returns delay.
     *
     * @return array{
     *       requests: int[],
     *       timestamp: int
     *  } Rate limit.
     */
    public function getDelay(): array
    {
        return $this->delay;
    }

    /**
     * Resets delay.
     */
    public function resetDelay(): void
    {
        $this->delay = [
            "timestamp" => null,
            "requests" => []
        ];
    }
}