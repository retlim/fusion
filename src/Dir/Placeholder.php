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

namespace Valvoid\Fusion\Dir;

use Valvoid\Fusion\Bus\Bus;
use Valvoid\Fusion\Bus\Events\Config as ConfigEvent;
use Valvoid\Fusion\Log\Events\Errors\Error;
use Valvoid\Fusion\Log\Events\Level;

/**
 * Placeholder to prevent empty working directory.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Placeholder
{
    /**
     * Replaces existing working directory content.
     *
     * @param array $config Working directory config.
     * @param array $filenames Filenames.
     * @throws Error Internal error exception.
     */
    public static function replace(array $config, array $filenames): void
    {
        if (isset($filenames[2])) {
            if (!$config["clearable"])
                Bus::broadcast(new ConfigEvent(
                    "Can't set the default value for the \"path\" " .
                    "key. The current working directory is not empty " .
                    "and the package manager it not authorized to delete the " .
                    "content. Set the value of the \"clearable\" key " .
                    "for authorization to \"true\".",
                    Level::ERROR,
                    ["dir", "path"]
                ));

            foreach ($filenames as $filename)
                if ($filename != "." && $filename != "..")
                    Dir::delete($config["path"] ."/$filename");
        }

        self::write($config["path"]);
    }

    /**
     * Creates working directory.
     *
     * @param array $config Working directory config.
     * @throws Error Internal error.
     */
    public static function create(array $config): void
    {
        if (!$config["creatable"])
            Bus::broadcast(new ConfigEvent(
                "The value of the \"path\" key, the current " .
                "working directory does not exist and the package manager " .
                "is not authorized to create it. Set the value of the " .
                "\"creatable\" key for authorization to \"true\".",
                Level::ERROR,
                ["dir", "path"]
            ));

        // suppress warning and
        // throw own permission error
        if (!mkdir($config["path"], 0755, true))
            Bus::broadcast(new ConfigEvent(
                "Can't create the value of the \"path\" key, " .
                "the current working directory. Check and change the parent " .
                "directory permissions.",
                Level::ERROR,
                ["dir", "path"]
            ));

        self::write($config["path"]);
    }

    /**
     * Writes the meta to the working directory.
     *
     * @param string $path Working directory.
     * @throws Error Internal error.
     */
    private static function write(string $path): void
    {
        $metadata = [
            "name" => "Placeholder",
            "description" => "A placeholder for an empty working directory.",
            "id" => "valvoid/placeholder",
            "version" => "1.0.0",
            "structure" => [

                // the reason
                // enable error handling for remote source
                // build and replication
                // error can occur before the metas provide their cache
                // ends up in error inside an unknown cwd content

                // also it's recycles normalized task behaviour otherwise
                // each task must implement own handling and so on ...
                "/cache" => "cache",
            ],
            "environment" => [
                "php" => [
                    "version" => "8.1.0"
                ]
            ]
        ];

        $metadata = json_encode($metadata,

            // readable
            JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);

        if ($metadata === false)
            throw new Error(
                "Can't encode content for the file \"$path/fusion.json\"."
            );

        if (!file_put_contents("$path/fusion.json", $metadata))
            throw new Error(
                "Can't write to the file \"$path/fusion.json\"."
            );
    }
}