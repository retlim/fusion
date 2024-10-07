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

namespace Valvoid\Fusion\Hub\APIs\Remote;

use Valvoid\Fusion\Hub\Responses\Remote\Offset as OffsetResponse;

/**
 * Remote offset API.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
abstract class Offset extends Remote
{
    /**
     * Returns offset URL.
     *
     * @param string $path Project path.
     * @param string $offset Offset reference (pseudo version:commit/branch offset).
     * @return string URL.
     */
    abstract public function getOffsetUrl(string $path, string $offset): string;

    /**
     * Returns cURL options for the offset endpoint.
     *
     * @return array<int, mixed> Options.
     */
    abstract public function getOffsetOptions(): array;

    /**
     * Returns normalized offset response. A commit ID, sha, hash,
     * whatever unique identifier ...
     *
     * @param array $content Decoded server response content.
     * @return OffsetResponse Response.
     */
    abstract public function getOffset(array $content): OffsetResponse;
}