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

namespace Valvoid\Fusion\Tasks\Categorize;

use Valvoid\Fusion\Log\Events\Infos\Content;
use Valvoid\Fusion\Log\Log;
use Valvoid\Fusion\Metadata\External\Category as ExternalMetaCategory;
use Valvoid\Fusion\Metadata\External\External;
use Valvoid\Fusion\Metadata\Internal\Category as InternalMetaCategory;
use Valvoid\Fusion\Metadata\Internal\Internal;
use Valvoid\Fusion\Tasks\Group;
use Valvoid\Fusion\Tasks\Task;

/**
 * Categorize task to sort metas.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Categorize extends Task
{
    /** @var array<string, Internal> Internal metas. */
    private array $metas = [];

    /** @var bool Has recursive root indicator. */
    private bool $hasExternalRoot = false;

    /** @var array<string, External> Loadable category wrapper. */
    private array $loadable = [];

    /** @var array<string, Internal> Obsolete category wrapper. */
    private array $obsolete = [];

    /** @var array<string, Internal> Recyclable category wrapper. */
    private array $recyclable = [];

    /** @var array<string, Internal> Movable category wrapper. */
    private array $movable = [];

    /**
     * Executes the task.
     */
    public function execute(): void
    {
        $this->metas = Group::getInternalMetas();

        $this->config["efficiently"] ?
            $this->categorizeEfficiently() :
            $this->categorizeRedundant();
    }

    /** Categorizes redundant. */
    private function categorizeRedundant(): void
    {
        Log::info("categorize metas redundant");

        // identifier is common then
        foreach (Group::getExternalMetas() as $id => $meta) {
            $dir = $meta->getDir();
            $this->loadable[$id] = $meta;

            $meta->setCategory(

                // download new
                ExternalMetaCategory::DOWNLOADABLE);

            if (isset($this->metas[$id])) {
                $this->obsolete[$id] = $this->metas[$id];

                // remove for lazy rest loop
                unset($this->metas[$id]);
                $this->obsolete[$id]->setCategory(

                    // different versions or not
                    // drop required
                    InternalMetaCategory::OBSOLETE);
            }

            // empty director = root
            if (!$this->hasExternalRoot && !$dir)
                $this->hasExternalRoot = true;
        }

        $this->handleRest();
        $this->notify();
    }

    /** Categorizes efficiently. */
    private function categorizeEfficiently(): void
    {
        Log::info("categorize metas efficiently");

        // identifier is common then
        foreach (Group::getExternalMetas() as $id => $meta) {
            $dir = $meta->getDir();

            // empty director = root
            if (!$this->hasExternalRoot && !$dir)
                $this->hasExternalRoot = true;

            // decision
            if (isset($this->metas[$id])) {
                if ($meta->getVersion() == $this->metas[$id]->getVersion()) {
                    if ($dir != $this->metas[$id]->getDir()) {
                        $this->movable[$id] = $this->metas[$id];
                        $this->metas[$id]->setCategory(

                            // keep and
                            // move to other direction
                            InternalMetaCategory::MOVABLE);

                    } else {
                        $this->recyclable[$id] = $this->metas[$id];
                        $this->metas[$id]->setCategory(

                            // do nothing
                            // keep as it is
                            InternalMetaCategory::RECYCLABLE);
                    }

                    $meta->setCategory(

                        // do nothing
                        ExternalMetaCategory::REDUNDANT);

                } else {
                    $this->obsolete[$id] = $this->metas[$id];
                    $this->loadable[$id] = $meta;

                    $this->metas[$id]->setCategory(

                        // different versions
                        // other required
                        InternalMetaCategory::OBSOLETE);

                    $meta->setCategory(

                        // download new
                        ExternalMetaCategory::DOWNLOADABLE);
                }

                // remove for lazy rest loop
                unset($this->metas[$id]);
                continue;
            }

            $this->loadable[$id] = $meta;
            $meta->setCategory(

                // download new
                ExternalMetaCategory::DOWNLOADABLE);
        }

        $this->handleRest();
        $this->notify();
    }

    /** Handles rest. */
    private function handleRest(): void
    {
        // handle rest
        foreach ($this->metas as $id => $meta) {
            if (!$this->hasExternalRoot && !$meta->getDir()) {
                $this->recyclable[$id] = $meta;
                $meta->setCategory(

                    // recycle current root
                    InternalMetaCategory::RECYCLABLE);

                continue;
            }

            $this->obsolete[$id] = $meta;
            $meta->setCategory(

                // just drop
                InternalMetaCategory::OBSOLETE);
        }
    }

    /** Notifies result. */
    private function notify(): void
    {
        if ($this->recyclable) {
            Log::info("recycle:");

            foreach ($this->recyclable as $meta)
                Log::info(new Content($meta->getContent()));
        }

        if ($this->loadable) {
            Log::info("download:");

            foreach ($this->loadable as $meta)
                Log::info(new Content($meta->getContent()));
        }

        if ($this->obsolete) {
            Log::info("delete:");

            foreach ($this->obsolete as $meta)
                Log::info(new Content($meta->getContent()));
        }

        if ($this->movable) {
            Log::info("move:");

            foreach ($this->movable as $meta)
                Log::info(new Content($meta->getContent()));
        }
    }
}