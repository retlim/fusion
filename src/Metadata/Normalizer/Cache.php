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
 * External meta cache normalizer.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Cache
{
    /**
     * Normalizes cache.
     *
     * @param array $category
     * @param string $cache
     */
    public static function normalize(array $category, string &$cache): void
    {
        // require structure info
        // cache folder
        if (!$category)
            Bus::broadcast(new MetadataEvent(
                "Missing cache folder identifier. Structure must have " .
                "unique cache folder identifier.",
                Level::ERROR,
                ["structure"]
            ));

        $path = $category[0];

        // nested folder
        if (!$path)
            Bus::broadcast(new MetadataEvent(
                "No cache directory. " .
                "Cache folder identifier must be at a nested directory.",
                Level::ERROR,
                ["structure"],
                [$path, "cache"]
            ));

        $cache = $path;
    }
}