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

namespace Valvoid\Fusion\Util\Reference;

use Valvoid\Fusion\Util\Pattern\Interpreter as PatternInterpreter;

/**
 * Reference normalizer util.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Normalizer
{
    /**
     * Returns versions.
     *
     * @param array $reference Reference.
     * @return array<string, array{
     *     build: string,
     *     release: string,
     *     offset: string,
     *     major: string,
     *     minor: string,
     *     patch: string
     * }> Versions.
     */
    public static function getFilteredVersions(array &$versions, array $reference): array
    {
        // per round brackets
        $wrapper = [];
        $skip = false;

        foreach ($reference as $entry) {
            if ($entry == "||") {
                $skip = false;
                $intersection = array_intersect_key(...$wrapper);

                if ($intersection)
                    return $intersection;

                // remove last value
                // the "||" value takes it then
                array_pop($wrapper);
                continue;

            // ignore
            // actually default behavior
            // but parsed for easier debugging
            } elseif ($entry == "&&")
                continue;

            // fake result
            elseif ($skip)
                $entry = [];

            // pattern
            // inflated semantic version
            elseif (isset($entry["sign"])) {
                $matches = [];

                foreach ($versions as $inline => $inflated)
                    if (PatternInterpreter::isMatch($inflated, $entry))
                        $matches[$inline] = $inflated;

                $entry = $matches;

            // brackets
            // nested wrapper
            } else
                $entry = self::getFilteredVersions($versions, $entry);

            // skip all next "&&" entries
            // empty intersection
            $skip = !$entry;
            $wrapper[] = $entry;
        }

        return ($skip || !$wrapper) ? [] :
            array_intersect_key(...$wrapper);
    }
}