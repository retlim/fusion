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

namespace Valvoid\Fusion\Tasks\Build\Config;

use Valvoid\Fusion\Bus\Bus;
use Valvoid\Fusion\Bus\Events\Config as ConfigEvent;
use Valvoid\Fusion\Config\Interpreter as ConfigInterpreter;
use Valvoid\Fusion\Log\Events\Level;
use Valvoid\Fusion\Tasks\Build\Build;
use Valvoid\Fusion\Util\Version\Interpreter as VersionInterpreter;

/**
 * Build task config interpreter.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Interpreter extends ConfigInterpreter
{
    /**
     * Interprets the build task config.
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
            self::interpretDefaultTask($breadcrumb, $entry);

        elseif (is_array($entry))
            foreach ($entry as $key => $value)
                match ($key) {
                    "environment" => self::interpretEnvironment($breadcrumb, $value),
                    "task" => self::interpretTask($breadcrumb, $value),
                    "source" => self::interpretSource($breadcrumb, $value),
                    default => Bus::broadcast(new ConfigEvent(
                        "The unknown \"$key\" index must be \"task\", " .
                        "\"environment\", or \"source\" string.",
                        Level::ERROR,
                        [...$breadcrumb, $key]
                    ))
                };

        else Bus::broadcast(new ConfigEvent(
            "The value must be the default \"" . Build::class .
            "\" class name string or a configured array task.",
            Level::ERROR,
            $breadcrumb
        ));
    }

    /**
     * Interprets the default task.
     *
     * @param mixed $entry Task entry.
     */
    private static function interpretDefaultTask(array $breadcrumb, mixed $entry): void
    {
        if ($entry !== Build::class)
            Bus::broadcast(new ConfigEvent(
                "The value must be the \"" . Build::class .
                "\" class name string.",
                Level::ERROR,
                $breadcrumb
            ));
    }

    /**
     * Interprets the task.
     *
     * @param array $breadcrumb Index path inside the config.
     * @param mixed $entry Task entry.
     */
    private static function interpretTask(array $breadcrumb, mixed $entry): void
    {
        // overlay reset value
        if ($entry === null)
            return;

        if ($entry !== Build::class)
            Bus::broadcast(new ConfigEvent(
                "The value, task class name, of the \"task\" " .
                "index must be the \"" . Build::class . "\" string.",
                Level::ERROR,
                [...$breadcrumb, "task"]
            ));
    }

    /**
     * Interprets the source.
     *
     * @param array $breadcrumb Index path inside the config.
     * @param mixed $entry Source entry.
     */
    private static function interpretSource(array $breadcrumb, mixed $entry): void
    {
        // overlay reset value
        if ($entry === null)
            return;

        if (!is_string($entry) || !$entry)
            Bus::broadcast(new ConfigEvent(
                "The value, source, of the \"source\" " .
                "index must be a non-empty string.",
                Level::ERROR,
                [...$breadcrumb, "source"]
            ));
    }

    /**
     * Interprets the environment.
     *
     * @param array $breadcrumb Index path inside the config.
     * @param mixed $entry Environment entry.
     */
    private static function interpretEnvironment(array $breadcrumb, mixed $entry): void
    {
        // overlay reset value
        if ($entry === null)
            return;

        if (!is_array($entry) || empty($entry))
            Bus::broadcast(new ConfigEvent(
                "The value, environment config, of the \"environment\" " .
                "index must be an assoc array.",
                Level::ERROR,
                [...$breadcrumb, "environment"]
            ));

        foreach ($entry as $key => $value)
            match ($key) {
                "php" => self::interpretPhp($breadcrumb, $value),

                // pass error to builder
                // prevent redundant error handling
                default => Bus::broadcast(new ConfigEvent(
                    "The unknown \"$key\" index must be \"php\" string.",
                    Level::ERROR,
                    [...$breadcrumb, $key]
                ))
            };
    }

    /**
     * Interprets the php version.
     *
     * @param array $breadcrumb Index path inside the config.
     * @param mixed $entry Php version.
     */
    private static function interpretPhp(array $breadcrumb, mixed $entry): void
    {
        // overlay reset value
        if ($entry === null)
            return;

        if (!is_array($entry) || empty($entry))
            Bus::broadcast(new ConfigEvent(
                "The value of the \"php\" index must be an assoc array.",
                Level::ERROR,
                [...$breadcrumb, "environment", "php"]
            ));

        foreach ($entry as $key => $value)
            match ($key) {
                "version" => self::interpretPhpVersion($breadcrumb, $value),

                // pass error to builder
                // prevent redundant error handling
                default => Bus::broadcast(new ConfigEvent(
                    "The unknown \"$key\" index must be \"version\" string.",
                    Level::ERROR,
                    [...$breadcrumb, $key]
                ))
            };
    }

    /**
     * Interprets the php version.
     *
     * @param array $breadcrumb Index path inside the config.
     * @param mixed $entry PHP version.
     */
    private static function interpretPhpVersion(array $breadcrumb, mixed $entry): void
    {
        // overlay reset value
        if ($entry === null)
            return;

        if (!is_string($entry) || !$entry ||
            !VersionInterpreter::isSemanticCoreVersion($entry))
            Bus::broadcast(new ConfigEvent(
                "The value of the \"version\" index must be a " .
                "core (major.minor.patch) semantic version string.",
                Level::ERROR,
                [...$breadcrumb, "environment", "php", "version"]
            ));
    }
}