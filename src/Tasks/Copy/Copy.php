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

namespace Valvoid\Fusion\Tasks\Copy;

use Valvoid\Fusion\Dir\Dir;
use Valvoid\Fusion\Log\Events\Errors\Error;
use Valvoid\Fusion\Log\Events\Infos\Content;
use Valvoid\Fusion\Log\Log;
use Valvoid\Fusion\Metadata\Internal\Category as InternalMetaCategory;
use Valvoid\Fusion\Tasks\Group;
use Valvoid\Fusion\Tasks\Task;
use Valvoid\Fusion\Util\Version\Interpreter;
use Valvoid\Fusion\Util\Version\Parser;

/**
 * Copy task to cache non-obsolete internal packages.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Copy extends Task
{
    /** @var string[] Cache and nested source dirs. */
    private array $lockedDirs;

    /**
     * Executes the task.
     *
     * @throws Error Internal error.
     */
    public function execute(): void
    {
        Log::info("cache internal packages");

        // only if remote external
        // do nothing if only internal as it is
        if (!Group::hasDownloadable())
            return;

        $internalMetas = Group::getInternalMetas();
        $externalMetas = Group::getExternalMetas();
        $packagesDir = Dir::getPackagesDir();

        // potential extensions paths to
        // copy only existing packages
        // take external IDs because special cases like clone
        // after clone repo there are maybe committed pseudo extensions
        // no internal packages yet
        // identify them by external valid IDs
        $pseudoIds = array_keys($externalMetas);

        foreach ($pseudoIds as $i => $id)
            if (isset($internalMetas[$id]))
                unset($pseudoIds[$i]);

        // copy recyclable and moveable to state
        foreach ($internalMetas as $id => $metadata)
            if ($metadata->getCategory() != InternalMetaCategory::OBSOLETE) {
                $from = $metadata->getSource();
                $to = "$packagesDir/$id";

                // lock unimportant dirs
                // cache directory
                $this->lockedDirs = [
                    $from . $metadata->getStructureCache()
                ];

                Log::info(new Content($metadata->getContent()));
                Dir::createDir($to);

                // nested source wrapper directories
                // "delete"/ignore obsolete package extensions
                foreach ($metadata->getStructureSources() as $dir => $source)
                    if ($dir) {
                        $this->lockedDirs[] = $from . $dir;

                        // copy pseudos
                        foreach ($pseudoIds as $pseudoId) {
                            $extension = "$from$dir/$pseudoId";

                            if (is_dir($extension)) {
                                Dir::createDir("$to$dir/$pseudoId");
                                $this->copy($extension, "$to$dir/$pseudoId");
                            }
                        }
                    }

                // clear obsolete extensions
                // copy only valid
                foreach ($metadata->getStructureExtensions() as $dir) {
                    $this->lockedDirs[] = $from . $dir;

                    foreach ($internalMetas as $extenderId => $extender)
                        if ($extender->getCategory() != InternalMetaCategory::OBSOLETE) {
                            $extension = "$from$dir/$extenderId";

                            if (is_dir($extension)) {
                                Dir::createDir("$to$dir/$extenderId");
                                $this->copy($extension, "$to$dir/$extenderId");
                            }
                        }
                }

                // content
                $this->copy($from, $to);

            // new version
            // migrate
            // keep persistence
            } elseif (isset($externalMetas[$id])) {
                $internalVersion = Parser::getInflatedVersion($metadata->getVersion());
                $externalVersion = Parser::getInflatedVersion($externalMetas[$id]->getVersion());

                // higher version must support
                // up and downgrade
                if (Interpreter::isBiggerThan($externalVersion, $internalVersion) ?
                    $externalMetas[$id]->onMigrate() :
                    $metadata->onMigrate())

                    // indicator result
                    continue;

                $extensions = $externalMetas[$id]->getStructureExtensions();

                // no custom migration script
                // default fallback migration
                // non-breaking changes or
                if ($internalVersion["major"] == $externalVersion["major"] ||

                    // same extension directories
                    !array_diff($metadata->getStructureExtensions(), $extensions)) {
                    $from = $metadata->getSource();
                    $to = "$packagesDir/$id";

                    foreach ($extensions as $dir)
                        foreach ($internalMetas as $extenderId => $extender)
                            if ($extender->getCategory() != InternalMetaCategory::OBSOLETE) {
                                $extension = "$from$dir/$extenderId";

                                if (is_dir($extension)) {
                                    Dir::createDir("$to$dir/$extenderId");
                                    $this->copy($extension, "$to$dir/$extenderId");
                                }
                            }
                }
            }
    }

    /**
     * Copies content from one to other directory.
     *
     * @param string $from Origin directory.
     * @param string $to Cache directory.
     * @throws Error
     */
    private function copy(string $from, string $to): void
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
                    $this->copy($file, $copy);
                }
            }
    }
}