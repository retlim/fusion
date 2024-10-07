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

namespace Valvoid\Fusion\Hub\APIs\Remote\Bitbucket;

use Valvoid\Fusion\Hub\APIs\Remote\Offset as RemoteOffsetApi;
use Valvoid\Fusion\Hub\APIs\Remote\Status;
use Valvoid\Fusion\Hub\Responses\Remote\Offset as OffsetResponse;
use Valvoid\Fusion\Hub\Responses\Remote\References;

/**
 * Bitbucket API.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Bitbucket extends RemoteOffsetApi
{
    /** @var array cURL Options. */
    private array $options = [
        CURLOPT_HTTPHEADER => [
            "Accept: application/json"
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
     * Returns normalized response status.
     *
     * @param int $code API response code.
     * @param string[] $headers Server response headers.
     * @return Status Status.
     */
    public function getStatus(int $code, array $headers): Status
    {
        return match ($code) {
            200 => Status::OK,
            401 => Status::UNAUTHORIZED,
            403 => Status::FORBIDDEN,
            404 => Status::NOT_FOUND,
            429 => Status::TO_MANY_REQUESTS,
            default => Status::ERROR
        };
    }

    /**
     * Returns references URL.
     *
     * @param string $path Project path.
     * @return string URL.
     */
    public function getReferencesUrl(string $path): string
    {
        // cloud
        if ($this->config["version"] == 2.0)
            return $this->config["url"] .

                // /{workspace}/{repo_slug}/refs/tags
                "$path/refs/tags?pagelen=100";

        // data center API 1.0
        $path = substr($path, 1);
        $path = str_replace('/', "/repos", $path);

        return $this->config["url"] .

            // /{projectKey}/repos/{repositorySlug}/tags
            "/$path/tags?limit=100";
    }

    /**
     * Returns cURL options for the tag references endpoint.
     *
     * @return array<int, mixed> Options.
     */
    public function getReferencesOptions(): array
    {
        return $this->options;
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
        $next = null;

        // cloud
        if ($this->config["version"] == 2.0) {
            foreach ($content["values"] as $value)
                $entries[] = $value["name"];

            if (isset($content["next"]))
                $next = $content["next"];

        // data center API 1.0
        } else {
            foreach ($content["values"] as $value)
                $entries[] = $value["displayId"];

            if (!$content["isLastPage"]) {
                $next = $this->getReferencesUrl($path);
                $next .= "&start=" . $content["start"];
            }
        }

        return new References($entries, $next);
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

        // cloud
        if ($this->config["version"] == 2.0)
            return $this->config["url"] .

                // /{workspace}/{repo_slug}/commit/{commit}
                "$path/commit/$offset";

        // data center API 1.0
        $path = substr($path, 1);
        $path = str_replace('/', "/repos", $path);

        return $this->config["url"] .

            // /{projectKey}/repos/{repositorySlug}/commits/{commitId}
            "/$path/commits/$offset";
    }

    /**
     * Returns cURL options for the offset endpoint.
     *
     * @return array<int, mixed> Options.
     */
    public function getOffsetOptions(): array
    {
        return $this->options;
    }

    /**
     * Returns normalized offset response. A commit hash.
     *
     * @param array $content Decoded server response content.
     * @return OffsetResponse Response.
     */
    public function getOffset(array $content): OffsetResponse
    {
        // cloud or server
        return new OffsetResponse(
            ($this->config["version"] == 2.0) ?
                $content["hash"] :
                $content["id"]
        );
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

        // cloud
        if ($this->config["version"] == 2.0)
            return $this->config["url"] .

                // /{workspace}/{repo_slug}/src/{commit}/{path}
                "$path/src/$reference/$file";

        // data center API 1.0
        $path = substr($path, 1);
        $path = str_replace('/', "/repos", $path);

        return $this->config["url"] .

            // /{projectKey}/repos/{repositorySlug}/raw/{path}
            "/$path/raw$file?at=$reference";
    }

    /**
     * Returns cURL options for the raw file content endpoint.
     *
     * @return array<int, mixed> Options.
     */
    public function getFileOptions(): array
    {
        return $this->options;
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

        // cloud
        // no API endpoint
        if ($this->config["version"] == 2.0)
            return "https://bitbucket.org$path/get/$reference.zip";

        // data center API 1.0
        $path = substr($path, 1);
        $path = str_replace('/', "/repos", $path);

        return $this->config["url"] .

            // /{projectKey}/repos/{repositorySlug}/archive
            "/$path/archive?at=$reference&format=zip";
    }

    /**
     * Returns cURL options for the archive endpoint.
     *
     * @return array<int, mixed> Options.
     */
    public function getArchiveOptions(): array
    {
        return $this->options;
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
            if (str_starts_with($header, "x-ratelimit-reset:") ||
                str_starts_with($header, "X-RateLimit-Reset:")) {
                $headerParts = explode(": ", $header, 2);

                return $headerParts[1];
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
                json_last_error_msg() . "Set log serializer " .
                "threshold to \"debug\" for raw API response.";

        // cloud
        if ($this->config["version"] == 2.0)
            return $message . $content["error"]["message"] ??
                "No error message. Set log serializer " .
                "threshold to \"debug\" for raw API response.";

        // data center API 1.0
        return $message . $content["errors"][0]["message"] ??
            "No error message. Set log serializer " .
            "threshold to \"debug\" for raw API response.";
    }
}