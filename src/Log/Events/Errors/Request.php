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
 * Request error event.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Request extends Exception implements Event
{
    /**
     * @var array<array{
     *      layer: string,
     *      breadcrumb: string[],
     *      source: string
     * }>  Root-leaf source or directory path. */
    private array $path = [];

    /** @var int Unique hub request ID. */
    private int $id;

    /** @var array Absolute (inflated, normalized) request sources. */
    private array $sources;

    /**
     * Constructs the source error.
     *
     * @param int $id Unique hub request ID.
     * @param string $message Message.
     * @param array $sources Directories, files, or URLs.
     */
    public function __construct(int $id, string $message, array $sources)
    {
        parent::__construct($message);

        $this->id = $id;
        $this->sources = $sources;
    }

    /**
     * @param array $path Parent package sources.
     */
    public function setPath(array $path): void
    {
        $this->path = $path;
    }

    /**
     * Returns parents. In file at source entry.
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
     * Returns URL, file, or directory sources.
     *
     * @return array Sources.
     */
    public function getSources(): array
    {
        return $this->sources;
    }

    /**
     * Returns request ID.
     *
     * @return int ID.
     */
    public function getId(): int
    {
        return $this->id;
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

        foreach ($this->sources as $source)
            $string .= "\nby: $source" .

        $string .= "\nis: " . $this->message;

        return $string;
    }
}