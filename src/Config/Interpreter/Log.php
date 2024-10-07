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
use Valvoid\Fusion\Config\Interpreter;
use Valvoid\Fusion\Config\Config;
use Valvoid\Fusion\Log\Events\Level;
use Valvoid\Fusion\Log\Serializers\Files\File;
use Valvoid\Fusion\Log\Serializers\Streams\Stream;

/**
 * Log config interpreter.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Log
{
    /**
     * Interprets the log config.
     *
     * @param mixed $entry Potential config.
     */
    public static function interpret(mixed $entry): void
    {
        // overlay reset value
        if ($entry === null)
            return;

        if (!is_array($entry) || empty($entry))
            Bus::broadcast(new ConfigEvent(
                "The value of the \"log\" index must be an assoc array.",
                Level::ERROR,
                ["log"]
            ));

        foreach ($entry as $key => $value)
            match($key) {
                "serializers" => self::interpretSerializers($value),
                default => Bus::broadcast(new ConfigEvent(
                    "The unknown \"$key\" index must be " .
                    "\"serializers\" string.",
                    Level::ERROR,
                    ["log", $key]
                ))
            };
    }

    /**
     * Interprets hub serializers.
     *
     * @param mixed $entry Entry.
     */
    private static function interpretSerializers(mixed $entry): void
    {
        // overlay reset value
        if ($entry === null)
            return;

        if (!is_array($entry) || empty($entry))
            Bus::broadcast(new ConfigEvent(
                "The value, serializers group, of the \"serializers\" " .
                "index must be an assoc array.",
                Level::ERROR,
                ["log", "serializers"]
            ));

        foreach ($entry as $key => $value) {
            if (is_int($key) || !$key)
                Bus::broadcast(new ConfigEvent(
                    "The \"$key\" index, serializer id, must be a non-empty string.",
                    Level::ERROR,
                    ["log", "serializers", $key]
                ));

            // configured serializer with/out type identifier
            if (is_array($value))
                (isset($value["serializer"])) ?
                    self::interpretSerializerConfig($key, $value) :
                    self::interpretAnonymousSerializerConfig($key, $value);

            // default serializer
            // just class name without config
            elseif (is_string($value))
                self::interpretDefaultSerializer($key, $value);

            // overlay reset
            elseif ($value === null)
                continue;

            else
                Bus::broadcast(new ConfigEvent(
                    "The value, configured or default serializer, of the \"$key\" " .
                    "index must be a non-empty array or string.",
                    Level::ERROR,
                    ["log", "serializers", $key]
                ));
        }
    }

    /**
     * Interprets default serializer.
     *
     * @param string $id Serializer id.
     * @param string $entry Default serializer class to validate.
     */
    private static function interpretDefaultSerializer(string $id, string $entry): void
    {
        if (!Config::hasLazy($entry))
            Bus::broadcast(new ConfigEvent(
                "The value, default serializer identifier, of the \"$id\" index must " .
                "be a registered loadable class. Remove this invalid entry from " .
                "the config and execute \"inflate\" task to register custom " .
                "lazy code.",
                Level::ERROR,
                ["log", "serializers", $id]
            ));

        if (!is_subclass_of($entry, File::class) &&
            !is_subclass_of($entry, Stream::class))
            self::throwSerializerSubclassError(
                ["log", "serializers", $id],
                $id
            );

        $interpreter = self::getInterpreter($entry);

        if (Config::hasLazy($interpreter)) {
            if (!is_subclass_of($interpreter, Interpreter::class))
                Bus::broadcast(new ConfigEvent(

                    // show auto-generated interpreter class
                    "The auto-generated \"$interpreter\" namespace " .
                    "derivation of the \"serializer\" value \"$entry\", serializer config interpreter, " .
                    "must be a string, name of a class that implements the \"" .
                    Interpreter::class . "\" interface.",
                    Level::ERROR,
                    ["log", "serializers", $id, "serializer"]
                ));

            $interpreter::interpret(
                ["log", "serializers", $id],
                $entry
            );
        }
    }

    /**
     * Interprets serializer config.
     *
     * @param string $id Serializer id.
     * @param array $entry Config.
     */
    private static function interpretSerializerConfig(string $id, array $entry): void
    {
        $serializer = $entry["serializer"];

        if (!Config::hasLazy($serializer))
            self::throwUnregisteredSerializerError(
                ["log", "serializers", $id, "serializer"],
                $id
            );

        if (!is_subclass_of($serializer, File::class) &&
            !is_subclass_of($serializer, Stream::class))
            self::throwSerializerSubclassError(
                ["log", "serializers", $id, "serializer"],
                $id
            );

        $interpreter = self::getInterpreter($serializer);

        if (Config::hasLazy($interpreter)) {
            if (!is_subclass_of($interpreter, Interpreter::class))
                Bus::broadcast(new ConfigEvent(

                    // show auto-generated interpreter class
                    "The auto-generated \"$interpreter\" namespace " .
                    "derivation of the \"serializer\" value \"$serializer\", serializer config interpreter, " .
                    "must be a string, name of a class that implements the \"" .
                    Interpreter::class . "\" interface.",
                    Level::ERROR,
                    ["log", "serializers", $id, "serializer"]
                ));

            $interpreter::interpret(
                ["log", "serializers", $id, "serializer"],
                $entry
            );
        }
    }

    /**
     * Interprets anonymous serializer config. A layer without serializer identifier.
     *
     * @param string $id Serializer id.
     * @param array $entry Config.
     */
    private static function interpretAnonymousSerializerConfig(string $id, array $entry): void
    {
        $serializer = Config::get("log", "serializers", $id, "serializer");

        if (!$serializer)
            self::throwSerializerSubclassError(
                ["log", "serializers", $id],
                $id
            );

        // validated by previous identified hub layer
        $interpreter = self::getInterpreter($serializer);

        if (Config::hasLazy($interpreter))
            $interpreter::interpret(
                ["log", "serializers", $id],
                $entry
            );
    }

    /**
     * Returns interpreter class name.
     *
     * @param string $serializer Serializer class name.
     * @return Interpreter::class Interpreter.
     */
    private static function getInterpreter(string $serializer): string
    {
        return substr($serializer, 0,

                // namespace length
                strrpos($serializer, '\\')) . "\Config\Interpreter";
    }

    /**
     * Throws unregistered serializer class error.
     *
     * @param array $breadcrumb Breadcrumb.
     * @param string $id Serializer ID.
     */
    private static function throwUnregisteredSerializerError(array $breadcrumb, string $id): void
    {
        Bus::broadcast(new ConfigEvent(
            "The value, default serializer identifier, of the \"$id\" index must " .
            "be a registered loadable class. Remove this invalid entry from " .
            "the config and execute \"inflate\" task to register custom " .
            "lazy code.",
            Level::ERROR,
            $breadcrumb
        ));
    }

    /**
     * Throws serializer subclass interface error.
     *
     * @param array $breadcrumb Breadcrumb.
     * @param string $id Serializer ID.
     */
    private static function throwSerializerSubclassError(array $breadcrumb, string $id): void
    {
        Bus::broadcast(new ConfigEvent(
            "The value, configured serializer config, of the \"$id\" " .
            "index must have the \"serializer\" index with a string value, name of " .
            "a class that implements the \"" . File::class . "\", or \"" .
            Stream::class . "\" class.",
            Level::ERROR,
            $breadcrumb
        ));
    }
}