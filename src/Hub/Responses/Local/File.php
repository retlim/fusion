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

namespace Valvoid\Fusion\Hub\Responses\Local;

/**
 * File response.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class File
{
    /** @var string Content. */
    private string $content;

    /**
     * Constructs the file response.
     *
     * @param string $content Raw content.
     */
    public function __construct(string $content)
    {
        $this->content = $content;
    }

    /**
     * Returns content.
     *
     * @return string Content.
     */
    public function getContent(): string
    {
        return $this->content;
    }
}