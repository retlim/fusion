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

namespace Valvoid\Fusion\Util\Metadata;

/**
 * Error event path util.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Structure
{
    /**
     * Returns breadcrumb.
     *
     * @param array $structure Meta structure.
     * @param string $source Inline source.
     * @param array $breadcrumb Index path inside structure.
     * @return array|null Entry.
     */
    public static function getBreadcrumb(array $structure, string $source,
                                         array $breadcrumb = []): array|null
    {
        foreach ($structure as $key => $value)

            // assoc
            // directory or source prefix
            if (is_string($key)) {
                if (is_array($value)) {
                    $result = str_starts_with($source, "$key/") ?
                        self::getBreadcrumb($value,

                            // pass suffix
                            substr($source, strlen("$key/")), [...$breadcrumb, $key]) :
                        self::getBreadcrumb($value, $source, [...$breadcrumb, $key]);

                    if ($result)
                        return $result;

                // potential match
                } elseif ($source == "$key/$value")
                    return [...$breadcrumb, $key, $value];

            // seq
            } elseif (is_array($value)) {
                $result = self::getBreadcrumb($value, $source, $breadcrumb);

                if ($result)
                    return $result;

            // potential match
            } elseif ($source == $value)
                return [...$breadcrumb, $value];

        return null;
    }
}