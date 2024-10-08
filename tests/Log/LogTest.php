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

namespace Valvoid\Fusion\Tests\Log;

use ReflectionException;
use Valvoid\Fusion\Log\Log;
use Valvoid\Fusion\Tests\Test;

/**
 * Log test.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class LogTest extends Test
{
    private Log $log;

    public function __construct()
    {
        try {
            $configMock = new ConfigMock;
            $this->log = Log::___init();

            $this->testLockedSingletonInstance();
            $this->testInstanceDestruction();

            $configMock->destroy();
            $this->log->destroy();

        } catch (ReflectionException $exception) {
            echo "\n[x] " . __CLASS__ . " | " . __FUNCTION__;

            $this->result = false;
        }
    }

    public function testLockedSingletonInstance(): void
    {
        $instance = Log::___init();

        // assert indicator for locked and consumed instance
        if ($instance !== true) {
            echo "\n[x] " . __CLASS__ . " | " . __FUNCTION__;

            $this->result = false;
        }
    }

    public function testInstanceDestruction(): void
    {
        $instance = $this->log;
        $this->log->destroy();
        $this->log = Log::___init();

        // assert different instances
        if ($instance === $this->log) {
            echo "\n[x] " . __CLASS__ . " | " . __FUNCTION__;

            $this->result = false;
        }
    }
}