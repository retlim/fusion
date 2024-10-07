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
 * Config error exception.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Config extends Exception implements Event
{
    /** @var string Absolute file. */
    private string $layer;

    /** @var array Path inside metadata. */
    private array $breadcrumb;

    /**
     * Constructs the config error.
     *
     * @param string $message Description.
     * @param string $layer Location.
     * @param array $breadcrumb Index path inside the config.
     */
    public function __construct(string $message, string $layer, array $breadcrumb = [])
    {
        parent::__construct($message);

        $this->layer = $layer;
        $this->breadcrumb = $breadcrumb;
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
     * Returns generic string.
     *
     * @return string String.
     */
    public function __toString(): string
    {
        return "\nin: " . $this->layer .
            "\nat: " . implode(" | ", $this->breadcrumb) .
            "\nis: " . $this->message;
    }
}