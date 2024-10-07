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

namespace Valvoid\Fusion\Util\Pattern;

/**
 * Pattern interpreter util.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Interpreter
{
    /**
     * Returns indicator for reference pattern.
     *
     * @param string $pattern Reference pattern.
     * @return bool Indicator.
     */
    public static function isReferencePattern(string $pattern): bool
    {
        // sign
        // prefix
        // semantic version
        return preg_match('/^(>?|>=?|<?|<=?|==?|!=?)' .
            '([a-zA-Z]{0,10})' .
            '(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)' .
            '(?:-((?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*)' .
            '(?:\.(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*))*))?' .
            '(?:\+([0-9a-zA-Z-]+(?:\.[0-9a-zA-Z-]+)*))?$/',
            $pattern);
    }

    /**
     * Returns indicator for offset reference pattern.
     *
     * @param string $pattern Reference pattern.
     * @return bool Indicator.
     */
    public static function isOffsetReferencePattern(string $pattern): bool
    {
        // absolute sign
        // prefix
        // semantic version
        return preg_match('/^(==)' .
            '([a-zA-Z]{0,10})' .
            '(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)' .
            '(?:-((?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*)' .
            '(?:\.(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*))*))?' .
            '(?:\+([0-9a-zA-Z-]+(?:\.[0-9a-zA-Z-]+)*))?$/',
            $pattern);
    }

    /**
     * Returns indicator for version matched pattern.
     *
     * @param array $version Inflated semantic version.
     * @param array $pattern Inflated reference pattern.
     * @return bool Indicator.
     */
    public static function isMatch(array $version, array $pattern): bool
    {
        return match($pattern["sign"]) {
            ">" => self::isGreater($version, $pattern),
            ">=" => self::isGreater($version, $pattern) || self::isEqual($version, $pattern),
            "<" => self::isSmaller($version, $pattern),
            "<=" => self::isSmaller($version, $pattern) || self::isEqual($version, $pattern),
            "!=" => !self::isEqual($version, $pattern),
            "==" => self::isEqual($version, $pattern),

            // default
            // greater than or equal to and
            // non-breaking changes - smaller than
            // like for example >=1.0.0 && <2.0.0
            default => self::isUnsigned($version, $pattern)
        };
    }

    /**
     * Returns indicator for version is greater than or equal to pattern
     * inside non-braking (major) part.
     *
     * @param array $version Inflated version.
     * @param array $pattern Inflated pattern.
     * @return bool Indicator.
     */
    public static function isUnsigned(array $version, array $pattern): bool
    {
        // empty pattern build
        // do not check build = false
        if ($version["release"] && !$pattern["release"] ||
            $version["major"] != $pattern["major"])
            return false;

        if ($version["minor"] < $pattern["minor"])
            return false;

        if ($version["minor"] == $pattern["minor"]) {
            if ($version["patch"] < $pattern["patch"])
                return false;

            if ($version["patch"] == $pattern["patch"]) {

                // empty build is greater
                if ($version["release"] && $version["release"] < $pattern["release"])
                    return false;

                if ($version["release"] == $pattern["release"] &&
                    $version["build"] < $pattern["build"])
                    return false;
            }
        }

        return true;
    }

    /**
     * Returns indicator for version is greater than pattern.
     *
     * @param array $version Inflated version.
     * @param array $pattern Inflated pattern.
     * @return bool Indicator.
     */
    public static function isGreater(array $version, array $pattern): bool
    {
        // empty pattern build
        // do not check build = false
        if ($version["release"] && !$pattern["release"] ||
            $version["major"] < $pattern["major"])
            return false;

        if ($version["major"] == $pattern["major"]) {
            if ($version["minor"] < $pattern["minor"])
                return false;

            if ($version["minor"] == $pattern["minor"]) {
                if ($version["patch"] < $pattern["patch"])
                    return false;

                if ($version["patch"] == $pattern["patch"]) {

                    // empty build is greater
                    if ($version["release"] && $version["release"] < $pattern["release"])
                        return false;

                    if ($version["release"] == $pattern["release"] &&
                        $version["build"] <= $pattern["build"])
                        return false;
                }
            }
        }

        return true;
    }

    /**
     * Returns indicator for version is smaller than pattern.
     *
     * @param array $version Inflated version.
     * @param array $pattern Inflated pattern.
     * @return bool Indicator.
     */
    public static function isSmaller(array $version, array $pattern): bool
    {
        // empty pattern build
        // do not check build = false
        if ($version["release"] && !$pattern["release"] ||
            $version["major"] > $pattern["major"])
            return false;

        if ($version["major"] == $pattern["major"]) {
            if ($version["minor"] > $pattern["minor"])
                return false;

            if ($version["minor"] == $pattern["minor"]) {
                if ($version["patch"] > $pattern["patch"])
                    return false;

                if ($version["patch"] == $pattern["patch"]) {

                    // empty build is bigger = false
                    if (!$version["release"] && $pattern["release"] ||
                        $version["release"] > $pattern["release"])
                        return false;

                    if ($version["build"] >= $pattern["build"])
                        return false;
                }
            }
        }

        return true;
    }

    /**
     * Returns indicator for version is equal pattern.
     *
     * @param array $version Inflated version.
     * @param array $pattern Inflated pattern.
     * @return bool Indicator.
     */
    public static function isEqual(array $version, array $pattern): bool
    {
        foreach ($version as $key => $value)
            if ($pattern[$key] != $value)
                return false;

        return true;
    }
}