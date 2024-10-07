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

namespace Valvoid\Fusion\Util\Version;

/**
 * Version util.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Interpreter
{
    /**
     * Returns indicator for semantic version.
     *
     * @param string $entry Version entry.
     * @return bool Indicator.
     */
    public static function isSemanticVersion(string $entry): bool
    {
        return preg_match('/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)' .
            '(?:-((?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*)(?:\.(?:0|[1-9]\d*|\d*[a-zA-Z-]' .
            '[0-9a-zA-Z-]*))*))?(?:\+([0-9a-zA-Z-]+(?:\.[0-9a-zA-Z-]+)*))?$/',
            $entry);
    }

    /**
     * Returns indicator for semantic core version.
     *
     * @param string $entry Version entry.
     * @return bool Indicator.
     */
    public static function isSemanticCoreVersion(string $entry): bool
    {
        return preg_match(
            '/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)$/',
            $entry
        );
    }

    /**
     * Returns indicator for first variable is bigger.
     *
     * @param array $a First variable.
     * @param array $b Second variable.
     * @return bool Indicator.
     */
    public static function isBiggerThan(array $a, array $b): bool
    {
        // true a > b
        // <= false

        if ($a["major"] < $b["major"])
            return false;

        if ($a["major"] > $b["major"])
            return true;

        if ($a["minor"] < $b["minor"])
            return false;

        if ($a["minor"] > $b["minor"])
            return true;

        if ($a["patch"] < $b["patch"])
            return false;

        if ($a["patch"] > $b["patch"])
            return true;

        if ($a["release"] && (!$b["release"] ||
                $a["release"] < $b["release"]))
            return false;

        if ($b["release"] && (!$a["release"] ||
                $a["release"] > $b["release"]))
            return true;

        if ($a["build"] < $b["build"])
            return false;

        if ($a["build"] > $b["build"])
            return true;

        return false;
    }
}