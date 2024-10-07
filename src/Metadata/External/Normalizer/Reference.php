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

namespace Valvoid\Fusion\Metadata\External\Normalizer;

/**
 * Reference parser.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Reference
{
    /**
     * Returns normalized reference.
     *
     * @param string $reference Inline reference.
     * @return array{
     *     reference: string,
     *     version?: string
     * } Normalized reference.
     */
    public static function getNormalizedReference(string $reference): array
    {
        $reference = explode(':', $reference);

        // fake version
        // branch|commit|whatever ... offset
        if (isset($reference[1]))
            return [
                "version" => $reference[0],
                "reference" => $reference[1]
            ];

        return [
            "reference" => $reference[0]
        ];
    }
}