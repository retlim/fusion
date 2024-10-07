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

namespace Valvoid\Fusion\Metadata;

use Valvoid\Fusion\Bus\Bus;
use Valvoid\Fusion\Bus\Events\Metadata as MetadataEvent;
use Valvoid\Fusion\Log\Events\Errors\Metadata as MetadataError;
use Valvoid\Fusion\Log\Events\Level;
use Valvoid\Fusion\Log\Log;
use Valvoid\Fusion\Metadata\Interpreter\Interpreter;
use Valvoid\Fusion\Metadata\Normalizer\Normalizer;
use Valvoid\Fusion\Metadata\Normalizer\Structure;
use Valvoid\Fusion\Metadata\Parser\Parser;

/**
 * Metadata builder.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
trait Builder
{
    /** @var array Merged data. */
    private array $content = [];

    /** @var string Current layer. */
    private string $layer = "object";

    /** @var array Layers. */
    private array $layers;

    /**
     * Constructs the builder.
     *
     * @param string $dir Recursive or nested directory (to).
     * @param string $source Internal inline source (from).
     */
    abstract public function __construct(string $dir, string $source);

    /**
     * Adds production layer.
     *
     * @param string $file File.
     * @param string $content Content.
     * @throws MetadataError Invalid metadata exception.
     */
    public function addProductionLayer(string $content, string $file): void
    {
        $content = json_decode($content, true);

        if ($content === null)
            throw new MetadataError(

                // identifier
                $this->layers["object"]["content"]["raw"]["source"],
                "Can't decode JSON content. " .
                json_last_error_msg(),
                $file
            );

        $this->addLayer("production", $content, $file);
    }

    /**
     * Normalizes individually layer.
     *
     * @param string $layer Layer.
     */
    private function normalizeIndividually(string $layer): void
    {
        // optional development
        // validate and extract ids
        if (!isset($this->layers[$layer]["content"]["parsed"]["structure"]))
            return;

        Bus::addReceiver(self::class, $this->handleBusEvent(...),
            MetadataEvent::class);

        $this->layer = $layer;
        $content = $this->layers[$layer]["content"]["parsed"];

        Structure::normalize(
            $content,
            $layer,
            $this->content["structure"]["cache"]
        );

        // extract dependencies
        foreach ($content["structure"]["sources"] as $dir => $sources)
            if ($dir)
                $this->setDependencies($sources);

        Bus::removeReceiver(self::class);
    }

    /**
     * Sets dependencies.
     *
     * @param array $sources Sources.
     */
    private function setDependencies(array $sources): void
    {
        $dependencies = &$this->layers[$this->layer]["content"]["parsed"]["dependencies"];

        foreach ($sources as $source) {
            $source = explode('/', $source);

            // remove api and reference
            array_shift($source);
            array_pop($source);

            // remove ' parts
            foreach ($source as $i => $segment)
                if ($segment[0] === "'")
                    unset($source[$i]);

            $dependencies[] = implode('/', $source);
        }
    }

    /**
     * Adds layer.
     *
     * @param string $layer Layer.
     * @param string $file File.
     * @param array $content Content.
     */
    private function addLayer(string $layer, array $content, string $file = ""): void
    {
        $this->content = [];
        $this->layer = $layer;
        $this->layers[$layer] = [
            "file" => $file,
            "content" => [
                "raw" => $content
            ]
        ];

        // bus wrapper
        // error handling
        Bus::addReceiver(self::class, $this->handleBusEvent(...),
            MetadataEvent::class);
        Interpreter::interpret($layer, $content);
        Parser::parse($content);
        Bus::removeReceiver(self::class);

        $this->layers[$layer]["content"]["parsed"] = [
            "dependencies" => [],
            ...$content
        ];
    }

    /**
     * Normalizes metadata.
     */
    private function normalize(): void
    {
        $this->layer = "all";
        $this->content = [];

        Bus::addReceiver(self::class, $this->handleBusEvent(...),
            MetadataEvent::class);

        // overlay existing
        foreach ($this->layers as $layer)
            if ($layer)
                Normalizer::overlay($this->content, $layer["content"]["parsed"]);

        unset($this->content["dependencies"]);

        Normalizer::normalize($this->content);
        Bus::removeReceiver(self::class);
    }

    /**
     * Returns raw layers.
     *
     * @return array Layers.
     */
    private function getRawLayers(): array
    {
        $layers = [];

        foreach ($this->layers as $category => $layer)
            if ($layer)
                $layers[$layer["file"] ?? $category] = $layer["content"]["raw"];

        return $layers;
    }

    /**
     * Returns metadata.
     *
     * @return Metadata Metadata.
     */
    abstract public function getMetadata(): Metadata;

    /**
     * Handles bus event.
     *
     * @param MetadataEvent $event Root event.
     * @throws MetadataError Invalid metadata exception.
     */
    protected function handleBusEvent(MetadataEvent $event): void
    {
        $breadcrumb = $event->getBreadcrumb();
        $abstract = $event->getAbstract();
        $layer = "unknown layer";
        $row = 0;

        switch ($this->layer) {
            case "object":
                $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

                // reverse
                // take first match
                foreach (array_reverse($backtrace) as $entry)
                    if ($entry["class"] == self::class) {
                        $layer = $entry["file"];
                        $row = $entry["line"];
                        break;
                    }

                break;

            case "production":
            case "bot":
            case "local":
            case "development":
                $layer = $this->layers[$this->layer]["file"];
                break;

            // all
            default:
                $layer = $this->layers["production"]["file"];
        }

        $metadata = new MetadataError(

            // identifier
            $this->layers["object"]["content"]["raw"]["source"],
            $event->getMessage(),
            $layer,
            $breadcrumb,
            $row
        );

        match ($event->getLevel()) {
            Level::ERROR => throw $metadata,
            Level::WARNING => Log::warning($metadata),
            Level::NOTICE => Log::notice($metadata),
            Level::VERBOSE => Log::verbose($metadata),
            Level::INFO => Log::info($metadata)
        };
    }
}