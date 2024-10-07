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

namespace Valvoid\Fusion\Tasks\Replicate\Config;

use Valvoid\Fusion\Config\Normalizer as ConfigNormalizer;

/**
 * Replicate task config normalizer.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Normalizer extends ConfigNormalizer
{

    /** @inheritdoc */
    public static function normalize(array $breadcrumb, array &$config): void
    {
        $config["source"] ??= false;

        if (!isset($config["environment"]["php"]["version"]))
            $config["environment"]["php"]["version"] = [
                "major" => PHP_MAJOR_VERSION,
                "minor" => PHP_MINOR_VERSION,
                "patch" => PHP_RELEASE_VERSION,

                // placeholder
                "release" => "",
                "build" => ""
            ];
    }
}