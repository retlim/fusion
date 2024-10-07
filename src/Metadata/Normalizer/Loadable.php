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

namespace Valvoid\Fusion\Metadata\Normalizer;

use Valvoid\Fusion\Bus\Bus;
use Valvoid\Fusion\Bus\Events\Metadata as MetadataEvent;
use Valvoid\Fusion\Log\Events\Level;

/**
 * External meta loadable normalizer.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Loadable
{
    /**
     * Normalizes loadable.
     *
     * @param array $loadable
     * @param string $cache
     * @param array $result
     */
    public static function normalize(array $loadable, string $cache, array &$result): void
    {
        if (!$loadable)
            return;

        $dir = "$cache/loadable";
        $cacheLen = strlen($dir);

        foreach ($loadable as $entry)
            foreach ($entry as $namespace => $path) {

                // redundant
                if (array_key_exists($namespace, $result))
                    Bus::broadcast(new MetadataEvent(
                        "Redundant loadable identifier. Namespace already taken.",
                        Level::NOTICE,
                        ["structure"],
                        [$path, $namespace]
                    ));

                else

                    // nested cache folder
                    if (str_starts_with($path, $dir)) {
                        $path = substr($path, $cacheLen);

                        // default, redundant
                        // empty = cache
                        ($path) ?
                            $result[$namespace] = $path :
                            Bus::broadcast(new MetadataEvent(
                                "Redundant loadable identifier. " .
                                "Cache folder is default.",
                                Level::NOTICE,
                                ["structure"],
                                [$path, $namespace]
                            ));

                    } else
                        Bus::broadcast(new MetadataEvent(
                            "External loadable path. Loadable identifier must " .
                            "be inside \"$dir\" cache folder.",
                            Level::ERROR,
                            ["structure"],
                            [$path, $namespace]
                        ));
            }
    }
}