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

namespace Valvoid\Fusion\Tests\Tasks\Categorize;

use ReflectionException;
use Valvoid\Fusion\Metadata\External\Category as ExternalCategory;
use Valvoid\Fusion\Metadata\Internal\Category as InternalCategory;
use Valvoid\Fusion\Tasks\Categorize\Categorize;
use Valvoid\Fusion\Tasks\Group;
use Valvoid\Fusion\Tests\Test;

/**
 * Test case for the categorize task.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class CategorizeTest extends Test
{
    public function __construct()
    {
        try {
            $log = new LogMock;
            $group = Group::___init();
            MetadataMock::addMockedMetadata();

            $this->testEfficientCategorization();
            $this->testRedundantCategorization();

            $group->destroy();
            $log->destroy();

        } catch (ReflectionException $exception) {
            echo "\n[x] " . __CLASS__ . " | " . __FUNCTION__;

            if (isset($group))
                $group->destroy();

            if (isset($log))
                $log->destroy();

            $this->result = false;
        }
    }

    public function testEfficientCategorization(): void
    {
        // recycle
        $categorize = new Categorize(["efficiently" => true]);

        $categorize->execute();

        foreach (Group::getInternalMetas() as $metadata) {

            // assert diff version drop
            if ($metadata->getId() == "metadata1" &&
                $metadata->getCategory() === InternalCategory::OBSOLETE)
                continue;

            // assert diff dir recycling
            if ($metadata->getId() == "metadata2" &&
                $metadata->getCategory() === InternalCategory::MOVABLE)
                continue;

            echo "\n[x] " . __CLASS__ . " | " . __FUNCTION__;

            $this->result = false;

            return;
        }

        foreach (Group::getExternalMetas() as $metadata) {

            // assert diff version download
            if ($metadata->getId() == "metadata1" &&
                $metadata->getCategory() === ExternalCategory::DOWNLOADABLE)
                continue;

            // assert diff dir drop
            if ($metadata->getId() == "metadata2" &&
                $metadata->getCategory() === ExternalCategory::REDUNDANT)
                continue;

            echo "\n[x] " . __CLASS__ . " | " . __FUNCTION__;

            $this->result = false;

            return;
        }
    }

    public function testRedundantCategorization(): void
    {
        // rebuild all
        $categorize = new Categorize(["efficiently" => false]);

        $categorize->execute();

        // assert internal drop
        foreach (Group::getInternalMetas() as $metadata)
            if ($metadata->getCategory() !== InternalCategory::OBSOLETE) {
                echo "\n[x] " . __CLASS__ . " | " . __FUNCTION__;

                $this->result = false;

                return;
            }

        // assert new external state
        foreach (Group::getExternalMetas() as $metadata)
            if ($metadata->getCategory() !== ExternalCategory::DOWNLOADABLE) {
                echo "\n[x] " . __CLASS__ . " | " . __FUNCTION__;

                $this->result = false;

                return;
            }
    }
}