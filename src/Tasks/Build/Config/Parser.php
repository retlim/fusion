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

namespace Valvoid\Fusion\Tasks\Build\Config;

use Valvoid\Fusion\Config\Parser as ConfigParser;
use Valvoid\Fusion\Util\Version\Parser as VersionParser;

/**
 * Build task config parser.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Parser extends ConfigParser
{
    /**
     * @param array $breadcrumb
     * @param array $config
     */
    public static function parse(array $breadcrumb, array &$config): void
    {
        if (isset($config["environment"]["php"]["version"]))
            $config["environment"]["php"]["version"] = VersionParser::getInflatedVersion(
                $config["environment"]["php"]["version"]
            );
    }
}