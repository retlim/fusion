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

namespace Valvoid\Fusion\Tests\Bus;

use Valvoid\Fusion\Bus\Bus;
use Valvoid\Fusion\Tests\Test;

/**
 * Bus test.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class BusTest extends Test
{
    private Bus $bus;

    public function __construct()
    {
        $this->bus = Bus::___init();

        $this->testLockedSingletonInstance();
        $this->testInstanceDestruction();

        $this->bus->destroy();
    }

    public function testLockedSingletonInstance(): void
    {
        $instance = Bus::___init();

        // assert indicator for locked and consumed instance
        if ($instance !== true) {
            echo "\n[x] " . __CLASS__ . " | " . __FUNCTION__;

            $this->result = false;
        }
    }

    public function testInstanceDestruction(): void
    {
        $instance = $this->bus;
        $this->bus->destroy();
        $this->bus = Bus::___init();

        // assert different instances
        if ($instance === $this->bus) {
            echo "\n[x] " . __CLASS__ . " | " . __FUNCTION__;

            $this->result = false;
        }
    }
}