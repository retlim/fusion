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

namespace Valvoid\Fusion\Tests\Tasks;

use Valvoid\Fusion\Tasks\Group;
use Valvoid\Fusion\Tests\Test;

/**
 * Test case for the task group.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class GroupTest extends Test
{
    private Group $group;

    public function __construct()
    {
        $this->group = Group::___init();

        $this->testLockedSingletonInstance();
        $this->testInstanceDestruction();

        $this->group->destroy();
    }

    public function testLockedSingletonInstance(): void
    {
        $instance = Group::___init();

        // assert indicator for locked and consumed instance
        if ($instance !== true) {
            echo "\n[x] " . __CLASS__ . " | " . __FUNCTION__;

            $this->result = false;
        }
    }

    public function testInstanceDestruction(): void
    {
        $instance = $this->group;
        $this->group->destroy();
        $this->group = Group::___init();

        // assert different instances
        if ($instance === $this->group) {
            echo "\n[x] " . __CLASS__ . " | " . __FUNCTION__;

            $this->result = false;
        }
    }
}