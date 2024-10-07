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

namespace Valvoid\Fusion\Metadata\External;

use Valvoid\Fusion\Bus\Bus;
use Valvoid\Fusion\Bus\Events\Metadata as MetadataEvent;
use Valvoid\Fusion\Metadata\External\Normalizer\Reference;
use Valvoid\Fusion\Metadata\External\Parser\Source;
use Valvoid\Fusion\Metadata\Builder as MetadataBuilder;

/**
 * External metadata builder.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Builder
{
    use MetadataBuilder;

    /**
     * Constructs the builder.
     *
     * @param string $dir Recursive or nested directory (to).
     * @param string $source External inline source (from).
     */
    public function __construct(string $dir, string $source)
    {
        // reverse overlay order
        // object - required
        // fusion.json - required
        $this->layers = [
            "production" => null,

            // no intersection with other layers
            "object" => [
                "content" => [
                    "raw" => [

                        // origin pattern reference
                        "source" => $source,
                        "dir" => $dir
                    ]
                ]
            ]
        ];

        // bus wrapper
        // error handling
        Bus::addReceiver(self::class, $this->handleBusEvent(...),
            MetadataEvent::class);

        $parser = new Source($source);

        // mutable
        // pattern - normalized reference
        $this->layers["object"]["content"]["parsed"] = [
            "id" => $parser->getId(),
            "source" => $parser->getSource(),
            "dir" => $dir
        ];

        Bus::removeReceiver(self::class);
    }

    /**
     * Returns package ID.
     *
     * @return string ID.
     */
    public function getId(): string
    {
        return $this->layers["object"]["content"]["parsed"]["id"];
    }

    /**
     * Returns raw dir.
     *
     * @return string Dir.
     */
    public function getRawDir(): string
    {
        return $this->layers["object"]["content"]["raw"]["dir"];
    }

    /**
     * Adds lazy source pointer.
     *
     * @param string $reference Semantic version with optional offset.
     */
    public function normalizeReference(string $reference): void
    {
        $this->layer = "object";
        $reference = Reference::getNormalizedReference($reference);

        // fake version
        // branch|commit offset
        if (isset($reference["version"])) {
            $this->layers["object"]["content"]["parsed"]["version"] = $reference["version"];

        // reset
        } else
            unset($this->layers["object"]["content"]["parsed"]["version"]);

        // progressive
        // replace pattern
        $this->layers["object"]["content"]["parsed"]["source"]["reference"] =

            // absolute pointer
            // extracted by pattern reference
            $reference["reference"];
    }

    /**
     * Returns parsed source.
     *
     * @return array{
     *     api: string,
     *     path: string,
     *     reference: array,
     *     prefix: string
     * } Source.
     */
    public function getParsedSource(): array
    {
        return $this->layers["object"]["content"]["parsed"]["source"];
    }

    /**
     * Returns normalized source.
     *
     * @return array{
     *     api: string,
     *     path: string,
     *     reference: string,
     *     prefix: string
     * } Source.
     */
    public function getNormalizedSource(): array
    {
        return $this->layers["object"]["content"]["parsed"]["source"];
    }

    /**
     * Returns external metadata.
     *
     * @return External Metadata.
     */
    public function getMetadata(): External
    {
        $this->normalize();
        $this->normalizeIndividually("production");

        $this->content["dependencies"]["production"] =
            $this->layers["production"]["content"]["parsed"]["dependencies"];

        return new External(
            $this->getRawLayers(),
            $this->content
        );
    }
}