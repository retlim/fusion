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
 * Persistence config interpreter.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Persistence
{
    /**
     * Interprets the persistence config.
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
                "The value of the \"persistence\" index must be an assoc array.",
                Level::ERROR,
                ["persistence"]
            ));

        foreach ($entry as $key => $value)
            match($key) {
                "overlay" => self::interpretOverlay($value),
                default => Bus::broadcast(new ConfigEvent(
                    "The unknown \"$key\" index must be " .
                    "\"overlay\" string.",
                    Level::ERROR,
                    ["persistence", $key]
                ))
            };
    }

    /**
     * Interprets the overlay.
     *
     * @param mixed $entry Entry.
     */
    private static function interpretOverlay(mixed $entry): void
    {
        if (!is_bool($entry))
            Bus::broadcast(new ConfigEvent(
                "The value, overlay flag, of the index " .
                "\"overlay\" must be a boolean.",
                Level::ERROR,
                ["persistence", "overlay"]
            ));
    }
}