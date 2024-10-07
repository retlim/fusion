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
use Valvoid\Fusion\Log\Events\Errors\Request;

/**
 * Local file synchronization request.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class File extends Local
{
    /** @var string Cache file. */
    private string $file;

    /** @var string Path. */
    private string $path;

    /** @var string Filename. */
    private string $filename;

    /**
     * Constructs the request.
     *
     * @param int $id Request ID.
     * @param Cache $cache Hub cache.
     * @param array $source Structure source.
     * @param string $filename Filename.
     * @param LocalApi $api API.
     * @throws Error Internal error.
     */
    public function __construct(int $id, Cache $cache, array $source, string $path,
                                string $filename, LocalApi $api)
    {
        parent::__construct($id, $cache, $source, $api);

        // non-nested [api]/[path]/[reference]/fusion.json and
        // [api]/[path]/[reference]/snapshot.json
        // cache structure like registry
        $this->file = $cache->getLocalDir($source) . $filename;
        $this->filename = $filename;
        $this->path = $path;

        $cache->lockFile($source, $filename, $id);
    }

    /**
     * Executes the request.
     *
     * @throws Request Invalid request exception.
     * @throws Error Internal error.
     */
    public function execute(): void
    {
        $reference = $this->source["reference"];

        // add prefix
        if (!$this->cache->isOffset($this->source))
            $reference = $this->source["prefix"] . $reference;

        $response = $this->api->getFileContent(
            $this->source["path"],
            $reference,

            // fusion.json or nested snapshot.json
            $this->path . $this->filename
        );

        if (is_string($response))
            $this->throwError($response);

        if (file_put_contents($this->file, $response->getContent()) === false)
            throw new Error(
                "Can't write the file \"$this->file\"."
            );

        $this->cache->unlockFile($this->source, $this->filename);
    }
}