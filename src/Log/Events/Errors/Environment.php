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

namespace Valvoid\Fusion\Log\Events\Errors;

use Exception;
use Valvoid\Fusion\Log\Events\Event;

/**
 * Environment error.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Environment extends Exception implements Event
{
    /**
     * @var array<array{
     *     layer: string,
     *     breadcrumb: string[],
     *     source: string
     * }> Meta structure | runtime, persistence config path.
     */
    private array $path;

    /** @var string Meta layer. */
    private string $layer;

    /** @var string[] Index path inside meta content. */
    private array $breadcrumb;

    /**
     * Constructs the environment error.
     *
     * @param array $breadcrumb Breadcrumb.
     * @param string $message Message.
     */
    public function __construct(string $message, array $path, string $layer, array $breadcrumb)
    {
        parent::__construct($message);

        $this->path = $path;
        $this->layer = $layer;
        $this->breadcrumb = $breadcrumb;
    }

    /**
     * Returns path.
     *
     * @return array<array{
     *     layer: string,
     *     breadcrumb: string[],
     *     source: string
     * }> Path.
     */
    public function getPath(): array
    {
        return $this->path;
    }

    /**
     * Returns layer.
     *
     * @return string Layer.
     */
    public function getLayer(): string
    {
        return $this->layer;
    }

    /**
     * Returns breadcrumb.
     *
     * @return string[] Breadcrumb.
     */
    public function getBreadcrumb(): array
    {
        return $this->breadcrumb;
    }

    /**
     * Returns exception as string.
     *
     * @return string Exception.
     */
    public function __toString(): string
    {
        $string = "";

        foreach ($this->path as $entry)
            echo "\nin: " . $entry["layer"] .
                "\nat: " . implode(" | ", $entry["breadcrumb"]) .
                "\nas: " . $entry["source"];

        $string .= "\nin: " . $this->layer .
            "\nat: " . implode(" | ", $this->breadcrumb) .
            "\nis: " . $this->message . "\n";

        return $string;
    }
}