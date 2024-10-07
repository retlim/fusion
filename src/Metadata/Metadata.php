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

use Valvoid\Fusion\Dir\Dir;
use Valvoid\Fusion\Log\Events\Errors\Lifecycle;
use Valvoid\Fusion\Log\Log;

/**
 * Metadata.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
abstract class Metadata
{
    /** @var array Parsed layers. */
    protected array $layers;

    /** @var array Merged layers. */
    protected array $content = [];

    /** @var array{
     *     state: string,
     *     root: string,
     *     file: string
     * } Lifecycle.
     */
    protected array $lifecycle = [
        "state" => null,
        "root" => null,
        "file" => null
    ];

    /**
     * Constructs the metadata.
     *
     * @param array $layers Layers.
     * @param array $content Content.
     */
    public function __construct(array $layers, array $content)
    {
        $this->layers = $layers;
        $this->content = $content;
    }

    /**
     * Returns structure sub meta.
     *
     * @return array Structure.
     */
    public function getStructure(): array
    {
        return $this->content["structure"];
    }

    /**
     * Returns structure source category sub meta.
     *
     * @return array<string, string[]> Structure source.
     */
    public function getStructureSources(): array
    {
        return $this->content["structure"]["sources"];
    }

    /**
     * Returns structure cache.
     *
     * @return string Structure cache.
     */
    public function getStructureCache(): string
    {
        return $this->content["structure"]["cache"];
    }

    /**
     * Returns structure namespaces.
     *
     * @return array<string, string> Structure loadable.
     */
    public function getStructureNamespaces(): array
    {
        return $this->content["structure"]["namespaces"];
    }

    /**
     * Returns production dependency IDs.
     *
     * @return string[] IDs.
     */
    public function getProductionIds(): array
    {
        return $this->content["dependencies"]["production"];
    }

    /**
     * Returns structure extensions category sub meta.
     *
     * @return string[] Structure extension.
     */
    public function getStructureExtensions(): array
    {
        return $this->content["structure"]["extensions"];
    }

    /**
     * Returns structure states category sub meta.
     *
     * @return string[] Structure states.
     */
    public function getStructureStates(): array
    {
        return $this->content["structure"]["states"];
    }

    /**
     * Returns ID sub meta.
     *
     * @return string ID.
     */
    public function getId(): string
    {
        return $this->content["id"];
    }

    /**
     * Returns directory sub meta.
     *
     * @return string Directory.
     */
    public function getDir(): string
    {
        return $this->content["dir"];
    }

    /**
     * Returns version sub meta.
     *
     * @return string Version.
     */
    public function getVersion(): string
    {
        return $this->content["version"];
    }

    /**
     * Returns content.
     *
     * @return array Content.
     */
    public function getContent(): array
    {
        return $this->content;
    }

    /**
     * Returns environment.
     *
     * @return array Environment.
     */
    public function getEnvironment(): array
    {
        return $this->content["environment"];
    }

    /**
     * Returns layers.
     *
     * @return array Layers.
     */
    public function getLayers(): array
    {
        return $this->layers;
    }

    /**
     * Returns lifecycle breadcrumb.
     *
     * @return array Breadcrumb.
     */
    protected function getLifecycleBreadcrumb(): array
    {
        return ["lifecycle", $this->lifecycle["state"], $this->lifecycle["file"]];
    }

    /**
     * Handles lifecycle error.
     *
     * @param int $errno
     * @param string $message Message.
     * @throws Lifecycle
     */
    abstract protected function errorHandlerCallback(int $errno, string $message): void;

    /**
     * Triggers optional lifecycle copy callback and returns
     * triggered or not indicator.
     *
     * @return bool Indicator.
     */
    public function onCopy(): bool
    {
        if (!isset($this->content["lifecycle"]["copy"]))
            return false;

        $this->lifecycle = [
            "state" => "copy",
            "root" => Dir::getStateDir() . $this->getDir(),
            "file" => $this->content["lifecycle"]["copy"]
        ];

        // break variable scope
        return $this->requireCallback();
    }

    /**
     * Requires lifecycle callback file.
     *
     * @param array $variables Variables.
     * @return bool Indicator.
     */
    protected function requireCallback(array $variables = []): bool
    {
        $callback = $this->lifecycle["root"] . $this->lifecycle["file"];

        if (!file_exists($callback))
            $this->throwLifecycleError(
                "The file \"" . $this->lifecycle["file"] . "\" does not exist."
            );

        set_error_handler($this->errorHandlerCallback(...));

        ob_start();
        extract($variables);
        unset($variables);

        $indicator = require_once $callback;

        restore_error_handler();

        Log::verbose("callback exit indicator \"$indicator\"");
        Log::debug(ob_get_clean());

        return $indicator;
    }
}