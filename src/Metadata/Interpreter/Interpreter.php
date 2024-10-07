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

namespace Valvoid\Fusion\Metadata\Interpreter;

use Valvoid\Fusion\Bus\Bus;
use Valvoid\Fusion\Bus\Events\Metadata as MetadataEvent;
use Valvoid\Fusion\Log\Events\Level;

/**
 * External meta interpreter.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Interpreter
{
    /**
     * Interprets meta.
     *
     * @param mixed $entry Entry.
     */
    public static function interpret(string $layer, mixed $entry): void
    {
        if (!is_array($entry) || empty($entry))
            Bus::broadcast(new MetadataEvent(
                "Meta must be an assoc array.",
                Level::ERROR
            ));

        foreach ($entry as $key => $value)
            match($key) {
                "name" => self::interpretName($value),
                "description" => self::interpretDescription($value),
                "lifecycle" => Lifecycle::interpret($value),
                "version" => self::interpretVersion($value),
                "structure" => Structure::interpret($value),
                "environment" => Environment::interpret($value),
                "id" => self::interpretId($layer, $value),
                default => Bus::broadcast(new MetadataEvent(
                    "The unknown \"$key\" index must be " .
                    "\"name\", \"description\", \"lifecycle\", \"id\", \"version\", " .
                    "\"structure\" or \"environment\" string.",
                    Level::ERROR,
                    [$key]
                ))
            };
    }

    /**
     * Interprets name.
     *
     * @param mixed $entry Name.
     */
    private static function interpretName(mixed $entry): void
    {
        if ($entry === null)
            return;

        if (!is_string($entry) || !$entry)
            Bus::broadcast(new MetadataEvent(
                "The value of the \"name\" index must " .
                "be a non-empty string.",
                Level::ERROR,
                ["name"]
            ));
    }

    /**
     * Interprets description.
     *
     * @param mixed $entry
     */
    private static function interpretDescription(mixed $entry): void
    {
        if ($entry === null)
            return;

        if (!is_string($entry) || !$entry)
            Bus::broadcast(new MetadataEvent(
                "The value of the \"description\" index must " .
                "be a non-empty string.",
                Level::ERROR,
                ["description"]
            ));
    }

    /**
     * Interprets ID.
     *
     * @param string $layer Layer.
     * @param mixed $entry ID.
     */
    private static function interpretId(string $layer, mixed $entry): void
    {
        if ($layer != "production")
            Bus::broadcast(new MetadataEvent(
                "The \"id\" index is static and belongs to " .
                "the \"fusion.json\" file.",
                Level::ERROR,
                ["id"]
            ));

        if ($entry === null)
            return;

        if (!preg_match("/^[a-z_][a-z0-9_]{0,20}(\/[a-z_][a-z0-9_]{0,20}){0,4}$/", $entry))
            Bus::broadcast(new MetadataEvent(
                "The value of the \"id\" index must fit following " .
                "criteria: Each segment starts with lowercase alphabetic " .
                "character or underscore. Each segment may consists of lowercase " .
                "alphabetic characters, underscore or digits. Each segment must " .
                "be between 1 and 20 characters long. The optional namespace prefix " .
                "can include up to 4 group names. Segments are separated by a " .
                "forward slash.",
                Level::ERROR,
                ["id"]
            ));
    }

    /**
     * Interprets version.
     *
     * @param mixed $entry Entry.
     */
    private static function interpretVersion(mixed $entry): void
    {
        if ($entry === null)
            return;

        if (!preg_match('/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)' .
            '(?:-((?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*)(?:\.(?:0|[1-9]\d*|\d*[a-zA-Z-]' .
            '[0-9a-zA-Z-]*))*))?(?:\+([0-9a-zA-Z-]+(?:\.[0-9a-zA-Z-]+)*))?$/', $entry))
            Bus::broadcast(new MetadataEvent(
                "The value, package version, of the \"version\" index " .
                "must be a semantic version string.",
                Level::ERROR,
                ["version"]
            ));
    }
}