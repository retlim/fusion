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

namespace Valvoid\Fusion\Log\Events\Infos;

use Valvoid\Fusion\Log\Events\Event;

/**
 * Triggered task or task group ID info.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Id implements Event
{
    /** @var string ID. */
    private string $id;

    /**
     * Constructs the ID info.
     *
     * @param string $id ID.
     */
    public function __construct(string $id)
    {
        $this->id = $id;
    }

    /**
     * Returns ID.
     *
     * @return string ID.
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Returns info as string.
     *
     * @return string Info.
     */
    public function __toString(): string
    {
        return "\nid: $this->id";
    }
}