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

namespace Valvoid\Fusion\Hub\APIs\Remote\Valvoid\Config;

use Valvoid\Fusion\Config\Parser as ConfigParser;

/**
 * Valvoid config parser.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Parser extends ConfigParser
{
    /** @inheritdoc */
    public static function parse(array $breadcrumb, array &$config): void
    {
        if (isset($config["tokens"])) {
            if (is_string($config["tokens"]))
                $config["tokens"] = [$config["tokens"]];

            $entry = $config["tokens"];
            $config["tokens"] = [];

            self::parseTokens($entry, $config["tokens"]);
        }
    }

    /**
     * Parses tokens.
     *
     * @param array $entry Entry.
     * @param array $tokens Inflated tokens.
     */
    private static function parseTokens(array $entry, array &$tokens): void
    {
        foreach ($entry as $key => $value)

            // assoc entry
            if (is_string($key)) {
                if (str_starts_with($key, "/"))
                    $key = substr($key, 1);

                $keyParts = explode('/', $key, 2);
                $key = $keyParts[0];

                // inline multi space key
                if (isset($keyParts[1]))
                    $value = [$keyParts[1] => $value];

                if (is_array($value)) {
                    if (!isset($tokens[$key]))
                        $tokens[$key] = [];

                    self::parseTokens($value, $tokens[$key]);

                } elseif (is_string($value)) {
                    if (str_starts_with($value, "/"))
                        $value = substr($value, 1);

                    $valueParts = explode('/', $value, 2);

                    // prefixed path value
                    if (isset($valueParts[1])) {
                        if (!isset($tokens[$key]))
                            $tokens[$key] = [];

                        $value = [$valueParts[0] => $valueParts[1]];

                        self::parseTokens($value, $tokens[$key]);

                    } else
                        $tokens[$key][] = $value;
                }

                // string value
            } else {
                if (str_starts_with($value, "/"))
                    $value = substr($value, 1);

                $valueParts = explode('/', $value, 2);

                // prefixed path value
                if (isset($valueParts[1])) {
                    $value = [$valueParts[0] => $valueParts[1]];

                    self::parseTokens($value, $tokens);

                } else
                    $tokens[] = $value;
            }
    }
}