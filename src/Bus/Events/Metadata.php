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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace Valvoid\Fusion\Bus\Events;

use Valvoid\Fusion\Log\Events\Level;

/**
 * Metadata event.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Metadata implements Event
{
    /** @var Level Level. */
    private Level $level;

    /** @var string Message. */
    private string $message;

    /** @var string[] Breadcrumb. */
    private array $breadcrumb;

    /** @var string[] Abstract. */
    private array $abstract;

    /**
     * Constructs the event.
     *
     * @param string $message Message.
     * @param Level $level Level.
     * @param array $breadcrumb Breadcrumb.
     * @param array $abstract Abstract.
     */
    public function __construct(string $message, Level $level, array $breadcrumb = [],
                                array $abstract = [])
    {
        $this->message = $message;
        $this->level = $level;
        $this->breadcrumb = $breadcrumb;
        $this->abstract = $abstract;
    }

    /**
     * Returns new root directory.
     *
     * @return string Root.
     */
    public function getMessage(): string
    {
        return $this->message;
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
     * Returns abstract.
     *
     * @return array Abstract.
     */
    public function getAbstract(): array
    {
        return $this->abstract;
    }

    /**
     * Returns level.
     *
     * @return Level Level.
     */
    public function getLevel(): Level
    {
        return $this->level;
    }
}