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

namespace Valvoid\Fusion\Tasks\Shift;

use Valvoid\Fusion\Bus\Bus;
use Valvoid\Fusion\Bus\Events\Cache;
use Valvoid\Fusion\Bus\Events\Root;
use Valvoid\Fusion\Dir\Dir;
use Valvoid\Fusion\Log\Events\Errors\Error;
use Valvoid\Fusion\Log\Events\Infos\Content;
use Valvoid\Fusion\Log\Log;
use Valvoid\Fusion\Metadata\External\Category as ExternalMetaCategory;
use Valvoid\Fusion\Metadata\External\External as ExternalMeta;
use Valvoid\Fusion\Metadata\Internal\Category as InternalMetaCategory;
use Valvoid\Fusion\Metadata\Internal\Internal as InternalMeta;
use Valvoid\Fusion\Tasks\Group;
use Valvoid\Fusion\Tasks\Task;

/**
 * Shift task.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Shift extends Task
{
    /** @var string Working directory root. */
    private string $root;

    /** @var ?ExternalMeta Recursive external root meta. */
    private ?ExternalMeta $externalRootMeta;

    /** @var string State directory. */
    private string $state;

    /** @var InternalMeta[] Internal metas. */
    private array $internalMetas;

    /** @var string[] Cache and nested source dirs. */
    private array $lockedDirs;

    /**
     * Executes the task.
     *
     * @throws Error Internal error.
     */
    public function execute(): void
    {
        Log::info("shift state");

        if (!Group::hasDownloadable()) {
            foreach (Group::getInternalMetas() as $meta)
                $meta->onUpdate();

            return;
        }

        $this->root = Dir::getRootDir();
        $this->internalMetas = Group::getInternalMetas();
        $this->state = Dir::getStateDir();
        $this->externalRootMeta = Group::getExternalRootMetadata();

        // complete recursive or
        // pick nested
        ($this->externalRootMeta &&
            $this->externalRootMeta->getCategory() == ExternalMetaCategory::DOWNLOADABLE) ?
            $this->shiftRecursive() :
            $this->shiftNested();
    }

    /**
     * Shifts recursive state.
     *
     * @throws Error Internal error.
     */
    private function shiftRecursive(): void
    {
        $hasInternalFusion = isset($this->internalMetas["valvoid/fusion"]) &&
            str_starts_with(__DIR__, $this->root);

        // keep current package manager code alive
        // if inside working directory
        if ($hasInternalFusion)
            $this->persistCurrentCode(true);

        $this->lockedDirs = [
            Dir::getCacheDir() . "/log",
            $this->state,
            Dir::getPackagesDir(),
            Dir::getTaskDir(),
            Dir::getOtherDir()
        ];

        foreach ($this->internalMetas as $meta)
            $meta->onDelete();

        // delete current state
        // keep cached state dir
        $this->cleanUpDir($this->root);

        // potential new cache directory
        $cachePath = $this->externalRootMeta->getStructureCache();
        $externalCacheDir = $this->root . $cachePath;
        $internalCacheDir = Dir::getCacheDir();

        if ($internalCacheDir != $externalCacheDir) {

            // notify new cache directory change
            Bus::broadcast(new Cache($externalCacheDir));

            // keep current code session alive
            if ($hasInternalFusion)
                Bus::broadcast(new Root(Dir::getOtherDir() .
                    "/valvoid/fusion"));

            $internalPath = Group::getInternalRootMetadata()->getStructureCache();
            $externalPath = $this->externalRootMeta->getStructureCache();
            $externalDirname = explode('/', $externalPath,

                // take second entry
                // fusion dir starts always with slash
                3)[1];

            // add "." prevent naming conflict
            Dir::rename("$this->root/$internalPath", "$this->root/.$externalDirname");
            Dir::createDir("$this->root$externalPath");
            Dir::rename("$this->root/.$externalDirname", "$this->root$externalPath");

            // changed
            $this->state = Dir::getStateDir();
        }

        // shift all but cache
        foreach (scandir($this->state, SCANDIR_SORT_NONE) as $filename)
            if ($filename != "." && $filename != "..")
                if (str_starts_with($cachePath, "/$filename"))
                    $this->shiftNonEmpty(
                        "$this->state/$filename",
                        "$this->root/$filename"
                    );

                else Dir::rename(
                    "$this->state/$filename",
                    "$this->root/$filename"
                );

        foreach (Group::getExternalMetas() as $meta)
            if ($meta->getCategory() == ExternalMetaCategory::DOWNLOADABLE) {
                Log::info(new Content($meta->getContent()));
                $meta->onInstall();
            }

        foreach (Group::getInternalMetas() as $meta)
            if ($meta->getCategory() != InternalMetaCategory::OBSOLETE)
                Log::info(new Content($meta->getContent()));
    }

    /**
     * Shifts non-empty directory.
     *
     * @param string $from Source directory.
     * @param string $to Target directory.
     * @throws Error
     */
    private function shiftNonEmpty(string $from, string $to): void
    {
        foreach (scandir($from, SCANDIR_SORT_NONE) as $filename)
            if ($filename != "." && $filename != "..") {
                $file = "$from/$filename";

                if (is_dir($file) && file_exists("$to/$filename"))
                    $this->shiftNonEmpty($file, "$to/$filename");

                else Dir::rename($file,
                    "$to/$filename"
                );
            }
    }

    /**
     * Shifts nested state.
     *
     * @throws Error Internal error.
     */
    private function shiftNested(): void
    {
        $externalMetas = Group::getExternalMetas();
        $stateDir = Dir::getStateDir();

        foreach ($this->internalMetas as $id => $metadata) {

            // keep current package manager code alive
            // if inside working directory
            if ($id == "valvoid/fusion" &&
                str_starts_with(__DIR__, $this->root))
                $this->persistCurrentCode(false);

            // recycle
            // override only cache
            if ($metadata->getCategory() == InternalMetaCategory::RECYCLABLE) {
                $dir = $metadata->getDir();
                $cache = $metadata->getStructureCache();
                $from = "$stateDir$dir$cache";
                $to = $metadata->getSource() . $cache;

                // clear nested
                if ($dir) {
                    Dir::delete($to);
                    Dir::rename($from, $to);

                // root
                // keep static content
                // state, log, ... directory
                } else {
                    $this->lockedDirs = [
                        "$from/log",
                        $this->state,
                        Dir::getPackagesDir(),
                        Dir::getTaskDir(),
                        Dir::getOtherDir()
                    ];

                    $this->cleanUpDir($to);
                    $this->copyDir($from, $to);
                }

                // refresh extensions
                foreach ($metadata->getStructureExtensions() as $extension) {
                    $to = $metadata->getSource() . $extension;
                    $from = "$stateDir$dir$extension";

                    // extension is optional
                    if (is_dir($from)) {
                        Dir::delete($to);
                        Dir::rename($from, $to);
                    }
                }

                // refresh states
                foreach ($metadata->getStructureStates() as $state) {
                    $to = $metadata->getSource() . $state;
                    $from = "$stateDir$dir$state";

                    // state is optional
                    if (is_dir($from)) {
                        Dir::delete($to);
                        Dir::rename($from, $to);
                    }
                }

                $metadata->onUpdate();
                Log::info(new Content($metadata->getContent()));

            // clean up
            // delete obsolete and movable
            } else {
                $metadata->onDelete();
                Dir::delete($metadata->getSource());
            }
        }

        // shift
        // rename loadable and movable
        foreach ($externalMetas as $id => $metadata) {
            if ($metadata->getCategory() == ExternalMetaCategory::REDUNDANT)
                if ($this->internalMetas[$id]->getCategory() !==
                    InternalMetaCategory::MOVABLE)
                    continue;

            $dir = $metadata->getDir();
            $to = $this->root . $dir;

            Log::info(new Content($metadata->getContent()));

            // prev version?
            Dir::delete($to);
            Dir::createDir($to);
            Dir::rename($stateDir . $dir, $to);

            $metadata->onInstall();
        }
    }

    /**
     * Shifts current package manager code to the /other dir.
     *
     * @param bool $recursive
     * @throws Error
     */
    private function persistCurrentCode(bool $recursive): void
    {
        $meta = $this->internalMetas["valvoid/fusion"];
        $to = Dir::getOtherDir() . "/valvoid/fusion";
        $from = $meta->getSource();

        // recursive root
        // lock state and packages
        if ($recursive) {

            // lock unimportant dirs
            // cache directory
            $this->lockedDirs = [
                $this->state,
                Dir::getPackagesDir(),
                Dir::getTaskDir(),
                Dir::getOtherDir()
            ];

            // nested source wrapper directories
            foreach ($meta->getStructureSources() as $dir => $source)
                if ($dir)
                    $this->lockedDirs[] = $from . $dir;
        }

        Dir::createDir($to);

        $this->copyDir($from, $to);

        // notify new fusion code root
        Bus::broadcast(new Root($to));
    }

    /**
     * Copies content from one to other directory.
     *
     * @param string $from Origin directory.
     * @param string $to Cache directory.
     * @throws Error
     */
    private function copyDir(string $from, string $to): void
    {
        foreach (scandir($from, SCANDIR_SORT_NONE) as $filename)
            if ($filename != "." && $filename != "..") {
                $file = "$from/$filename";
                $copy = "$to/$filename";

                if (is_file($file))
                    Dir::copy($file, $copy);

                // do not copy locked dirs
                // cache and source
                elseif (!in_array($file, $this->lockedDirs)) {
                    Dir::createDir($copy);
                    $this->copyDir($file, $copy);
                }
            }
    }

    /**
     * Cleans up directory.
     *
     * @param string $dir Directory.
     * @throws Error
     */
    private function cleanUpDir(string $dir): void
    {
        foreach (scandir($dir, SCANDIR_SORT_NONE) as $filename)
            if ($filename != "." && $filename != "..") {
                $file = "$dir/$filename";

                if (is_dir($file)) {
                    if (!in_array($file, $this->lockedDirs))
                        $this->cleanUpDir($file);

                } else
                    Dir::delete($file);
            }

        foreach ($this->lockedDirs as $lockedDir)
            if (str_starts_with($lockedDir, $dir))
                return;

        Dir::delete($dir);
    }
}