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

namespace Valvoid\Fusion\Tasks\Build;

use Valvoid\Fusion\Metadata\External\Builder;

/**
 * Multi version path util.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Trace
{
    /**
     * Returns path to a version.
     *
     * @param array $implication Implication.
     * @param string $id Package ID.
     * @param string $version Version.
     * @return array<int, array{
     *     source: string,
     *     builder: Builder
     * }> Path.
     */
    public static function getVersionPath(array $implication, string $id,
                                          string $version): array
    {
        $path = [];

        foreach ($implication as $identifier => $entry)
            if ($id == $identifier && isset($entry["implication"][$version]))
                return [
                    $identifier => [
                        "source" => $entry["source"],
                        "version" => $version
                    ]
                ];

            else foreach ($entry["implication"] as $v => $item) {
                $path = self::getVersionPath($item, $id, $version);

                if ($path)
                    return [
                        $identifier => [
                            "source" => $entry["source"],
                            "version" => $v
                        ],
                        ...$path
                    ];
            }

        return $path;
    }

    /**
     * Returns first match path to a source.
     *
     * @param array $implication Implication.
     * @param string $source Source.
     * @return array Path.
     */
    public static function getSourcePath(array $implication, string $source): array
    {
        $path = [];

        foreach ($implication as $identifier => $entry)
            if ($source == $entry["source"])
                return [
                    $identifier => [
                        "source" => $entry["source"]
                    ]
                ];

            elseif (isset($entry["implication"]))
                foreach ($entry["implication"] as $v => $item) {
                    $path = self::getSourcePath($item, $source);

                    if ($path)
                        return [
                            $identifier => [
                                "source" => $entry["source"],
                                "version" => $v
                            ],
                            ...$path
                        ];
                }

        return $path;
    }

    /**
     * Returns tree.
     *
     * @param array $implication Absolute implication.
     * @param array $path Path.
     * @return array Tree.
     */
    public static function getTree(array $implication, array $path): array
    {
        $tree = [];

        foreach ($implication as $id => $entry)
            if (isset($path[$id]) && isset($entry["implication"][$path[$id]]))
                $tree[$id] = [
                    "source" => $entry["source"],
                    "implication" => self::getTree($entry["implication"][$path[$id]], $path)
                ];

        return $tree;
    }
}