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
use Valvoid\Fusion\Hub\APIs\Local\Local as LocalApi;
use Valvoid\Fusion\Hub\APIs\Remote\Remote as RemoteApi;
use Valvoid\Fusion\Hub\Cache as HubCache;
use Valvoid\Fusion\Hub\Requests\Request;
use Valvoid\Fusion\Log\Events\Errors\Request as RequestError;

/**
 * Cache request.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
abstract class Cache extends Request
{
    /** @var LocalApi|RemoteApi|null API. */
    protected LocalApi|RemoteApi|null $api;

    /** @var array<int, int> Sync request IDs. */
    protected array $syncIds;

    /**
     * Constructs the request.
     *
     * @param int $id Request ID.
     * @param HubCache $cache Hub cache.
     * @param array $source Structure source.
     */
    public function __construct(int $id, HubCache $cache, array $source,
                                LocalApi|RemoteApi|null $api)
    {
        parent::__construct($id, $cache, $source);

        $this->api = $api;
    }

    /**
     * Adds local|remote sync ID.
     *
     * @param int $id ID.
     */
    public function addSyncId(int $id): void
    {
        $this->syncIds[$id] = time();
    }

    /**
     * Removes local|remote sync request ID.
     *
     * @param int $id ID.
     */
    public function removeSyncId(int $id): void
    {
        unset($this->syncIds[$id]);
    }

    /**
     * Returns indicator for complete or not sync.
     *
     * @return bool Indicator.
     */
    public function hasSyncIds(): bool
    {
        return !empty($this->syncIds);
    }

    /**
     * Responses to receiver.
     *
     * @param Closure $callback Callback.
     */
    public abstract function response(Closure $callback): void;

    /**
     * Throws request error.
     *
     * @param string $message Error message.
     * @param string[] $sources URL or dir sources.
     * @throws RequestError Invalid request exception.
     */
    protected function throwError(string $message, array $sources): void
    {
        throw new RequestError(
            $this->id,
            $message,
            $sources
        );
    }
}