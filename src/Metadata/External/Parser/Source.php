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

namespace Valvoid\Fusion\Metadata\External\Parser;

use Valvoid\Fusion\Bus\Bus;
use Valvoid\Fusion\Bus\Events\Metadata as MetadataEvent;
use Valvoid\Fusion\Log\Events\Level;
use Valvoid\Fusion\Util\Pattern\Interpreter as PatternInterpreter;
use Valvoid\Fusion\Util\Version\Parser;

/**
 * Source parser.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Source
{
    /** @var array Inflated source. */
    private array $source;

    /** @var string Extracted package ID. */
    private string $id;

    /** @var string Inline reference. */
    private string $reference;

    /** @var array Reference prefixes. */
    private array $prefixes = [];

    /**
     * Constructs the parser.
     *
     * @param string $source Inline source.
     */
    public function __construct(string $source)
    {
        // API, nth path parts, reference
        $parts = explode('/', $source);

        if (!isset($parts[2]))
            self::throwError(
                "Invalid source schema. External source must contain " .
                "at least 3 parts: api/path/reference"
            );

        // wrapper
        $id = [];
        $this->reference = array_pop($parts);
        $this->source = [
            "api" => array_shift($parts),
            "path" => "/",

            // default
            "prefix" => "",
            "reference" => []
        ];

        $this->inflateReference($this->source["reference"]);

        // > 1 nth path parts
        // extract ID and remove ' indicators
        foreach ($parts as &$part)
            ($part[0] === "'") ?
                $part = substr($part, 1) :
                $id[] = $part;

        $this->id = implode('/', $id);
        $this->source["path"] .= implode('/', $parts);

        if ($this->prefixes) {
            $prefixes = array_unique($this->prefixes);

            if (isset($prefixes[1]))
                self::throwError(
                    "The reference part contains different prefixes."
                );

            $this->source["prefix"] = $prefixes[0];
        }
    }

    /**
     * Returns inflated source.
     *
     * @return array Source.
     */
    public function getSource(): array
    {
        return $this->source;
    }

    /**
     * Returns package ID.
     *
     * @return string ID.
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Inflates reference.
     *
     * @param array $inflated Inflated reference.
     * @param int $i Inline char pointer.
     */
    private function inflateReference(array &$inflated, int &$i = 0): void
    {
        // fallback empty reference
        $reference = "";

        for (; $i < strlen($this->reference); ++$i)
            switch ($this->reference[$i]) {
                case '(':
                    $reference = trim($reference);

                    if ($reference)
                        $inflated[] = self::getInflatedPattern($reference);

                    $nested = [];

                    ++$i;
                    self::inflateReference($nested,$i);

                    // nested condition
                    $inflated[] = $nested;

                    break;

                case '|':
                case '&':

                    // trailing logical && and || or
                    if ((($this->reference[$i - 1] ?? null) !== $this->reference[$i])) {

                        // prevent empty get reference
                        // nested was added
                        if (isset($nested))
                            unset($nested);

                        $reference = trim($reference);

                        if ($reference)
                            $inflated[] = self::getInflatedPattern($reference);

                        $inflated[] = $this->reference[$i] . $this->reference[$i];
                        $reference = "";
                    }

                    break;

                case ')': break 2;
                default:
                    $reference .= $this->reference[$i];
            }

        $reference = trim($reference);

        if ($reference)
            $inflated[] = self::getInflatedPattern($reference);
    }

    /**
     * Returns absolute or inflated semantic reference.
     *
     * @param string $pattern Inline reference.
     * @return array Reference.
     */
    private function getInflatedPattern(string $pattern): array
    {
        $pattern = explode(':', $pattern, 2);

        // offset
        // absolute commit sha or
        // last branch commit sha
        if (isset($pattern[1])) {

            // validate pseudo version pattern
            if (!PatternInterpreter::isOffsetReferencePattern($pattern[0]))
                self::throwError(
                    "Invalid source reference pattern \"" .
                    implode(':', $pattern) . "\"."
                );

            // cut absolute "==" sign
            $pattern[0] = substr($pattern[0], 2);

            $this->extractPrefix($pattern[0]);

            $result = Parser::getInflatedVersion($pattern[0]);
            $result += [
                "offset" => $pattern[1],
                "sign" => "=="
            ];

        // tag, version, ...
        } else {
            $pattern = $pattern[0];

            // validate
            if (!PatternInterpreter::isReferencePattern($pattern))
                self::throwError(
                    "Invalid source reference pattern \"$pattern\"."
                );

            // extract sign
            // >=, <=, !=, ==
            if ($pattern[1] == '=') {
                $sign = $pattern[0] . $pattern[1];
                $pattern = substr($pattern, 2);

            // >, <
            } elseif ($pattern[0] == '>' || $pattern[0] == '<') {
                $sign = $pattern[0];
                $pattern = substr($pattern, 1);

            // default
            // greater than or equal to and
            // non-breaking changes - smaller than
            // like for example >=1.0.0 && <2.0.0
            } else $sign = "";

            $this->extractPrefix($pattern);

            $result = Parser::getInflatedVersion($pattern);
            $result["sign"] = $sign;
        }

        return $result;
    }

    /**
     * Extracts prefix.
     *
     * @param string $pattern Pattern.
     */
    private function extractPrefix(string &$pattern): void
    {
        // loop until major version
        for ($i = 0; $i < strlen($pattern); $i++)
            if (is_numeric($pattern[$i]))
                break;

        // has prefix
        if ($i) {
            $this->prefixes[] = substr($pattern, 0, $i);
            $pattern = substr($pattern, $i);

        } else
            $this->prefixes[] = "";
    }

    /**
     * Throws error.
     *
     * @param string $message Message.
     */
    private static function throwError(string $message): void
    {
        Bus::broadcast(new MetadataEvent(
            $message,
            Level::ERROR,

            // __construct -> $source param
            ["\$source"]
        ));
    }
}