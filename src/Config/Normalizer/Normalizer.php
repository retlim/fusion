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

namespace Valvoid\Fusion\Config\Normalizer;

/**
 * Config normalizer.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Normalizer
{
    /**
     * Normalizes the config.
     *
     * @param array $config Config.
     */
    public static function normalize(array &$config): void
    {
        $config = self::removeResetEntries($config);

        Hub::normalize($config);
        Tasks::normalize($config);
        Log::normalize($config);
    }

    /**
     * Removes reset config entries.
     *
     * @param array $config Config.
     * @return array Cleared config.
     */
    public static function removeResetEntries(array $config): array
    {
        foreach ($config as $key => $value) {
            if (is_array($value))
                $config[$key] = self::removeResetEntries($value);

            if ($config[$key] === null)
                unset($config[$key]);
        }

        return $config;
    }

    /**
     * Overlays composite config.
     *
     * @param array $config Composite config.
     * @param array $layer On top config.
     */
    public static function overlay(array &$config, array $layer): void
    {
        foreach ($layer as $key => $value)
            if ($value === null)
                $config[$key] = $value;

            elseif (is_array($value)) {

                // init shell for one to many add rule
                if (!isset($config[$key]) || !is_array($config[$key]))
                    $config[$key] = [];

                self::overlay($config[$key], $value);

            // extend with seq value if not exist
            // one to many add rule
            } elseif (isset($config[$key]) && is_array($config[$key])) {
                if (!in_array($value, $config[$key]))
                    $config[$key][] = $value;

            // override or set
            // one to one add rule
            } else
                $config[$key] = $value;
    }
}