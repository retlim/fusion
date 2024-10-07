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

namespace Valvoid\Fusion\Metadata\Normalizer;

use Valvoid\Fusion\Bus\Bus;
use Valvoid\Fusion\Bus\Events\Metadata as MetadataEvent;
use Valvoid\Fusion\Log\Events\Level;

/**
 * Metadata normalizer.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Normalizer
{
    /**
     * Normalizes meta.
     *
     * @param array $meta Meta.
     */
    public static function normalize(array &$meta): void
    {
        $meta = self::removeResetEntries($meta);
        $keys = ["id", "version", "name", "description", "structure",
            "environment"];

        // require
        foreach ($keys as $key)
            if (!isset($meta[$key]))
                Bus::broadcast(new MetadataEvent(
                    "Meta must have \"$key\" key.",
                    Level::ERROR,
                    [$key]
                ));

        // empty root package dir or
        // nested package inside parent structure dir and
        // own ID as dir extension
        if ($meta["dir"])
            $meta["dir"] .= "/" . $meta["id"];

        $meta["environment"]["php"]["modules"] ??= [];

        Structure::normalize($meta, "all");

        if (!$meta["structure"]["cache"])
            Bus::broadcast(new MetadataEvent(
                "Structure must have a nested cache directory.",
                Level::ERROR,
                ["structure"]
            ));
    }

    /**
     * Removes reset meta entries.
     *
     * @param array $meta Meta.
     * @return array Cleared meta.
     */
    private static function removeResetEntries(array $meta): array
    {
        foreach ($meta as $key => $value) {
            if (is_array($value))
                $meta[$key] = self::removeResetEntries($value);

            if ($meta[$key] === null)
                unset($meta[$key]);
        }

        return $meta;
    }

    /**
     * Overlays lower meta with higher one.
     *
     * @param array $content Lower meta.
     * @param array $layer Higher meta.
     */
    public static function overlay(array &$content, array $layer): void
    {
        foreach ($layer as $key => $value)
            if ($value === null)
                $content[$key] = $value;

            elseif (is_array($value)) {

                // init shell for one to many add rule
                // convert value to array
                if (!isset($content[$key]) || !is_array($content[$key]))
                    $content[$key] = [];

                self::overlay($content[$key], $value);

                // extend with seq value if not exist
                // one to many add rule
            } elseif (isset($content[$key]) && is_array($content[$key])) {
                if (!in_array($value, $content[$key]))
                    $content[$key][] = $value;

            // override or set
            // one to one add rule
            } else
                $content[$key] = $value;
    }
}