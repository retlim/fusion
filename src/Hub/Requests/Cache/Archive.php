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
use Valvoid\Fusion\Hub\APIs\Remote\Remote;
use Valvoid\Fusion\Hub\Responses\Cache\Archive as ArchiveResponse;
use Valvoid\Fusion\Log\Events\Errors\Error;

/**
 * Cache archive request.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Archive extends Cache
{
    /**
     * @param Closure $callback Receiver.
     * Responses to receiver.
     *
     * @throws Error Internal error.
     */
    public function response(Closure $callback): void
    {
        $callback(new ArchiveResponse($this->id,
            ($this->api instanceof Remote) ?
                $this->cache->getRemoteDir($this->source) :
                $this->cache->getLocalDir($this->source)
        ));
    }
}