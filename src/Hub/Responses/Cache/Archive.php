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
 * Archive response.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Archive extends Cache
{
    /** @var string Absolute cache file. */
    private string $file;

    /**
     * Constructs the response.
     *
     * @param int $id Request ID.
     */
    public function __construct(int $id, string $dir)
    {
        parent::__construct($id);

        $this->file = "$dir/archive.zip";
    }

    /**
     * Returns cached absolute archive file.
     *
     * @return string File.
     */
    public function getFile(): string
    {
        return $this->file;
    }
}