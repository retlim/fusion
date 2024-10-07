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

namespace Valvoid\Fusion\Log\Serializers\Files\JSON\Config;

use Valvoid\Fusion\Bus\Bus;
use Valvoid\Fusion\Bus\Events\Config as ConfigEvent;
use Valvoid\Fusion\Config\Interpreter as ConfigInterpreter;
use Valvoid\Fusion\Log\Events\Level;
use Valvoid\Fusion\Log\Serializers\Files\JSON\JSON;

/**
 * JSON file log config interpreter.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Interpreter extends ConfigInterpreter
{
    /**
     * Interprets the JSON serializer config.
     *
     * @param array $breadcrumb Index path inside the config to the JSON config.
     * @param mixed $entry Config to interpret.
     */
    public static function interpret(array $breadcrumb, mixed $entry): void
    {
        // overlay reset value
        if ($entry === null)
            return;

        if (is_string($entry))
            self::interpretDefaultSerializer($breadcrumb, $entry);

        elseif (is_array($entry))
            foreach ($entry as $key => $value)
                match ($key) {
                    "serializer" => self::interpretSerializer($breadcrumb, $value),
                    "threshold" => self::interpretThreshold($breadcrumb, $value),
                    "filename" => self::interpretFilename($breadcrumb, $value),
                    default => Bus::broadcast(new ConfigEvent(
                        "The unknown \"$key\" index must be " .
                        "\"serializer\" or \"threshold\" string.",
                        Level::ERROR,
                        [...$breadcrumb, $key]
                    ))
                };

        else Bus::broadcast(new ConfigEvent(
            "The value must be the default \"" . JSON::class .
            "\" class name string or a configured array serializer.",
            Level::ERROR,
            $breadcrumb
        ));
    }

    /**
     * Interprets the default serializer.
     *
     * @param mixed $entry Serializer entry.
     */
    private static function interpretDefaultSerializer(array $breadcrumb, mixed $entry): void
    {
        if ($entry !== JSON::class)
            Bus::broadcast(new ConfigEvent(
                "The value must be the \"" . JSON::class .
                "\" class name string.",
                Level::ERROR,
                $breadcrumb
            ));
    }

    /**
     * Interprets the serializer entry.
     *
     * @param mixed $entry Serializer entry.
     */
    private static function interpretSerializer(array $breadcrumb, mixed $entry): void
    {
        // overlay reset value
        if ($entry === null || $entry === JSON::class)
            return;

        Bus::broadcast(new ConfigEvent(
            "The value, serializer class name, of the \"serializer\" " .
            "index must be the \"" . JSON::class . "\" string.",
            Level::ERROR,
            [...$breadcrumb, "serializer"]
        ));
    }

    /**
     * Interprets the filename entry.
     *
     * @param mixed $entry Filename entry.
     */
    private static function interpretFilename(array $breadcrumb, mixed $entry): void
    {
        // overlay reset value
        if ($entry === null || $entry == "Y.m.d"  || $entry == "Y.m" ||
            $entry == "Y.m.d_H:i" || $entry == "Y.m.d_H" || $entry == "Y")
            return;

        Bus::broadcast(new ConfigEvent(
            "The value of the \"filename\" " .
            "index must be the \"Y.m.d\", \"Y.m\", \"Y.m.d_H:i\", " .
            "\"Y.m.d_H\", or \"Y\" string.",
            Level::ERROR,
            [...$breadcrumb, "filename"]
        ));
    }

    /**
     *  Interprets the threshold entry.
     *
     * @param mixed $entry Threshold entry.
     */
    private static function interpretThreshold(array $breadcrumb, mixed $entry): void
    {
        // overlay reset value
        if ($entry === null || $entry instanceof Level)
            return;

        if (!is_string($entry) || Level::tryFromName($entry) === null)
            Bus::broadcast(new ConfigEvent(
                "The value of the \"threshold\" " .
                "index must be a case or a related value of the \"" .
                Level::class . "\".",
                Level::ERROR,
                [...$breadcrumb, "threshold"]
            ));
    }
}