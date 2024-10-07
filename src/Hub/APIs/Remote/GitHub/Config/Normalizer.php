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

namespace Valvoid\Fusion\Hub\APIs\Remote\GitHub\Config;

use Valvoid\Fusion\Config\Normalizer as ConfigNormalizer;

/**
 * GitHub config normalizer.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Normalizer extends ConfigNormalizer
{
    /** @inheritdoc */
    public static function normalize(array $breadcrumb, array &$config): void
    {
        $config["tokens"] ??= [];
        $config["protocol"] ??= "https";
        $config["domain"] ??= end($breadcrumb);
        $config["url"] = $config["protocol"] .
            (($config["domain"] != "github.com") ?
                "://" . $config["domain"] . "/APIs/v3/repos" :
                "://api." . $config["domain"] . "/repos");
    }
}