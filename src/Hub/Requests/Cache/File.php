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
use Valvoid\Fusion\Hub\Responses\Cache\Snapshot;
use Valvoid\Fusion\Hub\Responses\Cache\Metadata;
use Valvoid\Fusion\Log\Events\Errors\Error as InternalError;

/**
 * File request.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class File extends Cache
{
    /** @var string Path. */
    private string $path;

    /** @var string Filename. */
    private string $filename;

    /**
     * Constructs the request.
     *
     * @param int $id Request ID.
     * @param HubCache $cache Hub cache.
     * @param array $source Structure source.
     * @param string $filename Filename.
    */
    public function __construct(int $id, HubCache $cache, array $source, string $path,
                                string $filename, LocalApi|RemoteApi $api)
    {
        parent::__construct($id, $cache, $source, $api);

        $this->path = $path;
        $this->filename = $filename;
    }

    /**
     * Responses to receiver.
     *
     * @param Closure $callback Receiver.
     * @throws InternalError Internal exception.
     */
    public function response(Closure $callback): void
    {
        $file = ($this->api instanceof RemoteApi) ?
            $this->cache->getRemoteDir($this->source) :
            $this->cache->getLocalDir($this->source);

        $file .= $this->filename;
        $path = $this->source["path"];
        $reference = $this->source["prefix"] . $this->source["reference"];
        $filename = $this->path . $this->filename;
        $content = file_get_contents($file);

        if ($content === false)
            throw new InternalError(
                "Can't get content from the \"$file\" file."
            );

        // remote URL or
        // local absolute file
        $file = ($this->api instanceof RemoteApi) ?
            $this->api->getFileUrl($path, $reference, $filename) :
            $this->api->getFileLocation($path, $reference, $filename);

        $callback(($this->filename == "/fusion.json") ?
            new Metadata($this->id, $file, $content) :
            new Snapshot($this->id, $file, $content));
    }
}