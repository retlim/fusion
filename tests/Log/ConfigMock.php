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

use ReflectionClass;
use ReflectionException;
use Valvoid\Fusion\Config\Config;

/**
 * Log config mock.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class ConfigMock
{
    private Config $config;

    private ReflectionClass $reflection;

    /**
     * @throws ReflectionException
     */
    public function __construct()
    {
        $this->reflection = new ReflectionClass(Config::class);
        $this->config = $this->reflection->newInstanceWithoutConstructor();
        $this->reflection->setStaticPropertyValue("instance", $this->config);
        $content = $this->reflection->getProperty('content');

        $content->setValue($this->config, [
            "log" => [
                "serializers" => []
            ]
        ]);
    }

    public function destroy(): void
    {
        $this->reflection->setStaticPropertyValue("instance", null);
    }
}