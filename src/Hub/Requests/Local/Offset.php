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

use Valvoid\Fusion\Hub\APIs\Local\Local as LocalApi;
use Valvoid\Fusion\Hub\APIs\Local\Offset as LocalOffsetApi;
use Valvoid\Fusion\Hub\Cache;
use Valvoid\Fusion\Log\Events\Errors\Request as RequestError;

/**
 * Local offset synchronization request.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Offset extends Local
{
    /** @var LocalOffsetApi */
    protected LocalApi $api;

    /** @var string Inline. */
    private string $inline;

    /** @var array Inflated. */
    private array $inflated;

    /**
     * Constructs the request.
     *
     * @param int $id Request ID.
     * @param Cache $cache Hub cache.
     * @param array $source Structure source.
     * @param LocalOffsetApi $api API.
     * @throws RequestError Request exception.
     */
    public function __construct(int $id, Cache $cache, array $source,
                                LocalOffsetApi $api, string $inline, array $inflated)
    {
        parent::__construct($id, $cache, $source, $api);

        if (!$this->cache->lockOffset($source, $inline, $inflated["offset"], $id))
            $this->throwError(
                "The offset ($this->inline) conflicts " .
                "with other offset. Remove it from the " .
                "source or create an other one. Offset must be a unique " .
                "non-existing pseudo version."
            );

        $this->inline = $inline;
        $this->inflated = $inflated;
    }

    /**
     * Executes the request.
     *
     * @throws RequestError Request exception.
     */
    public function execute(): void
    {
        $response = $this->api->getOffset($this->source["path"],

            // commit, tag, branch, or whatever
            $this->inflated["offset"]);

        if (is_string($response))
            $this->throwError($response);

        // override locked (unlock)
        // pseudo version conflicts with real one
        if (!$this->cache->addOffset($this->source, $this->inline,
            $this->inflated, $response->getId()))
            $this->throwError(
                "The offset ($this->inline) conflicts " .
                "with an existing version. Remove it from the " .
                "source or create an other one. Offset must be a " .
                "non-existing pseudo version."
            );
    }
}