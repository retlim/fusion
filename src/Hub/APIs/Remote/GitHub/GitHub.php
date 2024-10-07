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

namespace Valvoid\Fusion\Hub\APIs\Remote\GitHub;

use Valvoid\Fusion\Hub\APIs\Remote\Offset as RemoteOffsetApi;
use Valvoid\Fusion\Hub\APIs\Remote\Status;
use Valvoid\Fusion\Hub\Responses\Remote\Offset as OffsetResponse;
use Valvoid\Fusion\Hub\Responses\Remote\References;

/**
 * GitHub API.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class GitHub extends RemoteOffsetApi
{
    /** @var array cURL Options. */
    private array $options = [
        CURLOPT_HTTPHEADER => [

            // required
            "user-agent: Fusion"
        ]
    ];

    /**
     * Constructs the request.
     *
     * @param array $config Config.
     */
    public function __construct(array $config)
    {
        parent::__construct($config);

        // local dev
        // disable local SSL
        if ($this->config["protocol"] == "http")
            $this->options += [
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => 0
            ];
    }

    /**
     * Returns references URL.
     *
     * @param string $path Project path.
     * @return string URL.
     */
    public function getReferencesUrl(string $path): string
    {
        return $this->config["url"] .

            // /{owner}/{repo}/tags
            "$path/tags?per_page=100";
    }

    /**
     * Returns cURL options for the tag references endpoint.
     *
     * @return array<int, mixed> Options.
     */
    public function getReferencesOptions(): array
    {
        // recommended
        $options = $this->options;
        $options[CURLOPT_HTTPHEADER][] = "accept: application/vnd.github+json";

        return $options;
    }

    /**
     * Returns normalized response status.
     *
     * @param int $code API response code.
     * @param string[] $headers Server response headers.
     * @return Status Status.
     */
    public function getStatus(int $code, array $headers): Status
    {
        switch($code) {

            // OK, Found (file, archive)
            case 200:
            case 302:
                return Status::OK;

            case 401:
                return Status::UNAUTHORIZED;

            case 404:
                return Status::NOT_FOUND;

            case 403:
                foreach ($headers as $header)
                    if (str_starts_with($header, "x-ratelimit-reset:"))
                        return Status::TO_MANY_REQUESTS;

                return Status::FORBIDDEN;

            // retry-after (sec abuse mechanism) else
            // see 403 for rate limit
            case 429:
                return Status::TO_MANY_REQUESTS;

            default:
                return Status::ERROR;
        }
    }

    /**
     * Returns normalized references response.
     *
     * @param string $path Project path.
     * @param string[] $headers Server response headers.
     * @param array $content Decoded server response content.
     * @return References Response.
     */
    public function getReferences(string $path, array $headers, array $content): References
    {
        $entries = [];
        $url = null;

        foreach ($headers as $header)
            if (str_starts_with($header, "link:")) {
                $headerParts = explode(':', $header, 2);

                // take last part
                // loop pagination links
                foreach (explode(',', $headerParts[1]) as $entry) {
                    preg_match("/<(.*)>; rel=\"next\"/", $entry, $matches);

                    if (isset($matches[1])) {
                        $url = $matches[1];

                        break 2;
                    }
                }
            }

        // normalize
        // tag reference
        foreach ($content as $reference)
            $entries[] = $reference["name"];

        return new References($entries, $url);
    }

    /**
     * Returns offset URL.
     *
     * @param string $path Project path.
     * @param string $offset Offset reference (pseudo version:commit/branch offset).
     * @return string URL.
     */
    public function getOffsetUrl(string $path, string $offset): string
    {
        $offset = urlencode($offset);

        return $this->config["url"] .

            // {owner}/{repo}/commits/{ref}
            "$path/commits/$offset";
    }

    /**
     * Returns cURL options for the offset endpoint.
     *
     * @return array<int, mixed> Options.
     */
    public function getOffsetOptions(): array
    {
        // recommended
        $options = $this->options;
        $options[CURLOPT_HTTPHEADER][] = "accept: application/vnd.github+json";

        return $options;
    }

    /**
     * Returns normalized offset response. A commit sha.
     *
     * @param array $content Decoded server response content.
     * @return OffsetResponse Response.
     */
    public function getOffset(array $content): OffsetResponse
    {
        return new OffsetResponse($content["sha"]);
    }

    /**
     * Returns file URL.
     *
     * @param string $path Project path.
     * @param string $reference Reference.
     * @param string $file Relative to package root file.
     * @return string URL.
     */
    public function getFileUrl(string $path, string $reference, string $file): string
    {
        // remove leading slash
        // fusion's path or dir starts with slash
        $file = substr($file, 1);
        $file = urlencode($file);
        $reference = urlencode($reference);

        return $this->config["url"] .

            // /{owner}/{repo}/contents/{path}
            "$path/contents/$file?ref=$reference";
    }

    /**
     * Returns cURL options for the raw file content endpoint.
     *
     * @return array<int, mixed> Options.
     */
    public function getFileOptions(): array
    {
        // get raw content
        // prevent redundant parsing and
        // base64 decoding
        $options = $this->options;
        $options[CURLOPT_HTTPHEADER][] = "accept: application/vnd.github.raw+json";

        return $options;
    }

    /**
     * Returns archive URL.
     *
     * @param string $path Project path.
     * @param string $reference Reference.
     * @return string URL.
     */
    public function getArchiveUrl(string $path, string $reference): string
    {
        $reference = urlencode($reference);

        return $this->config["url"] .

            // /{owner}/{repo}/zipball/{ref}
            "$path/zipball/$reference";
    }

    /**
     * Returns cURL options for the archive endpoint.
     *
     * @return array<int, mixed> Options.
     */
    public function getArchiveOptions(): array
    {
        // recommended
        // actually raw content - whatever
        $options = $this->options;
        $options[CURLOPT_HTTPHEADER][] = "accept: application/vnd.github+json";

        return $options;
    }

    /**
     * Returns delay unix timestamp.
     *
     * @param array $headers Server response headers.
     * @param string $content Server response content.
     * @return int Timestamp.
     */
    public function getRateLimitReset(array $headers, string $content): int
    {
        foreach ($headers as $header)
            if (str_starts_with($header, "x-ratelimit-reset:")) {
                $headerParts = explode(": ", $header, 2);

                return $headerParts[1];

            } elseif (str_starts_with($header, "retry-after:")) {
                $headerParts = explode(": ", $header, 2);

                return time() + (int) $headerParts[1];
            }

        // 60 sec fallback
        return time() + 60;
    }

    /**
     * Returns error message.
     *
     * @param int $code Server response code.
     * @param array $headers Server response headers.
     * @param string $content Raw server response.
     * @return string Message.
     */
    public function getErrorMessage(int $code, array $headers, string $content): string
    {
        $content = json_decode($content, true);
        $message = "$code | ";

        if ($content === null)
            return "$message Can't parse response. " .
                json_last_error_msg() . " Set log serializer " .
                "threshold to \"debug\" for raw API response.";

        return $message . $content["message"] ??
            "No error message. Set log serializer " .
            "threshold to \"debug\" for raw API response.";
    }
}
