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

namespace Valvoid\Fusion\Hub\Requests;

use Valvoid\Fusion\Hub\Cache;

/**
 * Hub request.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
abstract class Request
{
    /** @var Cache Hub cache. */
    protected Cache $cache;

    /** @var int Unique request ID. */
    protected int $id;

    /** @var array Source. */
    protected array $source;

    /**
     * Constructs the request.
     *
     * @param Cache $cache Hub cache.
     * @param int $id Request ID.
     * @param array $source Structure source.
     */
    public function __construct(int $id, Cache $cache, array $source)
    {
        $this->cache = $cache;
        $this->id = $id;
        $this->source = $source;
    }
}