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

namespace Valvoid\Fusion\Tasks\Categorize\Config;

use Valvoid\Fusion\Config\Normalizer as ConfigNormalizer;
use Valvoid\Fusion\Config\Config;

/**
 * Categorize task config normalizer.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Normalizer extends ConfigNormalizer
{
    /**
     * Normalizes the config.
     *
     * @param array $breadcrumb Index path inside the config to the passed sub config.
     * @param array $config Sub config to normalize.
     */
    public static function normalize(array $breadcrumb, array &$config): void
    {
        $config["efficiently"] ??= true;
    }
}