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

namespace Valvoid\Fusion\Tasks\Snap;

use Valvoid\Fusion\Dir\Dir;
use Valvoid\Fusion\Log\Events\Errors\Error;
use Valvoid\Fusion\Log\Events\Infos\Content;
use Valvoid\Fusion\Log\Log;
use Valvoid\Fusion\Metadata\External\External as ExternalMetadata;
use Valvoid\Fusion\Metadata\Internal\Internal as InternalMetadata;
use Valvoid\Fusion\Tasks\Group;
use Valvoid\Fusion\Tasks\Task;

/**
 * Snap task to persist current built state.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Snap extends Task
{
    /** @var array<string, ExternalMetadata> External metas. */
    private array $metas;

    /** @var array Implication. */
    private array $implication;

    /** @var array<string, string> Snapshot file content. */
    private array $content;

    /**
     * Executes the task.
     *
     * @throws Error Internal exception.
     */
    public function execute(): void
    {
        Log::info("persist implication and references");

        $this->implication = Group::getImplication();
        $this->metas = Group::getExternalMetas();

        // redundant state
        // refresh/create state file
        if (!Group::hasDownloadable()) {
            $cacheDir = Dir::getCacheDir();
            $metadata = Group::getRootMetadata();
            $id = $metadata->getId();

        } else {
            $metadata = Group::getRootMetadata();
            $id = $metadata->getId();
            $cacheDir = Dir::getPackagesDir() . "/$id" .
                $metadata->getStructureCache();
        }

        // do not cache root
        // only nested dependencies
        if (isset($this->implication[$id]))
            $this->implication = $this->implication[$id]["implication"];

        Dir::createDir($cacheDir);
        Log::info("production:");

        // common production
        // internal or external
        $this->addRootIds(
            $metadata->getProductionIds(),
            "$cacheDir/snapshot.json"
        );

        // internal root only
        // development
        if ($metadata instanceof InternalMetadata) {

            // local development
            $ids = $metadata->getLocalIds();
            $file = "$cacheDir/snapshot.local.json";

            if ($ids !== null) {
                Log::info("local:");
                $this->addRootIds($ids, $file);

            } else
                Dir::delete($file);

            // shared development
            $ids = $metadata->getDevelopmentIds();
            $file = "$cacheDir/snapshot.dev.json";

            if ($ids !== null) {
                Log::info("development:");
                $this->addRootIds($ids, $file);

            } else
                Dir::delete($file);
        }
    }

    /**
     * Adds root IDs.
     *
     * @param array $ids Root IDs.
     * @param string $file Absolute snapshot file.
     * @throws Error Internal exception.
     */
    private function addRootIds(array $ids, string $file): void
    {
        Log::verbose($file);
        $this->content = [];

        // production only
        // external recursive root
        foreach ($ids as $id) {
            $this->addNestedIds($this->implication[$id]["implication"]);
            $this->addContent($id);
        }

        $content = json_encode($this->content,

            // readable content
            JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);

        if (!file_put_contents($file, $content))
            throw new Error(
                "Can't write to the file \"$file\"."
            );
    }

    /**
     * Adds content.
     *
     * @param string $id ID.
     */
    private function addContent(string $id): void
    {
        $metadata = $this->metas[$id];
        $reference = $metadata->getSource()["reference"];
        $layers = $metadata->getLayers();

        // offset
        if (isset($layers["object"]["version"]))
            $reference = $layers["object"]["version"] . ":$reference";

        $this->content[$id] = $reference;

        Log::info(new Content($metadata->getContent()));
    }

    /**
     * Adds nested IDs.
     *
     * @param array $implication Implication.
     */
    private function addNestedIds(array $implication): void
    {
        foreach ($implication as $id => $entry) {
            $this->addNestedIds($entry["implication"]);
            $this->addContent($id);
        }
    }
}