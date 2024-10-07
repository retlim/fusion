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

namespace Valvoid\Fusion\Hub\Requests\Cache;

use Closure;
use Valvoid\Fusion\Log\Events\Errors\Request as RequestError;

/**
 * Error request.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Error extends Cache
{
    /**
     * Responses to receiver.
     *
     * @param Closure $callback Receiver.
     * @throws RequestError Request error.
     */
    public function response(Closure $callback): void
    {
        $this->throwError(
            "Unknown API \"" . $this->source["api"] . "\". Extend " .
            "the hub config or remove the source from the requests.",
            ["no source"]
        );
    }
}