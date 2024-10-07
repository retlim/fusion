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

namespace Valvoid\Fusion\Util\Version;

/**
 * Version parser util.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Parser
{
    /**
     * Returns inflated semantic version.
     *
     * @param string $version Inline semantic version.
     * @return array{
     *     major: string,
     *     minor: string,
     *     patch: string,
     *     release: string,
     *     build: string
     * } Inflated version.
     */
    public static function getInflatedVersion(string $version): array
    {
        // add data
        // offset for example
        $version = explode('+', $version, 2);
        $pattern["build"] = $version[1] ?? "";

        // optional pre-release or
        // empty "production"
        $version = explode('-', $version[0], 2);
        $pattern["release"] = $version[1] ?? "";

        // core
        $version = explode('.', $version[0]);
        $pattern["major"] = $version[0];
        $pattern["minor"] = $version[1];
        $pattern["patch"] = $version[2];

        return $pattern;
    }
}