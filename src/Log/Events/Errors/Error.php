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
 * Error event.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Error extends Exception implements Event
{
    /**
     * Returns exception as string.
     *
     * @return string Exception.
     */
    public function __toString(): string
    {
        return "\n$this->message";
    }
}