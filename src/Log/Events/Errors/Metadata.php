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
 * Exception to handle static metadata error.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Metadata extends Exception implements Event
{
    /** @var string Structure source identifier. */
    private string $source;

    /** @var array Root - leaf source or directory path. */
    private array $path = [];

    /** @var string Absolute file. */
    private string $layer;

    /** @var array Path inside metadata. */
    private array $breadcrumb;

    /** @var int Row number inside layer. */
    private int $row;

    /**
     * Constructs the metadata error.
     *
     * @param string $source Source.
     * @param string $message Message.
     * @param string $layer File.
     * @param array $breadcrumb Path inside metadata.
     * @param int $row Line.
     */
    public function __construct(string $source, string $message, string $layer,
                                array $breadcrumb = [], int $row = 0)
    {
        parent::__construct($message);

        $this->source = $source;
        $this->layer = $layer;
        $this->breadcrumb = $breadcrumb;
        $this->row = $row;
    }

    /**
     * @param array $path
     */
    public function setPath(array $path): void
    {
        $this->path = $path;
    }

    /**
     * Returns package structure source identifier.
     *
     * @return string Source.
     */
    public function getSource(): string
    {
        return $this->source;
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
     * Returns row number.
     *
     * @return int Row number.
     */
    public function getRow(): int
    {
        return $this->row;
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
            $string .= "\nin: " . $entry["layer"] .
                "\nat: " . implode(" | ", $entry["breadcrumb"]) .
                "\nas: " . $entry["source"];

        $string .= "\nin: " . $this->layer;

        // meta maybe is empty
        // no index
        if ($this->breadcrumb)
            $string .= "\nat: " . implode(" | ", $this->breadcrumb);

        $string .= "\nis: " . $this->message;

        return $string;
    }
}