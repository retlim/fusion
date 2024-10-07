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

namespace Valvoid\Fusion\Hub\APIs\Remote\Valvoid;

use Valvoid\Fusion\Hub\APIs\Remote\Remote;
use Valvoid\Fusion\Hub\APIs\Remote\Status;
use Valvoid\Fusion\Hub\Responses\Remote\References;

/**
 * Valvoid API.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Valvoid extends Remote
{
    /** @var array cURL Options. */
    private array $options = [
        CURLOPT_HTTPHEADER => [

            // default
            "accept: application/json"
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
        // remove leading slash
        // fusion's path or dir starts with slash
        $path = substr($path, 1);
        $path = urlencode($path);

        return $this->config["url"] .

            // /[identifier]/versions
            "/$path/versions?per_page=100";
    }

    /**
     * Returns cURL options for the versions endpoint.
     *
     * @return array<int, mixed> Options.
     */
    public function getReferencesOptions(): array
    {
        return $this->options;
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
     * Returns normalized references response.
     *
     * @param string $path Project path.
     * @param string[] $headers Server response headers.
     * @param array $content Decoded server response content.
     * @return References Response.
     */
    public function getReferences(string $path, array $headers, array $content): References
    {
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

        // normalized version references
        return new References($content, $url);
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
        // registry has extracted snap file
        // cut prefix path
        $file = basename($file);

        // remove leading slash
        // fusion's path or dir starts with slash
        $path = substr($path, 1);
        $path = urlencode($path);
        $reference = urlencode($reference);

        return $this->config["url"] .

            // /[identifier]/[version]/fusion.json
            // /[identifier]/[version]/snapshot.json
            "/$path/$reference/$file";
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
        // remove leading slash
        // fusion's path or dir starts with slash
        $path = substr($path, 1);
        $path = urlencode($path);
        $reference = urlencode($reference);

        return $this->config["url"] .

            // [identifier]/[version]/archive.zip
            "/$path/$reference/archive.zip";
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
            if (str_starts_with($header, "x-rate-limit-reset:")) {
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
                json_last_error_msg() . " Set log serializer " .
                "threshold to \"debug\" for raw API response.";

        return $message . $content["message"] ??
            "No error message. Set log serializer " .
            "threshold to \"debug\" for raw API response.";
    }
}
