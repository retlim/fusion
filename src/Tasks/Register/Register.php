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

namespace Valvoid\Fusion\Tasks\Register;

use Valvoid\Fusion\Dir\Dir;
use Valvoid\Fusion\Log\Events\Errors\Error;
use Valvoid\Fusion\Log\Events\Errors\Error as InternalError;
use Valvoid\Fusion\Log\Events\Infos\Content;
use Valvoid\Fusion\Log\Log;
use Valvoid\Fusion\Metadata\External\Category as ExternalMetaCategory;
use Valvoid\Fusion\Metadata\Internal\Category as InternalMetaCategory;
use Valvoid\Fusion\Tasks\Group;
use Valvoid\Fusion\Tasks\Task;

/**
 * Register task.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Register extends Task
{
    /** Executes the task. */
    public function execute(): void
    {
        // optional
        // register new state
        Group::hasDownloadable() ?
            $this->registerNewState() :
            $this->registerCurrentState();
    }

    /** Register external new state. */
    private function registerNewState(): void
    {
        Log::info("register loadable, recyclable, and movable packages");

        $packagesDir = Dir::getPackagesDir();
        $lazy = $asap = [];

        foreach (Group::getExternalMetas() as $id => $meta)
            if ($meta->getCategory() == ExternalMetaCategory::DOWNLOADABLE) {
                Log::info(new Content($meta->getContent()));
                $this->appendInflated($lazy, $asap,

                    // absolute loadable direction
                    // internal state
                    "$packagesDir/$id" . $meta->getStructureCache() . "/loadable",

                    // relative path
                    $meta->getDir()
                );
            }

        foreach (Group::getInternalMetas() as $id => $meta)
            if ($meta->getCategory() != InternalMetaCategory::OBSOLETE) {
                Log::info(new Content($meta->getContent()));
                $this->appendInflated($lazy, $asap,

                    // absolute loadable direction
                    // internal state
                    "$packagesDir/$id" . $meta->getStructureCache() . "/loadable",

                    // relative path
                    $meta->getDir()
                );
            }

        $rootMeta = Group::getExternalRootMetadata() ??
            Group::getInternalRootMetadata();

        $path = $rootMeta->getStructureCache();

        $this->writeAutoloader(Dir::getPackagesDir() . "/" .
            $rootMeta->getId() . $path, $path, $asap, $lazy);
    }

    /** Register internal state. */
    private function registerCurrentState(): void
    {
        Log::info("register internal packages");

        $lazy = $asap = [];

        foreach (Group::getInternalMetas() as $meta) {
            Log::info(new Content($meta->getContent()));
            $this->appendInflated($lazy, $asap,

                // absolute loadable direction
                // internal state
                $meta->getSource() . $meta->getStructureCache() . "/loadable",

                // relative path
                $meta->getDir()
            );
        }

        $this->writeAutoloader(Dir::getCacheDir(),

            // cache path
            Group::getInternalRootMetadata()->getStructureCache(),
            $asap, $lazy
        );
    }

    /**
     * Requires inflated code.
     *
     * @param array $lazy Lazy.
     * @param array $asap ASAP.
     * @param string $dir Dir.
     * @param string $path Path.
     */
    private function appendInflated(array  &$lazy, array &$asap, string $dir,
                                    string $path): void
    {
        $file = "$dir/lazy.php";

        if (file_exists($file)) {
            $map = require $file;

            foreach ($map as $loadable => $file)
                $lazy[$loadable] = $path . $file;
        }

        $file = "$dir/asap.php";

        if (file_exists($file)) {
            $list = require $file;

            foreach ($list as $file)
                $asap[] = $path . $file;
        }
    }


    /**
     * Writes ASAP and lazy loadable autoloader to internal or external
     * state cache directory.
     *
     * @param string $dir Directory.
     * @param string $path Path.
     * @param array $asap ASAP.
     * @param array $lazy Path.
     * @throws Error
     */
    private function writeAutoloader(string $dir, string $path, array $asap,
                                     array $lazy): void
    {
        Dir::createDir($dir);

        // sort key list
        ksort($lazy, SORT_STRING);

        $depth = substr_count($path, '/');
        $autoloader = file_get_contents(__DIR__ . "/Autoloader.php");

        if ($autoloader === false)
            throw new InternalError(
                "Can't read the snapshot file \"" .
                __DIR__ . "/Autoloader.php\"."
            );

        $autoloader = str_replace(
            ", 2)",
            ", $depth)",
            $autoloader
        );

        if ($asap) {
            $content = "";

            foreach ($asap as $file)
                $content .= "\n\t\t'$file',";

            $autoloader = str_replace(
                "ASAP = []",
                "ASAP = [$content\n\t]",
                $autoloader
            );
        }

        if ($lazy) {
            $content = "";

            foreach ($lazy as $loadable => $file)
                $content .= "\n\t\t'$loadable' => '$file',";

            $autoloader = str_replace(
                "LAZY = []",
                "LAZY = [$content\n\t]",
                $autoloader
            );
        }

        if (!file_put_contents("$dir/Autoloader.php", $autoloader))
            throw new Error(
                "Can't write to the file \"$dir/Autoloader.php\"."
            );
    }
}