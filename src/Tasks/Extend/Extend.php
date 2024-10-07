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

namespace Valvoid\Fusion\Tasks\Extend;

use Valvoid\Fusion\Dir\Dir;
use Valvoid\Fusion\Log\Events\Errors\Error;
use Valvoid\Fusion\Log\Events\Infos\Content;
use Valvoid\Fusion\Log\Log;
use Valvoid\Fusion\Metadata\External\External as ExternalMeta;
use Valvoid\Fusion\Metadata\Internal\Category as InternalMetaCategory;
use Valvoid\Fusion\Metadata\Metadata;
use Valvoid\Fusion\Tasks\Group;
use Valvoid\Fusion\Tasks\Task;

/**
 * Extend task to handle stackable package customizations.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Extend extends Task
{
    /** @var ExternalMeta[] External metas.  */
    private array $externalMetas;

    /** @var string Packages cache directory. */
    private string $packagesDir;

    /** @var array Sorted identifiers. */
    private array $ids = [];

    /** @var array  Structures. */
    private array $structures = [];

    /** @var array Parents per package id. */
    private array $filters = [];

    /**
     * Executes the task.
     *
     * @throws Error Internal error.
     */
    public function execute(): void
    {
        if (Group::getExternalRootMetadata())
            $implication = Group::getImplication();

        else {
            $internalRootMeta = Group::getInternalRootMetadata();
            $implication[$internalRootMeta->getId()] = [

                // no need "source" entry here
                // just extend tree for extension order
                "implication" => Group::getImplication()
            ];
        }

        // flat implication to sorted ids
        $this->initIds($implication);
        $this->initFilters($implication, []);

        // extend new state
        if (Group::hasDownloadable()) {
            Log::info("extend packages");

            $this->externalMetas = Group::getExternalMetas();
            $this->packagesDir = Dir::getPackagesDir();
            $metadata = Group::getInternalRootMetadata();

            // implication recursive root else
            // recycle current
            if ($metadata->getCategory() != InternalMetaCategory::OBSOLETE) {
                $id = $metadata->getId();
                $this->structures[$id] = [
                    "dir" => "$this->packagesDir/$id",
                    "cache" => $metadata->getStructureCache(),
                    "extensions" => $metadata->getStructureExtensions()
                ];

                $this->spread($metadata, $id);
            }

            foreach ($this->externalMetas as $id => $metadata) {
                Log::info(new Content($metadata->getContent()));

                $extension = $metadata->getStructureExtensions();
                $this->structures[$id] = [
                    "dir" => "$this->packagesDir/$id",
                    "cache" => $metadata->getStructureCache(),
                    "extensions" => $extension
                ];

                $this->spread($metadata, $id);
            }

        // refresh
        } else {
            Log::info("refresh cached extension files");

            foreach (Group::getInternalMetas() as $id => $metadata) {
                Log::info(new Content($metadata->getContent()));

                $this->structures[$id] = [
                    "dir" => $metadata->getSource(),
                    "extensions" => $metadata->getStructureExtensions(),
                    "cache" => $metadata->getStructureCache()
                ];
            }
        }

        // filter dirs and
        // create extension files
        foreach ($this->structures as $id => $structure) {
            $filter = $this->filters[$id];
            $dir = $structure["dir"];
            $content = "";

            foreach ($structure["extensions"] as $extension) {
                $content .= "\n\t\"$extension\" => [";

                // only existing content
                // prevent redundant checks after
                if (is_dir("$dir$extension")) {
                    $this->filterExtension("$dir$extension", $filter);

                    // shift ordered identifiers
                    foreach ($this->ids as $index => $id)
                        if (is_dir("$dir$extension/$id"))
                            $content .= "\n\t\t$index => \"$id\",";

                    $content .= "\n\t";
                }

                $content .= "],";
            }

            $cache = $dir . $structure["cache"];

            Dir::createDir($cache);

            if (!file_put_contents(
                "$cache/extensions.php",
                "<?php\n" .
                "// Auto-generated by Fusion package manager. \n// Do not modify.\n" .
                "return [$content\n];",true
            ))
                throw new Error(
                    "Can't write to the file \"$cache/extensions.php\"."
                );
        }
    }

    /**
     * Filters extensions.
     *
     * @param string $dir Current dir.
     * @param array $filter Valid dirs.
     * @throws Error Internal error.
     */
    private function filterExtension(string $dir, array $filter): void
    {
        // empty
        // inside identifier extension
        if (!$filter)
            return;

        foreach (scandir($dir, SCANDIR_SORT_NONE) as $filename) {
            if ($filename == "." || $filename == "..")
                continue;

            $file = "$dir/$filename";

            if (is_dir($file)) {
                if (isset($filter[$filename]))
                    $this->filterExtension("$dir/$filename", $filter[$filename]);

                else
                    Dir::delete($file);

            } else
                Dir::delete($file);
        }
    }

    /**
     * Flat tree - all sorted ids.
     *
     * @param array $tree
     */
    private function initIds(array $tree): void
    {
        foreach ($tree as $id => $subtree) {
            $this->initIds($subtree["implication"]);

            $this->ids[] = $id;
        }
    }

    /**
     * Spreads extensions.
     *
     * @param Metadata $metadata
     * @param string $id
     * @throws Error Internal error.
     */
    private function spread(Metadata $metadata, string $id): void
    {
        // loadable
        // extend other packages
        foreach ($metadata->getStructureSources() as $path => $source)

            // jump over recursive
            if ($path)
                foreach ($this->externalMetas as $externalId => $externalMeta) {
                    if ($id != $externalId) {
                        $dir = "$this->packagesDir/$id" .

                            // has entry for package
                            "$path/$externalId";

                        foreach ($externalMeta->getStructureExtensions() as $extension) {
                            $from = $dir .

                                // own ID group
                                "$extension/$id";

                            if (is_dir($from)) {
                                $to = "$this->packagesDir/$externalId" .

                                    // own ID group
                                    "$extension/$id";

                                // maybe obsolete collision
                                Dir::delete($to);
                                Dir::createDir($to);
                                Dir::rename($from, $to);

                                // storage wrapper
                                // clear empty dir prefixes
                                Dir::clear(
                                    "$this->packagesDir/$id$path",
                                    "/$externalId$extension/$id"
                                );
                            }
                        }
                    }
                }
    }

    /**
     * Get parents of package index. truncate
     * Get structure filter.
     *
     * @param array $tree
     * @param array $filter
     */
    private function initFilters(array $tree, array $filter): void
    {
        foreach ($tree as $id => $subtree) {

            // handle multiple parent
            // package can be a dependency of multiple packages
            // init if not yet
            $this->filters[$id] ??= [];
            $this->filters[$id] = array_merge_recursive($this->filters[$id], $filter);
            $this->filters[$id] = array_merge_recursive($this->filters[$id],

                // create assoc array from id and
                // add it as recursive
                $this->getAssoc(explode('/', $id)));

            $this->initFilters($subtree["implication"], $this->filters[$id]);
        }
    }

    /**
     * Returns assoc array.
     *
     * @param array $breadcrumb
     * @return array
     */
    private function getAssoc(array $breadcrumb): array
    {
        $result = [];
        $key = array_shift($breadcrumb);

        if ($key)
            $result[$key] = $this->getAssoc($breadcrumb);

        return $result;
    }
}