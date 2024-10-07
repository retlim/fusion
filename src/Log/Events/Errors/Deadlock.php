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
 * Deadlock error event.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Deadlock extends Exception implements Event
{
    /**
     * @var array<array{
     *      layer: string,
     *      breadcrumb: string[],
     *      source: ?string
     * }> Locked path. Inside package tree.
     */
    private array $lockedPath;

    /** @var string Layer. */
    private string $lockedLayer;

    /** @var string[] Breadcrumb. */
    private array $lockedBreadcrumb;

    /**
     * @var array<array{
     *      layer: string,
     *      breadcrumb: string[],
     *      source: ?string
     * }> Conflict path. Potential tree entry.
     */
    private array $conflictPath;

    /** @var string Layer. */
    private string $conflictLayer;

    /** @var string[] Breadcrumb. */
    private array $conflictBreadcrumb;

    /**
     * Constructs the error.
     *
     * @param string $message Message.
     * @param array $lockedPath
     * @param array $conflictPath
     * @param string $lockedLayer
     * @param string $conflictLayer
     * @param array $lockedBreadcrumb
     * @param array $conflictBreadcrumb
     */
    public function __construct(string $message, array $lockedPath, array $conflictPath,
                                string $lockedLayer, string $conflictLayer,
                                array  $lockedBreadcrumb, array $conflictBreadcrumb)
    {
        parent::__construct($message);

        $this->lockedPath = $lockedPath;
        $this->conflictPath = $conflictPath;
        $this->lockedLayer = $lockedLayer;
        $this->conflictLayer = $conflictLayer;
        $this->lockedBreadcrumb = $lockedBreadcrumb;
        $this->conflictBreadcrumb = $conflictBreadcrumb;
    }

    /**
     * Returns built path.
     *
     * @return array<array{
     *      layer: string,
     *      breadcrumb: string[],
     *      source: ?string
     * }>  Path.
     */
    public function getLockedPath(): array
    {
        return $this->lockedPath;
    }

    /**
     * Returns layer.
     *
     * @return string Layer.
     */
    public function getLockedLayer(): string
    {
        return $this->lockedLayer;
    }

    /**
     * Returns breadcrumb.
     *
     * @return string[] Breadcrumb.
     */
    public function getLockedBreadcrumb(): array
    {
        return $this->lockedBreadcrumb;
    }

    /**
     * Returns conflict path.
     *
     * @return array<array{
     *      layer: string,
     *      breadcrumb: string[],
     *      source: ?string
     * }>  Path.
     */
    public function getConflictPath(): array
    {
        return $this->conflictPath;
    }

    /**
     * Returns layer.
     *
     * @return string Layer.
     */
    public function getConflictLayer(): string
    {
        return $this->conflictLayer;
    }

    /**
     * Returns breadcrumb.
     *
     * @return array Breadcrumb.
     */
    public function getConflictBreadcrumb(): array
    {
        return $this->conflictBreadcrumb;
    }

    /**
     * Returns exception as string.
     *
     * @return string Exception.
     */
    public function __toString(): string
    {
        $string = "";

        foreach ($this->lockedPath as $entry)
            $string .= "\nin: " . $entry["layer"] .
                "\nat: " . implode(" | ", $entry["breadcrumb"]) .
                "\nas: " . $entry["source"];

        $string .= "\nin: " . $this->lockedLayer .
            "\nat: " . implode(" | ", $this->lockedBreadcrumb) .
            "\n    ---";

        foreach ($this->conflictPath as $entry)
            $string .= "\nin: " . $entry["layer"] .
                "\nat: " . implode(" | ", $entry["breadcrumb"]) .
                "\nas: " . $entry["source"];

        $string .= "\nin: " . $this->conflictLayer .
            "\nat: " . implode(" | ", $this->conflictBreadcrumb) .
            "\nis: " . $this->message;

        return $string;
    }
}