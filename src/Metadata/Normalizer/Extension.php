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
 * External meta extension normalizer.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Extension
{
    /**
     * Normalizes extension.
     *
     * @param array $extension
     * @param array $result
     */
    public static function normalize(array $extension, array &$result): void
    {
        foreach ($extension as $path) {

            // remove or jump over nested
            foreach ($result as $i => $p)
                if (str_starts_with($p, $path)) {
                    unset($result[$i]);
                    Bus::broadcast(new MetadataEvent(
                        "Redundant, nested identifier.",
                        Level::NOTICE,
                        ["structure"],
                        [$path, "extension"]
                    ));

                } elseif (str_starts_with($path, $p)) {
                    Bus::broadcast(new MetadataEvent(
                        "Redundant, nested identifier.",
                        Level::NOTICE,
                        ["structure"],
                        [$path, "extension"]
                    ));

                    continue 2;
                }

            $result[] = $path;
        }
    }
}