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

namespace Valvoid\Fusion\Hub\Requests\Local;

use Valvoid\Fusion\Dir\Dir;
use Valvoid\Fusion\Hub\APIs\Local\Local as LocalApi;
use Valvoid\Fusion\Hub\Cache;
use Valvoid\Fusion\Hub\Requests\Request;
use Valvoid\Fusion\Log\Events\Errors\Request as RequestError;

/**
 * Local synchronization request.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
abstract class Local extends Request
{
    /** @var int[] Cache request ID. */
    protected array $cacheIds;

    /** @var LocalApi API */
    protected LocalApi $api;

    /**
     * Constructs the remote request.
     *
     * @param Cache $cache Hub cache.
     * @param int $id Request ID.
     * @param array $source Structure source.
     */
    public function __construct(int $id, Cache $cache, array $source, LocalApi $api)
    {
        parent::__construct($id, $cache, $source);

        $this->api = $api;
    }

    /**
     * Adds cache request ID that wait for sync done.
     *
     * @param int $id ID.
     */
    public function addCacheId(int $id): void
    {
        $this->cacheIds[$id] = time();
    }

    /**
     * Returns cache IDs.
     *
     * @return int[] IDs.
     */
    public function getCacheIds(): array
    {
        return array_keys($this->cacheIds);
    }

    /**
     * Executes request.
     */
    public abstract function execute(): void;

    /**
     * Throws request error.
     *
     * @param string $message Error message.
     * @throws RequestError Invalid request exception.
     */
    protected function throwError(string $message): void
    {
        // local paths are relative to projects parent dir
        // show absolute for better debug
        $source = Dir::getRootDir();
        $source = dirname($source);
        $source .= $this->source["path"];

        throw new RequestError(
            array_key_first($this->cacheIds),
            $message,
            [$source]
        );
    }
}