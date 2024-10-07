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

namespace Valvoid\Fusion\Tasks\Stack;

use Valvoid\Fusion\Dir\Dir;
use Valvoid\Fusion\Log\Events\Errors\Error;
use Valvoid\Fusion\Log\Events\Infos\Content;
use Valvoid\Fusion\Log\Log;
use Valvoid\Fusion\Metadata\External\Category as ExternalMetaCategory;
use Valvoid\Fusion\Metadata\External\External;
use Valvoid\Fusion\Metadata\Internal\Category as InternalMetaCategory;
use Valvoid\Fusion\Metadata\Internal\Internal;
use Valvoid\Fusion\Tasks\Group;
use Valvoid\Fusion\Tasks\Task;

/**
 * Stack task.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Stack extends Task
{
    /** @var External[] External metas. */
    private array $metas = [];

    /**
     * Executes the task.
     *
     * @throws Error Internal error.
     */
    public function execute(): void
    {
        Log::info("stack new state");

        if (!Group::hasDownloadable())
            return;

        $stateDir = Dir::getStateDir();
        $packageDir = Dir::getPackagesDir();
        $this->metas = Group::getExternalMetas();
        $rootMetadata = Group::getExternalRootMetadata() ??
            Group::getInternalRootMetadata();

        Dir::createDir($stateDir);
        Log::info(new Content($rootMetadata->getContent()));
        Dir::rename("$packageDir/" . $rootMetadata->getId(),
            $stateDir
        );

        // nested internal
        foreach (Group::getInternalMetas() as $id => $meta) {
            $category = $meta->getCategory();

            if ($category == InternalMetaCategory::OBSOLETE)
                continue;

            $dir = $meta->getDir();

            // jump over root
            if (!$dir)
                continue;

            Log::info(new Content($meta->getContent()));

            // take new directory
            if ($category == InternalMetaCategory::MOVABLE)
                $dir = $this->metas[$id]->getDir();

            $to = $stateDir . $dir;

            Dir::createDir($to);
            Dir::rename("$packageDir/$id", $to);
        }

        // nested external
        foreach ($this->metas as $id => $meta) {
            if ($meta->getCategory() == ExternalMetaCategory::DOWNLOADABLE &&
                $meta->getDir()) {
                Log::info(new Content($meta->getContent()));

                $to = $stateDir . $meta->getDir();

                Dir::createDir($to);
                Dir::rename("$packageDir/$id", $to);
            }
        }

        // trigger lifecycle callbacks
        $this->triggerLifecycleCallbacks(Group::getImplication());

        // root fallback
        // no recursive or source
        if ($rootMetadata instanceof Internal)
            $rootMetadata->onCopy();
    }

    /**
     * Triggers lifecycle callbacks.
     *
     * @param array $implication Implication.
     */
    private function triggerLifecycleCallbacks(array $implication): void
    {
        foreach ($implication as $id => $entry) {

            // nested first
            // respect implication order
            $this->triggerLifecycleCallbacks($entry["implication"]);

            $metadata = $this->metas[$id];

            $metadata->getCategory() == ExternalMetaCategory::DOWNLOADABLE ?
                $metadata->onDownload() :
                $metadata->onCopy();
        }
    }
}