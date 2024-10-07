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
 * Structure interpreter.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Structure
{
    /**
     * Interprets the structure entry.
     *
     * @param mixed $entry Entry.
     */
    public static function interpret(mixed $entry): void
    {
        // overlay reset value
        if ($entry === null)
            return;

        if (!is_array($entry) || empty($entry))
            Bus::broadcast(new MetadataEvent(
                "The value, package structure, of the \"structure\" " .
                "index must be an assoc array.",
                Level::ERROR,
                ["structure"]
            ));

        self::interpretStructure($entry, ["structure"]);
    }

    /**
     * Interprets structure.
     *
     * @param array $entry Structure.
     * @param array $breadcrumb Index path inside meta.
     */
    private static function interpretStructure(array $entry, array $breadcrumb): void
    {
        foreach ($entry as $key => $value) {
            if (is_string($key))

                // empty
                if (!$key)
                    Bus::broadcast(new MetadataEvent(
                        "The \"$key\" index, source or path prefix, " .
                        "must be a non-empty string.",
                        Level::ERROR,
                        [...$breadcrumb, $key]
                    ));

                // path identifier
                elseif ($key[0] === '/') {

                    // only separator
                    if ($key === '/')
                        Bus::broadcast(new MetadataEvent(
                            "The \"$key\" index, path prefix, " .
                            "must consist of non-empty separated parts. " .
                            "Separator \"/\" must have trailing chars.",
                            Level::ERROR,
                            [...$breadcrumb, $key]
                        ));
                }

            if (is_array($value))
                self::interpretStructure($value, [...$breadcrumb, $key]);

            // not reset
            elseif ($value !== null) {

                // non-empty string
                if (!is_string($value) || !$value)
                    Bus::broadcast(new MetadataEvent(
                        "The value, source suffix, of the \"$key\" index " .
                        "must be a non-empty string.",
                        Level::ERROR,
                        [...$breadcrumb, $key]
                    ));

                // source
                if ($value[0] === '/')
                    Bus::broadcast(new MetadataEvent(
                        "The value, source suffix, of the \"$key\" index " .
                        "must be of type source. Leading separator \"/\" is a path " .
                        "type identifier.",
                        Level::ERROR,
                        [...$breadcrumb, $key]
                    ));
            }
        }
    }
}