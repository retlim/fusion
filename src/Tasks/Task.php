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

namespace Valvoid\Fusion\Tasks;

/**
 * Task.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
abstract class Task
{
    /** @var array Task config. */
    protected array $config;

    /**
     * Constructs the task.
     *
     * @param array $config Config.
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Executes the task.
     */
    abstract public function execute(): void;
}