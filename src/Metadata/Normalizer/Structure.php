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
 * Metadata structure normalizer.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Structure
{
    /** @var string[] Cache category. */
    private array $cache = [];

    /** @var array<string, string[]> Source category. */
    private array $source = [];

    /** @var string[] Extension category. */
    private array $extension = [];

    /** @var array<string, string[]> Loadable category. */
    private array $loadable = [];

    /** @var string[] State category. */
    private array $state = [];

    /** @var string Current layer identifier. */
    private string $layer;

    /**
     * Constructs the normalizer.
     *
     * @param string $layer Current layer identifier.
     */
    private function __construct(string $layer)
    {
        $this->layer = $layer;
    }

    /**
     * Normalizes structure.
     *
     * @param array $meta Meta.
     * @param string $layer Current layer identifier.
     */
    public static function normalize(array &$meta, string $layer, string $cache = null): void
    {
        $structure = new self($layer);

        $structure->extractStructure($meta["structure"], "", "");

        // replace
        $meta["structure"] = [
            "cache" => "",
            "sources" => [],
            "extensions" => [],
            "namespaces" => [],
            "states" => []
        ];

        if ($structure->cache)
            Cache::normalize(
                $structure->cache,
                $meta["structure"]["cache"]
            );

        if ($structure->source)
            Source::normalize(
                $structure->source,
                $meta["structure"]["sources"]
            );

        if ($structure->extension)
            Extension::normalize(
                $structure->extension,
                $meta["structure"]["extensions"]
            );

        if ($structure->state)
            State::normalize(
                $structure->state,
                $meta["structure"]["states"]
            );

        if ($structure->loadable)
            Loadable::normalize(
                $structure->loadable,
                $cache ?? $meta["structure"]["cache"],
                $meta["structure"]["namespaces"]
            );
    }


    /**
     * Extracts structure into categories.
     *
     * @param array $structure Structure.
     * @param string $path Directory breadcrumb.
     * @param string $source Source breadcrumb.
     */
    private function extractStructure(array $structure, string $path, string $source): void
    {
        foreach ($structure as $key => $value)
            if (is_array($value))
                if (is_string($key))

                    // has directory identifier
                    // pass dir or source breadcrumb
                    ($key[0] === '/') ?
                        self::extractStructure($value, $path . $key, $source) :
                        self::extractStructure($value, $path, "$source/$key");

                // numeric seq
                // pass just value
                else
                    self::extractStructure($value, $path, $source);

            // cache dir
            // check also source due to branch name "cache"
            // cache dir has no source prefix
            elseif ($value == "cache" && !$source) {
                $entry = ($key[0] ?? null) === '/' ?
                    $path . $key :
                    $path;

                if ($this->layer == "development" || $this->layer == "local")
                    Bus::broadcast(new MetadataEvent(
                        "The \"cache\" indicator is static and belongs to " .
                        "the \"fusion.json\" file.",
                        Level::ERROR,
                        ["structure"],
                        [$entry]
                    ));

                $this->cache[] = $entry;

            // state dir
            } elseif ($value == "state" && !$source)
                $this->state[] = ($key[0] ?? null) === '/' ?
                    $path . $key :
                    $path;

            // extension dir
            elseif ($value == "extension" && !$source)
                $this->extension[] = ($key[0] ?? null) === '/' ?
                    $path . $key :
                    $path;

            // loadable
            // nested cache dir structure
            elseif (str_contains($value, '\\') && !$source)
                $this->loadable[] = [

                    // namespace => path
                    $value => ($key[0] ?? null) === '/' ?
                        $path . $key :
                        $path
                ];

            // assoc source reference
            // tag, branch, commit, etc.
            elseif (is_string($key))

                // has directory identifier
                // pass dir and/or source breadcrumb
                $this->source[] = ($key[0] === '/') ?
                    [$path . $key => "$source/$value"] :
                    [$path => "$source/$key/$value"];

            // seq source reference
            // tag, branch, commit, etc.
            else
                $this->source[] = [
                    $path => "$source/$value"
                ];
    }
}