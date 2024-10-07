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
 * Exception to handle runtime source error.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Lifecycle extends Exception implements Event
{
    /** @var array Root - leaf source or directory path. */
    private array $path;

    /** @var string Absolute file. */
    private string $layer;

    /** @var array Path inside metadata. */
    private array $breadcrumb;

    /**
     * Constructs the lifecycle error.
     *
     * @param string $message Message.
     * @param string $layer File.
     * @param array $breadcrumb Path inside metadata.
     * @param array $path Path.
     */
    public function __construct(string $message, string $layer, array $breadcrumb,
                                array $path = [])
    {
        parent::__construct($message);

        $this->path = $path;
        $this->breadcrumb = $breadcrumb;
        $this->layer = $layer;
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
     * Returns index.
     *
     * @return array Index path.
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