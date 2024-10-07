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
 * Lifecycle interpreter.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Lifecycle
{
    /**
     * Interprets the lifecycle entry.
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
                "The value of the \"lifecycle\" index must be an assoc array.",
                Level::ERROR,
                ["lifecycle"]
            ));

        foreach ($entry as $key => $value)
            match($key) {
                "download" => self::interpretDownload($value),
                "copy" => self::interpretCopy($value),
                "install" => self::interpretInstall($value),
                "update" => self::interpretUpdate($value),
                "migrate" => self::interpretMigrate($value),
                "delete" => self::interpretDelete($value),
                default => Bus::broadcast(new MetadataEvent(
                    "The unknown \"$key\" index must be " .
                    "\"download\", \"copy\", \"install\", \"update\", \"migrate\", or " .
                    "\"delete\" string.",
                    Level::ERROR,
                    ["lifecycle", $key]
                ))
            };
    }

    /**
     * Interprets the lifecycle download entry.
     *
     * @param mixed $entry Entry.
     */
    private static function interpretDownload(mixed $entry): void
    {
        // overlay reset value
        if ($entry === null)
            return;

        if (!is_string($entry) || !$entry)
            Bus::broadcast(new MetadataEvent(
                "The value must be a non-empty string.",
                Level::ERROR,
                ["lifecycle", "download"]
            ));

        if ($entry[0] !== '/')
            Bus::broadcast(new MetadataEvent(
                "The value must be a file relative to " .
                "own package root and starting with a leading forward slash.",
                Level::ERROR,
                ["lifecycle", "download"]
            ));
    }

    /**
     * Interprets the lifecycle copy entry.
     *
     * @param mixed $entry Entry.
     */
    private static function interpretCopy(mixed $entry): void
    {
        // overlay reset value
        if ($entry === null)
            return;

        if (!is_string($entry) || !$entry)
            Bus::broadcast(new MetadataEvent(
                "The value must be a non-empty string.",
                Level::ERROR,
                ["lifecycle", "copy"]
            ));

        if ($entry[0] !== '/')
            Bus::broadcast(new MetadataEvent(
                "The value must be a file relative to " .
                "own package root and starting with a leading forward slash.",
                Level::ERROR,
                ["lifecycle", "copy"]
            ));
    }

    /**
     * Interprets the lifecycle install entry.
     *
     * @param mixed $entry Entry.
     */
    private static function interpretInstall(mixed $entry): void
    {
        // overlay reset value
        if ($entry === null)
            return;

        if (!is_string($entry) || !$entry)
            Bus::broadcast(new MetadataEvent(
                "The value must be a non-empty string.",
                Level::ERROR,
                ["lifecycle", "install"]
            ));

        if ($entry[0] !== '/')
            Bus::broadcast(new MetadataEvent(
                "The value must be a file relative to " .
                "own package root and starting with a leading forward slash.",
                Level::ERROR,
                ["lifecycle", "install"]
            ));
    }

    /**
     * Interprets the lifecycle delete entry.
     *
     * @param mixed $entry Entry.
     */
    private static function interpretDelete(mixed $entry): void
    {
        // overlay reset value
        if ($entry === null)
            return;

        if (!is_string($entry) || !$entry)
            Bus::broadcast(new MetadataEvent(
                "The value must be a non-empty string.",
                Level::ERROR,
                ["lifecycle", "delete"]
            ));

        if ($entry[0] !== '/')
            Bus::broadcast(new MetadataEvent(
                "The value must be a file relative to " .
                "own package root and starting with a leading forward slash.",
                Level::ERROR,
                ["lifecycle", "delete"]
            ));
    }

    /**
     * Interprets the lifecycle update entry.
     *
     * @param mixed $entry Entry.
     */
    private static function interpretUpdate(mixed $entry): void
    {
        // overlay reset value
        if ($entry === null)
            return;

        if (!is_string($entry) || !$entry)
            Bus::broadcast(new MetadataEvent(
                "The value must be a non-empty string.",
                Level::ERROR,
                ["lifecycle", "update"]
            ));

        if ($entry[0] !== '/')
            Bus::broadcast(new MetadataEvent(
                "The value must be a file relative to " .
                "own package root and starting with a leading forward slash.",
                Level::ERROR,
                ["lifecycle", "update"]
            ));
    }

    /**
     * Interprets the lifecycle migrate entry.
     *
     * @param mixed $entry Entry.
     */
    private static function interpretMigrate(mixed $entry): void
    {
        // overlay reset value
        if ($entry === null)
            return;

        if (!is_string($entry) || !$entry)
            Bus::broadcast(new MetadataEvent(
                "The value must be a non-empty string.",
                Level::ERROR,
                ["lifecycle", "migrate"]
            ));

        if ($entry[0] !== '/')
            Bus::broadcast(new MetadataEvent(
                "The value must be a file relative to " .
                "own package root and starting with a leading forward slash.",
                Level::ERROR,
                ["lifecycle", "migrate"]
            ));
    }
}