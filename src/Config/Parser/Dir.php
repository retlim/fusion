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

namespace Valvoid\Fusion\Config\Parser;

use Valvoid\Fusion\Bus\Bus;
use Valvoid\Fusion\Bus\Events\Config as ConfigEvent;
use Valvoid\Fusion\Log\Events\Level;

/**
 * Working directory config parser.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Dir
{
    /**
     * Parses the working directory config.
     *
     * @param array $config Directory config to parse.
     */
    public static function parse(array &$config): void
    {
        self::parsePath($config["dir"]["path"]);
    }

    /**
     * Parses path.
     *
     * @param string $path Path entry.
     */
    private static function parsePath(string &$path): void
    {
        $path = str_replace('\\', '/', $path);
        $path = explode('/', $path);
        $filenames = [];

        foreach ($path as $filename)
            if ($filename == "..")
                array_pop($filenames) ??
                Bus::broadcast(new ConfigEvent(
                    "The value of the \"path\" key, the " .
                    "current working directory, does not point to anything, " .
                    "as it contains a reference (double dot) to a " .
                    "non-existent parent directory.",
                    Level::ERROR,
                    ["dir", "path"]
                ));

            elseif ($filename != '.')
                $filenames[] = $filename;

        $path = implode('/', $filenames);
    }

    /**
     * Returns non-nested path.
     *
     * @param string $path Directory to start.
     * @return string|null Root.
     */
    public static function getNonNestedPath(string $path): ?string
    {
        $match = null;

        while ($path) {
            if (is_file("$path/fusion.json"))
                $match = $path;

            $parent = dirname($path);

            if ($path == $parent)
                break;

            $path = $parent;
        }

        return $match;
    }
}