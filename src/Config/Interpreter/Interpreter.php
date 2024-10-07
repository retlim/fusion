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
use Valvoid\Fusion\Log\Events\Level;

/**
 * Config interpreter.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Interpreter
{
    /**
     * Interprets the config.
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
                "The value, config, must be an assoc array.",
                Level::ERROR
            ));

        foreach ($entry as $key => $value)
            match($key) {
                "persistence" => Persistence::interpret($value),
                "tasks" => Tasks::interpret($value),
                "log" => Log::interpret($value),
                "hub" => Hub::interpret($value),
                "dir" => "", // already done
                default =>  Bus::broadcast(new ConfigEvent(
                    "The unknown \"$key\" index must be \"dir\", " .
                    "\"persistence\", \"hub\", \"tasks\" or \"log\" string.",
                    Level::ERROR,
                    [$key]
                ))};
    }
}