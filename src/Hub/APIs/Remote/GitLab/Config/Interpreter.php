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

namespace Valvoid\Fusion\Hub\APIs\Remote\GitLab\Config;

use Valvoid\Fusion\Bus\Bus;
use Valvoid\Fusion\Bus\Events\Config as ConfigEvent;
use Valvoid\Fusion\Config\Interpreter as ConfigInterpreter;
use Valvoid\Fusion\Hub\APIs\Remote\GitLab\GitLab;
use Valvoid\Fusion\Log\Events\Level;

/**
 * GitLab config interpreter.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Interpreter extends ConfigInterpreter
{
    /**
     * Interprets the GitLab config.
     *
     * @param array $breadcrumb Index path inside the config.
     * @param mixed $entry Config.
     */
    public static function interpret(array $breadcrumb, mixed $entry): void
    {
        // overlay reset value
        if ($entry === null)
            return;

        if (is_string($entry))
            self::interpretDefaultApi($breadcrumb, $entry);

        elseif (is_array($entry))
            foreach ($entry as $key => $value)
                match ($key) {
                    "api" => self::interpretApi($breadcrumb, $value),
                    "protocol" => self::interpretProtocol($breadcrumb, $value),
                    "domain" => self::interpretDomain($breadcrumb, $value),
                    "tokens" => self::interpretTokens($breadcrumb, $value),
                    default => Bus::broadcast(new ConfigEvent(
                        "The unknown \"$key\" index must be " .
                        "\"tokens\", \"api\", \"protocol\" or \"domain\" string.",
                        Level::ERROR,
                        [...$breadcrumb, $key]
                    ))
                };

        else Bus::broadcast(new ConfigEvent(
            "The value must be the default \"" . GitLab::class .
            "\" class name string or a configured array API.",
            Level::ERROR,
            $breadcrumb
        ));
    }

    /**
     * Interprets the default api.
     *
     * @param mixed $entry API entry.
     */
    private static function interpretDefaultApi(array $breadcrumb, mixed $entry): void
    {
        if ($entry !== GitLab::class)
            Bus::broadcast(new ConfigEvent(
                "The value must be the \"" . GitLab::class .
                "\" class name string.",
                Level::ERROR,
                $breadcrumb
            ));
    }

    /**
     * Interprets API.
     *
     * @param mixed $entry API entry.
     */
    private static function interpretApi(array $breadcrumb, mixed $entry): void
    {
        // overlay reset value
        if ($entry === null)
            return;

        if ($entry !== GitLab::class)
            Bus::broadcast(new ConfigEvent(
                "The value, API class name, of the \"api\" " .
                "index must be the \"" . GitLab::class . "\" string.",
                Level::ERROR,
                [...$breadcrumb, "api"]
            ));
    }


    /**
     * Interprets tokens.
     *
     * @param mixed $entry Entry.
     */
    private static function interpretTokens(array $breadcrumb, mixed $entry): void
    {
        // token or null - normalizer reset value
        if ($entry === null || (is_string($entry) && $entry))
            return;

        // token group
        elseif (is_array($entry) && !empty($entry))
            self::validateTokensGroup(["tokens"], $entry);

        else
            Bus::broadcast(new ConfigEvent(
                "The value, token or token group, of " .
                "the \"tokens\" index must be a non-empty array or string.",
                Level::ERROR,
                [...$breadcrumb, "tokens"]
            ));
    }

    /**
     * Interprets tokens group.
     *
     * @param array $breadcrumb Index path inside the config.
     * @param array $entry Group entry.
     */
    private static function validateTokensGroup(array $breadcrumb, array $entry): void
    {
        foreach ($entry as $key => $value) {

            // path
            if (is_string($key))
                if ($key == "")
                    Bus::broadcast(new ConfigEvent(
                        "The \"$key\" index, path, must be a non-empty string.",
                        Level::ERROR,
                        [...$breadcrumb, $key]
                    ));

                // group
                elseif (is_array($value)) {
                    if (empty($value))
                        Bus::broadcast(new ConfigEvent(
                            "The value, group, of the \"$key\" " .
                            "index must be a non-empty array.",
                            Level::ERROR,
                            [...$breadcrumb, $key]
                        ));

                    self::validateTokensGroup([...$breadcrumb, $key], $value);
                    continue;
                }

            // token or reset
            if ((is_string($value) && $value) || $value === null)
                continue;

            else
                Bus::broadcast(new ConfigEvent(
                    "The value, token, of the \"$key\" " .
                    "index must be a non-empty string.",
                    Level::ERROR,
                    [...$breadcrumb, $key]
                ));
        }
    }

    /**
     * Interprets protocol.
     *
     * @param mixed $entry Protocol entry.
     */
    private static function interpretProtocol(array $breadcrumb, mixed $entry): void
    {
        // overlay reset value
        if ($entry === null)
            return;

        if ($entry != "https" && $entry != "http")
            Bus::broadcast(new ConfigEvent(
                "The value of the \"protocol\" index " .
                "must be an \"https\" or \"http\" string.",
                Level::ERROR,
                [...$breadcrumb, "protocol"]
            ));
    }

    /**
     * Interprets domain.
     *
     * @param mixed $entry Domain entry.
     */
    private static function interpretDomain(array $breadcrumb, mixed $entry): void
    {
        // overlay reset value
        if ($entry === null)
            return;

        if (!filter_var($entry, FILTER_VALIDATE_DOMAIN))
            Bus::broadcast(new ConfigEvent(
                "The value of the \"domain\" index " .
                "must be an domain string.",
                Level::ERROR,
                [...$breadcrumb, "domain"]
            ));
    }
}