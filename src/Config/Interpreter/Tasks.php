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
use Valvoid\Fusion\Log\Events\Level;
use Valvoid\Fusion\Tasks\Task;

/**
 * Tasks config interpreter.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Tasks
{
    /**
     * Interprets the task config.
     *
     * @param mixed $entry Tasks entry.
     */
    public static function interpret(mixed $entry): void
    {
        // overlay reset value
        if ($entry === null)
            return;

        if (!is_array($entry) || empty($entry))
            Bus::broadcast(new ConfigEvent(
                "The value, group, of the \"tasks\" " .
                "index must be an assoc array.",
                Level::ERROR,
                ["tasks"]
            ));

        foreach ($entry as $key => $value) {
            if (!is_string($key) || !preg_match("/[a-z]/", $key))
                Bus::broadcast(new ConfigEvent(
                    "The \"$key\" index, task/group id, must be an [a-z] string.",
                    Level::ERROR,
                    ["tasks", $key]
                ));

            // locked name
            // future functionality
            if ($key == "config" || $key == "evaluate" || $key == "cache" ||
                $key == "references" || $key == "metadata" || $key == "package" ||
                $key == "debug" || $key == "push" || $key == "publish" || $key == "release")
                Bus::broadcast(new ConfigEvent(
                    "The ID \"$key\" is locked for a future feature.",
                    Level::ERROR,
                    ["tasks", $key]
                ));

            if (is_array($value)) {

                // configured task with type identifier
                if (isset($value["task"]))
                    self::interpretTaskConfig(["tasks", $key], $value);

                // configured task without type identifier
                // check prev layers
                elseif ($taskClassName = Config::get("tasks", $key, "task"))
                    self::interpretAnonymousTaskConfig($taskClassName, ["tasks", $key], $value);

                // task group
                else
                    self::interpretGroup($key, $value);

            // default task
            } elseif (is_string($value))
                self::interpretDefaultTask(["tasks", $key], $key, $value);

            // not reset
            elseif ($value !== null)
                Bus::broadcast(new ConfigEvent(
                    "The value, task group, configured or default task, of the \"$key\" " .
                    "index must be a non-empty string or array.",
                    Level::ERROR,
                    ["tasks", $key]
                ));
        }
    }

    /**
     * Interprets task group entry.
     *
     * @param string $group Group id.
     * @param mixed $entry Tasks entry.
     */
    private static function interpretGroup(string $group, mixed $entry): void
    {
        // overlay reset value
        if ($entry === null)
            return;

        if (!is_array($entry) || empty($entry))
            Bus::broadcast(new ConfigEvent(
                "The value, task group, of the \"$group\" " .
                "index must be an assoc array.",
                Level::ERROR,
                ["tasks", $group]
            ));

        foreach ($entry as $key => $value) {
            if (!is_string($key) || !preg_match("/[a-z]/", $key))
                Bus::broadcast(new ConfigEvent(
                    "The \"$key\" index, task id, must be an [a-z] string.",
                    Level::ERROR,
                    ["tasks", $group, $key]
                ));

            if (is_array($value)) {

                // configured task with type identifier
                if (isset($value["task"]))
                    self::interpretTaskConfig(["tasks", $group, $key], $value);

                // configured task without type identifier
                // check prev layers
                elseif ($taskClassName = Config::get("tasks", $group, $key, "task"))
                    self::interpretAnonymousTaskConfig($taskClassName, ["tasks", $group, $key], $value);

                // nested group
                else
                    Bus::broadcast(new ConfigEvent(
                        "The value, configured task, of the \"$key\" " .
                        "index must have an identifier, nested \"task\" index.",
                        Level::ERROR,
                        ["tasks", $group, $key]
                    ));

            // default task
            } elseif (is_string($value))
                self::interpretDefaultTask(["tasks", $group, $key], $key, $value);

            // not reset
            elseif ($value !== null)
                Bus::broadcast(new ConfigEvent(
                    "The value, configured or default task, of the \"$key\" " .
                    "index must be a non-empty array or string.",
                    Level::ERROR,
                    ["tasks", $group, $key]
                ));
        }
    }

    /**
     * Interprets default task.
     *
     * @param array $breadcrumb Index path inside the config.
     * @param string $taskId Task id.
     * @param string $entry Default task class to validate.
     */
    private static function interpretDefaultTask(array $breadcrumb, string $taskId, string $entry): void
    {
        if (!Config::hasLazy($entry))
            Bus::broadcast(new ConfigEvent(
                "The value, default task identifier, of the \"$taskId\" index must " .
                "be a registered loadable class. Remove this invalid entry from " .
                "the config and execute \"inflate\" task to register custom " .
                "lazy code.",
                Level::ERROR,
                $breadcrumb
            ));

        if (!is_subclass_of($entry, Task::class))
            Bus::broadcast(new ConfigEvent(
                "The value, default task identifier, of the " .
                "\"$taskId\" index must be a string, name of a class that extends the \"" .
                Task::class . "\" class.",
                Level::ERROR,
                $breadcrumb
            ));

        $interpreter = substr($entry, 0,

                // namespace length
                strrpos($entry, '\\')) . "\Config\Interpreter";

        // registered file and
        // implements interface
        if (Config::hasLazy($interpreter)) {
            if (!is_subclass_of($interpreter, Interpreter::class))
                Bus::broadcast(new ConfigEvent(

                // show auto-generated interpreter class
                    "The auto-generated \"$interpreter\" namespace " .
                    "derivation of the \"task\" value \"$entry\", task config interpreter, " .
                    "must be a string, name of a class that implements the \"" .
                    Interpreter::class . "\" interface.",
                    Level::ERROR,
                    $breadcrumb
                ));

            $interpreter::interpret($breadcrumb, $entry);
        }
    }

    /**
     * Interprets configured task.
     *
     * @param array $breadcrumb Index path inside the config.
     * @param array $entry Task config entry to validate.
     */
    private static function interpretTaskConfig(array $breadcrumb, array $entry): void
    {
        $task = $entry["task"];

        if (!Config::hasLazy($task))
            Bus::broadcast(new ConfigEvent(
                "The value, configured task identifier, of the \"task\" index must " .
                "be a registered loadable class. Remove this invalid entry from " .
                "the config and execute \"inflate\" task to register custom " .
                "lazy code.",
                Level::ERROR,
                [...$breadcrumb, "task"]
            ));

        if (!is_subclass_of($task, Task::class))
            Bus::broadcast(new ConfigEvent(
                "The value, configured task identifier, of the \"task\" " .
                "index must be a string, name of a class that extends the \"" .
                Task::class . "\" class.",
                Level::ERROR,
                [...$breadcrumb, "task"]
            ));

        $interpreter = substr($task, 0,

                // namespace length
                strrpos($task, '\\')) . "\Config\Interpreter";

        // registered file and
        // implements interface
        if (Config::hasLazy($interpreter)) {
            if (!is_subclass_of($interpreter, Interpreter::class))
                Bus::broadcast(new ConfigEvent(

                    // show auto-generated interpreter class
                    "The auto-generated \"$interpreter\" namespace " .
                    "derivation of the \"task\" value \"$task\", task config interpreter, " .
                    "must be a string, name of a class that implements the \"" .
                    Interpreter::class . "\" interface.",
                    Level::ERROR,
                    $breadcrumb
                ));

            $interpreter::interpret($breadcrumb, $entry);
        }
    }

    /**
     * Interprets anonymous (without task identifier) task config.
     *
     * @param string $taskClassName Task class name.
     * @param array $breadcrumb Index path inside the config.
     * @param mixed $entry Task config entry.
     */
    private static function interpretAnonymousTaskConfig(string $taskClassName,
                                                         array $breadcrumb, array $entry): void
    {
        // already validated
        $interpreter = substr($taskClassName, 0,

                // namespace length
                strrpos($taskClassName, '\\')) . "\Config\Interpreter";

        if (Config::hasLazy($interpreter)) {
            if (!is_subclass_of($interpreter, Interpreter::class))
                Bus::broadcast(new ConfigEvent(

                    // show auto-generated interpreter class
                    "The auto-generated \"$interpreter\" namespace " .
                    "derivation of the \"task\" value \"$taskClassName\", task config interpreter, " .
                    "must be a string, name of a class that implements the \"" .
                    Interpreter::class . "\" interface.",
                    Level::ERROR,
                    $breadcrumb
                ));

            $interpreter::interpret($breadcrumb, $entry);
        }
    }
}