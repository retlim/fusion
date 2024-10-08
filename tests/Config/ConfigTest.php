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

namespace Valvoid\Fusion\Tests\Config;

use Valvoid\Fusion\Log\Events\Errors\Config as ConfigError;
use Valvoid\Fusion\Bus\Bus;
use Valvoid\Fusion\Config\Config;
use Valvoid\Fusion\Log\Events\Errors\Metadata;
use Valvoid\Fusion\Tests\Test;

/**
 * Config test.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class ConfigTest extends Test
{
    private string $root;

    private array $lazy;

    private Config $config;

    /**
     * @throws Metadata
     * @throws ConfigError
     */
    public function __construct()
    {
        try {
            $this->root = dirname(__DIR__, 2);
            $this->lazy = require $this->root . "/cache/loadable/lazy.php";
            $bus = Bus::___init();
            $this->config = Config::___init($this->root, $this->lazy, []);

            $this->testLockedSingletonInstance();
            $this->testInstanceDestruction();

            $this->config->destroy();
            $bus->destroy();

        } catch (ConfigError $exception) {
            echo "\n[x] " . __CLASS__ . " | " . __FUNCTION__;

            $this->result = false;
        }
    }

    /**
     * @throws Metadata
     * @throws ConfigError
     */
    public function testLockedSingletonInstance(): void
    {
        $instance = Config::___init($this->root, $this->lazy, []);

        // assert indicator for locked and consumed instance
        if ($instance !== true) {
            echo "\n[x] " . __CLASS__ . " | " . __FUNCTION__;

            $this->result = false;
        }
    }

    /**
     * @throws Metadata
     * @throws ConfigError
     */
    public function testInstanceDestruction(): void
    {
        $instance = $this->config;
        $this->config->destroy();
        $this->config = Config::___init($this->root, $this->lazy, []);

        // assert different instances
        if ($instance === $this->config) {
            echo "\n[x] " . __CLASS__ . " | " . __FUNCTION__;

            $this->result = false;
        }
    }
}