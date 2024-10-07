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

namespace Valvoid\Fusion\Hub\APIs\Remote\Bitbucket\Config;

use Valvoid\Fusion\Config\Normalizer as ConfigNormalizer;

/**
 * Bitbucket config normalizer.
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

        // cloud API 2.0
        if ($config["domain"] == "bitbucket.org") {
            $config["version"] = 2.0;
            $config["url"] = $config["protocol"]  . "://api." .
                $config["domain"] . "/2.0/repositories";

        // data center API 1.0
        } else {
            $config["version"] = 1.0;
            $config["url"] = $config["protocol"] . "://" .
                $config["domain"] . "/rest/api/latest/projects";
        }
    }
}