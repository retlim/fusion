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
use Valvoid\Fusion\Hub\Cache;
use Valvoid\Fusion\Log\Events\Errors\Error;
use Valvoid\Fusion\Log\Events\Errors\Request as RequestError;

/**
 * Local archive synchronization request.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Archive extends Local
{
    /**
     * Constructs the request.
     *
     * @param int $id Request ID.
     * @param Cache $cache Hub cache.
     * @param array $source Structure source.
     * @param LocalApi $api API.
     */
    public function __construct(int $id, Cache $cache, array $source, LocalApi $api)
    {
        parent::__construct($id, $cache, $source, $api);

        $this->cache->lockFile($this->source, "/archive.zip", $id);
    }

    /**
     * Executes the request.
     *
     * @throws RequestError Invalid request exception.
     * @throws Error Internal error.
     */
    public function execute(): void
    {
        $dir = $this->cache->getLocalDir($this->source);
        $reference = $this->source["reference"];

        // add prefix
        if (!$this->cache->isOffset($this->source))
            $reference = $this->source["prefix"] . $reference;

        $response = $this->api->createArchive(
            $this->source["path"],
            $reference,
            $dir
        );

        if (is_string($response))
            $this->throwError($response);

        if ($response->getFile() != "$dir/archive.zip" ||
            !file_exists("$dir/archive.zip"))
            throw new Error(
                "Can't write the file \"$dir/archive.zip\"."
            );

        $this->cache->unlockFile($this->source, "/archive.zip");
    }
}