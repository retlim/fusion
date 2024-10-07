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

namespace Valvoid\Fusion\Config\Parser;

use Valvoid\Fusion\Bus\Bus;
use Valvoid\Fusion\Bus\Events\Config as ConfigEvent;
use Valvoid\Fusion\Config\Config;
use Valvoid\Fusion\Config\Parser as ConfigParser;
use Valvoid\Fusion\Log\Events\Level;
use Valvoid\Fusion\Tasks\Task;

/**
 * Tasks config parser.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Tasks
{
    /**
     * Parses the tasks config.
     *
     * @param array $config Tasks config to parse.
     */
    public static function parse(array &$config): void
    {
        foreach ($config as $key => &$value)
            if (is_subclass_of($value, Task::class))
                $value = [
                    "task" => $value
                ];

            // configured task or group
            elseif (is_array($value))

                // identifiable
                if (isset($value["task"]))
                    self::parseTask(["tasks", $key], $value);

                // identifier in composite layer
                // custom parser already validated in prev layer
                // just pass settings
                elseif ($taskClassName = Config::get("tasks", $key, "task")) {
                    $parser = substr($taskClassName, 0,

                            // namespace length
                            strrpos($taskClassName, '\\')) . "\Config\Parser";

                    // registered file and
                    // implements interface
                    if (Config::hasLazy($parser)) {
                        if (!is_subclass_of($parser, ConfigParser::class))
                            Bus::broadcast(new ConfigEvent(

                                // show auto-generated parser class
                                "The auto-generated \"$parser\" " .
                                "derivation of the \"task\" value, task config parser, " .
                                "must be a string, name of a class that implements the \"" .
                                ConfigParser::class . "\" interface.",
                                Level::ERROR,
                                ["tasks", $key]
                            ));

                        $parser::parse(
                            ["tasks", $key],
                            $value
                        );
                    }

                // task group
                } else
                    self::parseGroup($key, $value);
    }

    /**
     * Parses task group.
     *
     * @param string $groupId Group id.
     * @param array $config Settings.
     */
    private static function parseGroup(string $groupId, array &$config): void
    {
        foreach ($config as $taskId => &$task)

            // configured task
            if (is_array($task)) {
                $breadcrumb = ["tasks", $groupId, $taskId];

                // identifiable
                if (isset($task["task"])) {
                    self::parseTask($breadcrumb, $task);

                // identifier in composite layer
                } else {
                    $task["task"] = Config::get(...[...$breadcrumb, "task"]);

                    // custom parser already validated in prev layer
                    // just pass settings
                    $parser = substr($task["task"], 0,

                            // namespace length
                            strrpos($task["task"], '\\')) . "\Config\Parser";

                    // registered file and
                    // implements interface
                    if (Config::hasLazy($parser)) {
                        if (!is_subclass_of($parser, ConfigParser::class))
                            Bus::broadcast(new ConfigEvent(

                                // show auto-generated parser class
                                "The auto-generated \"$parser\" " .
                                "derivation of the \"task\" value, task config parser, " .
                                "must be a string, name of a class that implements the \"" .
                                ConfigParser::class . "\" interface.",
                                Level::ERROR,
                                ["tasks", $groupId, $taskId]
                            ));

                        $parser::parse($breadcrumb, $task);
                    }
                }

            // default task
            // normalize to configured
            } elseif(is_subclass_of($task, Task::class))
                $task = [
                    "task" => $task
                ];
    }

    /**
     * Parses task settings.
     *
     * @param array $breadcrumb Index path inside the config.
     * @param array $config
     */
    private static function parseTask(array $breadcrumb, array &$config): void
    {
        $parser = substr($config["task"], 0,

                // namespace length
                strrpos($config["task"], '\\')) . "\Config\Parser";

        // registered file and
        // implements interface
        if (Config::hasLazy($parser)) {
            if (!is_subclass_of($parser, ConfigParser::class))
                Bus::broadcast(new ConfigEvent(

                    // show auto-generated parser class
                    "The auto-generated \"$parser\" " .
                    "derivation of the \"task\" value, task config parser, " .
                    "must be a string, name of a class that implements the \"" .
                    ConfigParser::class . "\" interface.",
                    Level::ERROR,
                    [...$breadcrumb, "task"]
                ));

            $parser::parse($breadcrumb, $config);
        }
    }
}