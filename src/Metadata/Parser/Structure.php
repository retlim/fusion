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

namespace Valvoid\Fusion\Metadata\Parser;

/**
 * Environment parser.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Structure
{
    /**
     * Parses environment entry.
     *
     * @param array $entry
     */
    public static function parse(array &$entry): void
    {
        $structure = [];

        self::parseStructure($entry,$structure);

        $entry = $structure;
    }

    /**
     * Parses structure.
     *
     * @param array $entry Raw structure.
     * @param array $structure Inflated structure.
     */
    private static function parseStructure(array $entry, array &$structure): void
    {
        foreach ($entry as $key => $value)

            // assoc entry
            if (is_string($key)) {

                // directory
                if ($key[0] === '/') {
                    $keyParts = explode('/', $key, 3);
                    $key = '/' . $keyParts[1];

                    // inline multi dir key
                    if (isset($keyParts[2]))
                        $value = ['/' . $keyParts[2] => $value];

                // space
                } else {
                    $keyParts = explode('/', $key, 2);
                    $key = $keyParts[0];

                    // inline multi space key
                    if (isset($keyParts[1]))
                        $value = [$keyParts[1] => $value];
                }

                if (is_array($value)) {
                    if (!isset($structure[$key]))
                        $structure[$key] = [];

                    elseif(!is_array($structure[$key]))
                        $structure[$key] = [
                            $structure[$key]
                        ];

                    self::parseStructure($value, $structure[$key]);

                // prefixed source suffix
                // directory cant be value
                // prevent directory to source transformation
                } elseif (is_string($value) && $value[0] !== '/') {
                    $valueParts = explode('/', $value, 2);

                    // multi space value
                    if (isset($valueParts[1])) {
                        if (!isset($structure[$key]))
                            $structure[$key] = [];

                        $value = [$valueParts[0] => $valueParts[1]];

                        self::parseStructure($value, $structure[$key]);

                    } else
                        $structure[$key][] = $value;

                } else
                    $structure[$key][] = $value;

            // seq
            } elseif (is_array($value))
                self::parseStructure($value, $structure);

            // prefixed source suffix
            // directory cant be value
            // prevent directory to source transformation
            elseif (is_string($value) && $value[0] !== '/') {
                $valueParts = explode('/', $value, 2);

                // multi space value
                if (isset($valueParts[1])) {
                    $value = [$valueParts[0] => $valueParts[1]];

                    self::parseStructure($value, $structure);

                } else
                    $structure[] = $value;

            } else
                $structure[] = $value;
    }
}