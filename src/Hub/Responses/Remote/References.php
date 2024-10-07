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

namespace Valvoid\Fusion\Hub\Responses\Remote;

/**
 * References response.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class References
{
    /** @var string[] Potential inline semantic versions. */
    private array $entries;

    /** @var string|null Optional next pagination link. */
    private string|null $url;

    /**
     * Constructs the response.
     *
     * @param string[] $entries Potential inline semantic versions.
     * @param string|null $url Optional next pagination link.
     */
    public function __construct(array $entries, string|null $url)
    {
        $this->entries = $entries;
        $this->url = $url;
    }

    /**
     * Returns entries.
     *
     * @return string[] Entries.
     */
    public function getEntries(): array
    {
        return $this->entries;
    }

    /**
     * Returns next link.
     *
     * @return string|null Link.
     */
    public function getUrl(): ?string
    {
        return $this->url;
    }
}