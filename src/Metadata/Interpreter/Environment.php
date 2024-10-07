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
 * Environment interpreter.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Environment
{
    /**
     * Interprets the environment entry.
     *
     * @param mixed $entry Potential lifecycle entry.
     */
    public static function interpret(mixed $entry): void
    {
        // overlay reset value
        if ($entry === null)
            return;

        if (!is_array($entry) || empty($entry))
            Bus::broadcast(new MetadataEvent(
                "The value, package environment, of the \"environment\" " .
                "index must be an assoc array.",
                Level::ERROR,
                ["environment"]
            ));

        foreach ($entry as $key => $value)
            match($key) {
                "php" => self::interpretPhp($value),
                default => Bus::broadcast(new MetadataEvent(
                    "The unknown \"$key\" index must be " .
                    "\"php\" string.",
                    Level::ERROR,
                    ["environment", $key]
                ))
            };
    }

    /**
     * Interprets the php entry.
     *
     * @param mixed $entry Entry.
     */
    private static function interpretPhp(mixed $entry): void
    {
        // overlay reset value
        if ($entry === null)
            return;

        if (!is_array($entry) || empty($entry))
            Bus::broadcast(new MetadataEvent(
                "The value of the \"php\" index must be an assoc array.",
                Level::ERROR,
                ["environment", "php"]
            ));

        foreach ($entry as $key => $value)
            match($key) {
                "version" => self::interpretVersion($value),
                "modules" => self::interpretModules($value),
                default => Bus::broadcast(new MetadataEvent(
                    "The unknown \"$key\" index must be " .
                    "\"version\" or \"modules\" string.",
                    Level::ERROR,
                    ["environment", $key]
                ))
            };
    }

    /**
     * Interprets the version entry.
     *
     * @param mixed $entry Entry.
     */
    private static function interpretVersion(mixed $entry): void
    {
        // overlay reset value
        if ($entry === null)
            return;

        if (!is_string($entry))
            Bus::broadcast(new MetadataEvent(
                "The value of the \"version\" index must be a string.",
                Level::ERROR,
                ["environment", "php", "version"]
            ));
    }

    /**
     * Returns indicator for semantic version core.
     *
     * @param string $entry Entry.
     * @return bool Indicator.
     */
    public static function isSemanticVersionCorePattern(string $entry): bool
    {
        return preg_match(
            "/^(>?|>=?|<?|<=?|==?|!=?)" .
            "(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)$/",
            $entry
        );
    }

    /**
     * Interprets the modules entry.
     *
     * @param mixed $entry Entry.
     */
    private static function interpretModules(mixed $entry): void
    {
        // overlay reset value
        if ($entry === null)
            return;

        if (!is_array($entry) || empty($entry))
            Bus::broadcast(new MetadataEvent(
                "The value of the \"modules\" index must be an assoc array.",
                Level::ERROR,
                ["environment", "php", "modules"]
            ));

        foreach ($entry as $module)
            if (!is_string($module) || !$module)
                Bus::broadcast(new MetadataEvent(
                    "The value of the \"modules\" index must be a seq " .
                    "string array.",
                    Level::ERROR,
                    ["environment", "php", "modules"]
                ));
    }
}