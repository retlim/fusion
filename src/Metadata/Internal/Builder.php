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

namespace Valvoid\Fusion\Metadata\Internal;

use Valvoid\Fusion\Bus\Bus;
use Valvoid\Fusion\Bus\Events\Metadata as MetadataEvent;
use Valvoid\Fusion\Log\Events\Level;
use Valvoid\Fusion\Metadata\Builder as MetadataBuilder;

/**
 * Internal metadata builder.
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
     * @param string $source Internal inline source (from).
     */
    public function __construct(string $dir, string $source)
    {
        // reverse overlay order
        // object - required
        // fusion.bot.php
        // fusion.local.php
        // fusion.dev.php
        // fusion.json - required
        $this->layers = [
            "production" => null,
            "development" => null,
            "local" => null,
            "bot" => null,

            // no intersection with other layers
            "object" => [
                "content" => [

                    // static normalized
                    // raw == parsed
                    "raw" => [
                        "dir" => $dir,
                        "source" => $source
                    ],
                    "parsed" => [
                        "dir" => $dir,
                        "source" => $source
                    ]
                ]
            ]
        ];
    }

    /**
     * Adds bot layer.
     *
     * @param string $file File.
     * @param array $content Content.
     */
    public function addBotLayer(array $content, string $file): void
    {
        $this->addLayer("bot", $content, $file);
    }

    /**
     * Adds local layer.
     *
     * @param string $file File.
     * @param array $content Content.
     */
    public function addLocalLayer(array $content, string $file): void
    {
        $this->addLayer("local", $content, $file);
    }

    /**
     * Adds development layer.
     *
     * @param string $file File.
     * @param array $content Content.
     */
    public function addDevelopmentLayer(array $content, string $file): void
    {
        $this->addLayer("development", $content, $file);
    }

    /**
     * Returns internal metadata.
     *
     * @return Internal Metadata.
     */
    public function getMetadata(): Internal
    {
        $this->content = [];

        $this->normalize();
        $this->normalizeIndividually("production");
        $this->normalizeIndividually("development");
        $this->normalizeIndividually("local");

        // required layer
        $this->content["dependencies"]["production"] =
            $this->layers["production"]["content"]["parsed"]["dependencies"];

        Bus::addReceiver(self::class, $this->handleBusEvent(...),
            MetadataEvent::class);

        // optional dev
        if (isset($this->layers["development"])) {
            $this->layer = "development";
            $this->content["dependencies"]["development"] =
                $this->layers["development"]["content"]["parsed"]["dependencies"];

            $intersection = array_intersect(
                $this->content["dependencies"]["production"],
                $this->content["dependencies"]["development"]
            );

            if ($intersection)
                Bus::broadcast(new MetadataEvent(
                    "Nested source intersection: " .
                    implode(', ', $intersection),
                    Level::ERROR,
                    ["structure"]
                ));

        } else
            $this->content["dependencies"]["development"] = null;

        // optional local
        if (isset($this->layers["local"])) {
            $this->layer = "local";
            $this->content["dependencies"]["local"] =
                $this->layers["local"]["content"]["parsed"]["dependencies"];

            $intersection = array_intersect(
                $this->content["dependencies"]["production"],
                $this->content["dependencies"]["local"]
            );

            if ($intersection)
                Bus::broadcast(new MetadataEvent(
                    "Nested source intersection: " .
                    implode(', ', $intersection),
                    Level::ERROR,
                    ["structure"]
                ));

            if ($this->content["dependencies"]["development"]) {
                $intersection = array_intersect(
                    $this->content["dependencies"]["development"],
                    $this->content["dependencies"]["local"]
                );

                if ($intersection)
                    Bus::broadcast(new MetadataEvent(
                        "Nested source intersection: " .
                        implode(', ', $intersection),
                        Level::ERROR,
                        ["structure"]
                    ));
            }

        } else
            $this->content["dependencies"]["local"] = null;

        Bus::removeReceiver(self::class);

        return new Internal(
            $this->getRawLayers(),
            $this->content
        );
    }
}