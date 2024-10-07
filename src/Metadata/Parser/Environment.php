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

namespace Valvoid\Fusion\Metadata\Parser;

use Valvoid\Fusion\Bus\Bus;
use Valvoid\Fusion\Bus\Events\Metadata as MetadataEvent;
use Valvoid\Fusion\Log\Events\Level;
use Valvoid\Fusion\Metadata\Interpreter\Environment as EnvironmentInterpreter;

/**
 * Environment parser.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Environment
{
    /**
     * Parses environment entry.
     *
     * @param array $environment
     */
    public static function parse(array &$environment): void
    {
        foreach ($environment as $key => &$value)
            match($key) {
                "php" => self::parsePhp($value),
                default => null
            };
    }

    /**
     * Parses php entry.
     *
     * @param array $php
     */
    private static function parsePhp(array &$php): void
    {
        foreach ($php as $key => &$value)
            match($key) {
                "version" => self::parsePhpVersion($value),
                default => null
            };
    }

    /**
     * Parses php version entry.
     *
     * @param string $version
     */
    private static function parsePhpVersion(string &$version): void
    {
        $inline = $version;
        $version = [];

        self::inflateReference($inline, $version);
    }

    /**
     * Inflates condition.
     *
     * @param array $inflated Inflated condition.
     * @param int $i Inline char pointer.
     */
    private static function inflateReference(string $inline, array &$inflated, int &$i = 0): void
    {
        $reference = "";

        for (; $i < strlen($inline); ++$i)
            switch ($inline[$i]) {
                case '(':
                    $reference = trim($reference);

                    if ($reference)
                        $inflated[] = self::getReference($reference);

                    $nested = [];

                    ++$i;
                    self::inflateReference($inline, $nested,$i);

                    // nested condition
                    $inflated[] = $nested;

                    break;

                case '|':
                case '&':

                    // trailing logical && and || or
                    if ((($inline[$i - 1] ?? null) !== $inline[$i])) {

                        // prevent empty get reference
                        // nested was added
                        if (isset($nested))
                            unset($nested);

                        $reference = trim($reference);

                        if ($reference)
                            $inflated[] = self::getReference($reference);

                        $inflated[] = $inline[$i] . $inline[$i];
                        $reference = "";
                    }

                    break;

                case ')': break 2;
                default:
                    $reference .= $inline[$i];
            }

        $reference = trim($reference);

        if ($reference)
            $inflated[] = self::getReference($reference);
    }

    /**
     * Returns absolute or inflated semantic reference.
     *
     * @param string $reference Inline reference.
     * @return array Reference.
     */
    private static function getReference(string $reference): array
    {

        if (!EnvironmentInterpreter::isSemanticVersionCorePattern($reference))
            Bus::broadcast(new MetadataEvent(
                "The value of the \"version\" index must be a " .
                "core (major.minor.patch) semantic version pattern logic.",
                Level::ERROR,
                ["environment", "php", "version"]
            ));

        if (!is_numeric($reference[0])) {
            $sign = $reference[0];

            if ($reference[1] == '=')
                $sign .= $reference[1];

            $reference = ltrim($reference,  $sign);
        }

        $version = explode('.', $reference, 3);

        return [
            "major" => $version[0],
            "minor" => $version[1],
            "patch" => $version[2],

            // future?
            "build" => "",
            "release" => "",
            "sign" => $sign ?? ""
        ];
    }
}