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

namespace Valvoid\Fusion\Hub\Responses\Cache;

/**
 * Response.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
abstract class Cache
{
    /** @var int Unique request ID. */
    protected int $id;

    /**
     * Constructs the response.
     *
     * @param int $id Request ID.
     */
    public function __construct(int $id)
    {
        $this->id = $id;
    }

    /**
     * Returns request ID.
     *
     * @return int ID.
     */
    public function getId(): int
    {
        return $this->id;
    }
}