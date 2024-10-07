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

namespace Valvoid\Fusion\Config\Normalizer;

use Valvoid\Fusion\Bus\Bus;
use Valvoid\Fusion\Bus\Events\Config as ConfigEvent;
use Valvoid\Fusion\Config\Config;
use Valvoid\Fusion\Config\Normalizer;
use Valvoid\Fusion\Log\Events\Level;

/**
 * Tasks config normalizer.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Tasks
{
    /**
     * Normalizes the tasks config.
     *
     * @param array $config Config.
     */
    public static function normalize(array &$config): void
    {
        if (!isset($config["tasks"]))
            Bus::broadcast(new ConfigEvent(
                "Missing \"tasks\" key.",
                Level::ERROR
            ));

        // normalize tasks
        foreach ($config["tasks"] as $key => &$entry)
            if (is_array($entry))

                // configured task
                if (isset($entry["task"]))
                    self::normalizeTask(["tasks", $key], $entry);

                // group
                else
                    foreach ($entry as $taskId => &$task)

                        // configured task
                        if (isset($task["task"]))
                            self::normalizeTask(["tasks", $key, $taskId], $task);
    }

    /**
     * Normalizes task config.
     *
     * @param array $breadcrumb Breadcrumb.
     * @param array $config Config.
     */
    public static function normalizeTask(array $breadcrumb, array &$config): void
    {
        $normalizer = substr($config["task"], 0,

                // namespace length
                strrpos($config["task"], '\\')) . "\Config\Normalizer";

        // optional
        // only registered
        if (Config::hasLazy($normalizer)) {
            if (!is_subclass_of($normalizer, Normalizer::class))
                Bus::broadcast(new ConfigEvent(
                    "The auto-generated \"$normalizer\" " .
                    "derivation of the \"task\" value, task config normalizer, " .
                    "must be a string, name of a class that implements the \"" .
                    Normalizer::class . "\" interface.",
                    Level::ERROR,
                    [...$breadcrumb, "task"]
                ));

            $normalizer::normalize($breadcrumb, $config);
        }
    }
}