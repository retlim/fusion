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

namespace Valvoid\Fusion\Tests\Dir;

use ReflectionException;
use Valvoid\Fusion\Bus\Bus;
use Valvoid\Fusion\Dir\Dir;
use Valvoid\Fusion\Tests\Test;

/**
 * Hub test.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class DirTest extends Test
{
    private Dir $dir;

    public function __construct()
    {
        try {
            $configMock = new ConfigMock;
            $bus = Bus::___init();
            $this->dir = Dir::___init();

            $this->testLockedSingletonInstance();
            $this->testInstanceDestruction();

            $configMock->destroy();
            $this->dir->destroy();
            $bus->destroy();

        } catch (ReflectionException $exception) {
            echo "\n[x] " . __CLASS__ . " | " . __FUNCTION__;

            $this->result = false;
        }
    }

    public function testLockedSingletonInstance(): void
    {
        $instance = Dir::___init();

        // assert indicator for locked and consumed instance
        if ($instance !== true) {
            echo "\n[x] " . __CLASS__ . " | " . __FUNCTION__;

            $this->result = false;
        }
    }

    public function testInstanceDestruction(): void
    {
        $instance = $this->dir;
        $this->dir->destroy();
        $this->dir = Dir::___init();

        // assert different instances
        if ($instance === $this->dir) {
            echo "\n[x] " . __CLASS__ . " | " . __FUNCTION__;

            $this->result = false;
        }
    }
}