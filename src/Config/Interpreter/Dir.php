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

namespace Valvoid\Fusion\Config\Interpreter;

use Valvoid\Fusion\Bus\Bus;
use Valvoid\Fusion\Bus\Events\Config as ConfigEvent;
use Valvoid\Fusion\Config\Parser\Dir as DirectoryParser;
use Valvoid\Fusion\Log\Events\Level;

/**
 * Working directory config interpreter.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Dir
{
    /**
     * Interprets current working directory entry.
     *
     * @param mixed $config Entry.
     */
    public static function interpret(array $config): void
    {
        if (!is_array($config["dir"]) || empty($config["dir"]))
            Bus::broadcast(new ConfigEvent(
                "The value of the \"dir\" index must be an assoc array.",
                Level::ERROR,
                ["dir"]
            ));

        foreach ($config["dir"] as $key => $value)
            match($key) {
                "path" => self::interpretPath($value),
                "creatable" => self::interpretCreatable($value),
                "clearable" => self::interpretClearable($value),
                default => Bus::broadcast(new ConfigEvent(
                    "The unknown \"$key\" index must be \"path\", " .
                    "\"clearable\" or \"creatable\" string.",
                    Level::ERROR,
                    ["dir", $key]
                ))
            };
    }

    /**
     * Interprets path.
     *
     * @param mixed $entry Entry.
     */
    private static function interpretPath(mixed $entry): void
    {
        if (!is_string($entry) && $entry == "")
            Bus::broadcast(new ConfigEvent(
                "Must be non-empty string. Not empty. Absolute path ...",
                Level::ERROR,
                ["dir", "path"]
            ));

        if (str_starts_with($entry, "/..") ||
            str_starts_with($entry, "\\.."))
            Bus::broadcast(new ConfigEvent(
                "The value of the \"path\" key, " .
                "the current working directory, does not point to anything, " .
                "as it starts with a reference (double dot) to a non-existent " .
                "parent directory.",
                Level::ERROR,
                ["dir", "path"]
            ));

        // trailing slash
        // directory separator
        if (str_ends_with($entry, '/') ||
            str_ends_with($entry, '\\'))
            Bus::broadcast(new ConfigEvent(
                "Trailing slash is not a filename. " .
                "Must be string. Absolute path ...",
                Level::ERROR,
                ["dir", "path"]
            ));

        if (is_file($entry))
            Bus::broadcast(new ConfigEvent(
                "The value of the \"path\" key, the current " .
                "working directory must be a directory.",
                Level::ERROR,
                ["dir", "path"]
            ));

        $parentPath = DirectoryParser::getNonNestedPath($entry);

        // has parent package
        if ($parentPath && $parentPath != $entry)
            Bus::broadcast(new ConfigEvent(
                "The value of the \"path\" key, the current " .
                "working directory, is nested as it has a parent package " .
                "structure.",
                Level::ERROR,
                ["dir", "path"]
            ));
    }

    /**
     * Interprets the clearable entry.
     *
     * @param mixed $entry Entry.
     */
    private static function interpretClearable(mixed $entry): void
    {
        if (!is_bool($entry))
            Bus::broadcast(new ConfigEvent(
                "The value, clearable flag, of the index " .
                "\"clearable\" must be a boolean.",
                Level::ERROR,
                ["dir", "clearable"]
            ));
    }

    /**
     * Interprets the creatable entry.
     *
     * @param mixed $entry Entry.
     */
    private static function interpretCreatable(mixed $entry): void
    {
        if (!is_bool($entry))
            Bus::broadcast(new ConfigEvent(
                "The value, creatable flag, of the index " .
                "\"creatable\" must be a boolean.",
                Level::ERROR,
                ["dir", "creatable"]
            ));
    }
}