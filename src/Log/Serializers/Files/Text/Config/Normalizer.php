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

namespace Valvoid\Fusion\Log\Serializers\Files\Text\Config;

use Valvoid\Fusion\Config\Normalizer as ConfigNormalizer;
use Valvoid\Fusion\Log\Events\Level;

/**
 * Text file log config normalizer.
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
     * @param array $config Config to normalize.
     */
    public static function normalize(array $breadcrumb, array &$config): void
    {
        self::normalizeFilename($config);
        self::normalizeLevel($config);
    }

    /**
     * Normalizes filename.
     *
     * @param array $config Config.
     */
    private static function normalizeFilename(array &$config): void
    {
        $config["filename"] = date(
            $config["filename"] ??
            "Y.m.d"

        ) . ".txt";
    }

    /**
     * Normalizes level.
     *
     * @param array $config Config.
     */
    private static function normalizeLevel(array &$config): void
    {
        if (isset($config["threshold"])) {
            if (is_string($config["threshold"]))
                $config["threshold"] = Level::tryFromName($config["threshold"]);

        } else
            $config["threshold"] = Level::WARNING;
    }
}