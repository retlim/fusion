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
use Valvoid\Fusion\Config\Config;
use Valvoid\Fusion\Config\Interpreter;
use Valvoid\Fusion\Hub\APIs\Local\Local;
use Valvoid\Fusion\Hub\APIs\Remote\Remote;
use Valvoid\Fusion\Log\Events\Level;

/**
 * Hub config interpreter.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Hub
{
    /**
     * Interprets the hub config.
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
                "The value of the \"hub\" index must be an assoc array.",
                Level::ERROR,
                ["hub"]
            ));

        foreach ($entry as $key => $value)
            match($key) {
                "apis" => self::interpretApis($value),
                default => Bus::broadcast(new ConfigEvent(
                    "The unknown \"$key\" index must be " .
                    "\"apis\" string.",
                    Level::ERROR,
                    ["hub", $key]
                ))};
    }

    /**
     * Interprets hub apis.
     *
     * @param mixed $entry
     */
    private static function interpretApis(mixed $entry): void
    {
        if ($entry === null)
            return;

        if (!is_array($entry) || empty($entry))
            Bus::broadcast(new ConfigEvent(
                "The value, apis group, of the \"apis\" " .
                "index must be an assoc array.",
                Level::ERROR,
                ["hub", "apis"]
            ));

        foreach ($entry as $key => $value) {
            if (is_int($key) || !$key)
                Bus::broadcast(new ConfigEvent(
                    "The \"$key\" index, api id, must be a non-empty string.",
                    Level::ERROR,
                    ["hub", "apis", $key]
                ));

            // configured api with/out type identifier
            if (is_array($value))
                (isset($value["api"])) ?
                    self::interpretApiConfig($key, $value) :
                    self::interpretAnonymousApiConfig($key, $value);

            // default api
            // just class name without config
            elseif (is_string($value))
                self::interpretDefaultApi($key, $value);

            // overlay reset
            elseif ($value === null)
                continue;

            else
                Bus::broadcast(new ConfigEvent(
                    "The value, configured or default api, of the \"$key\" " .
                    "index must be a non-empty array or string.",
                    Level::ERROR,
                    ["hub", "apis", $key]
                ));
        }
    }

    /**
     * Interprets default api.
     *
     * @param string $id API id.
     * @param string $entry Default api class to validate.
     */
    private static function interpretDefaultApi(string $id, string $entry): void
    {
        if (!Config::hasLazy($entry))
            Bus::broadcast(new ConfigEvent(
                "The value, default api identifier, of the \"$id\" index must " .
                "be a registered loadable class. Remove this invalid entry from " .
                "the config and execute \"inflate\" task to register custom " .
                "lazy code.",
                Level::ERROR,
                ["hub", "apis", $id]
            ));

        if (!is_subclass_of($entry, Local::class) &&
            !is_subclass_of($entry, Remote::class))
            self::throwApiSubclassError(
                ["hub", "apis", $id],
                $id
            );

        $interpreter = self::getInterpreter($entry);

        if (Config::hasLazy($interpreter)) {
            if (!is_subclass_of($interpreter, Interpreter::class))
                Bus::broadcast(new ConfigEvent(

                // show auto-generated interpreter class
                    "The auto-generated \"$interpreter\" namespace " .
                    "derivation of the \"api\" value \"$entry\", api config interpreter, " .
                    "must be a string, name of a class that implements the \"" .
                    Interpreter::class . "\" interface.",
                    Level::ERROR,
                    ["hub", "apis", $id, "api"]
                ));

            $interpreter::interpret(
                ["hub", "apis", $id],
                $entry
            );
        }
    }

    /**
     * Interprets API config.
     *
     * @param string $id API id.
     * @param array $entry Config.
     */
    private static function interpretApiConfig(string $id, array $entry): void
    {
        $api = $entry["api"];

        if (!Config::hasLazy($api))
            self::throwUnregisteredApiError(
                ["hub", "apis", $id, "api"],
                $id
            );

        if (!is_subclass_of($api, Local::class) &&
            !is_subclass_of($api, Remote::class))
            self::throwApiSubclassError(
                ["hub", "apis", $id, "api"],
                $id
            );

        $interpreter = self::getInterpreter($api);

        if (Config::hasLazy($interpreter)) {
            if (!is_subclass_of($interpreter, Interpreter::class))
                Bus::broadcast(new ConfigEvent(

                    // show auto-generated interpreter class
                    "The auto-generated \"$interpreter\" namespace " .
                    "derivation of the \"api\" value \"$api\", api config interpreter, " .
                    "must be a string, name of a class that implements the \"" .
                    Interpreter::class . "\" interface.",
                    Level::ERROR,
                    ["hub", "apis", $id, "api"]
                ));

            $interpreter::interpret(
                ["hub", "apis", $id, "api"],
                $entry
            );
        }
    }

    /**
     * Interprets anonymous API config. A layer without API identifier.
     *
     * @param string $id API id.
     * @param array $entry Config.
     */
    private static function interpretAnonymousApiConfig(string $id, array $entry): void
    {
        $api = Config::get("hub", "apis", $id, "api");

        if (!$api)
            self::throwApiSubclassError(
                ["hub", "apis", $id],
                $id
            );

        // validated by previous identified hub layer
        $interpreter = self::getInterpreter($api);

        if (Config::hasLazy($interpreter))
            $interpreter::interpret(
                ["hub", "apis", $id],
                $entry
            );
    }

    /**
     * Returns interpreter class name.
     *
     * @param string $api API class name.
     * @return Interpreter::class Interpreter.
     */
    private static function getInterpreter(string $api): string
    {
        return substr($api, 0,

                // namespace length
                strrpos($api, '\\')) . "\Config\Interpreter";
    }

    /**
     * Throws unregistered API class error.
     *
     * @param array $breadcrumb Breadcrumb.
     * @param string $id API ID.
     */
    private static function throwUnregisteredApiError(array $breadcrumb, string $id): void
    {
        Bus::broadcast(new ConfigEvent(
            "The value, default api identifier, of the \"$id\" index must " .
            "be a registered loadable class. Remove this invalid entry from " .
            "the config and execute \"inflate\" task to register custom " .
            "lazy code.",
            Level::ERROR,
            $breadcrumb
        ));
    }

    /**
     * Throws API subclass interface error.
     *
     * @param array $breadcrumb Breadcrumb.
     * @param string $id API ID.
     */
    private static function throwApiSubclassError(array $breadcrumb, string $id): void
    {
        Bus::broadcast(new ConfigEvent(
            "The value, configured API config, of the \"$id\" " .
            "index must have the \"api\" index with a string value, name of " .
            "a class that implements the \"" . Local::class . "\" or \"" .
            Remote::class . "\" class.",
            Level::ERROR,
            $breadcrumb
        ));
    }
}