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
use Valvoid\Fusion\Dir\Dir;
use Valvoid\Fusion\Hub\APIs\Local\Local as LocalApi;
use Valvoid\Fusion\Hub\APIs\Local\Offset as LocalOffsetApi;
use Valvoid\Fusion\Hub\APIs\Remote\Offset as RemoteOffsetApi;
use Valvoid\Fusion\Hub\APIs\Remote\Remote as RemoteApi;
use Valvoid\Fusion\Hub\Cache as HubCache;
use Valvoid\Fusion\Hub\Responses\Cache\Versions as VersionsResponse;
use Valvoid\Fusion\Log\Events\Errors\Request as RequestError;

/**
 * Versions request.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Versions extends Cache
{
    /** @var array Offsets. */
    private array $offsets;

    /**
     * Constructs the request.
     *
     * @param int $id Request ID.
     * @param HubCache $cache Hub cache.
     * @param array $source Structure source.
     * @param LocalApi|RemoteApi $api API.
     * @param array $offsets Offsets.
     */
    public function __construct(int $id, HubCache $cache, array $source,
                                LocalApi|RemoteApi $api, array $offsets)
    {
        parent::__construct($id, $cache, $source, $api);

        $this->offsets = $offsets;
    }

    /**
     * Responses to receiver.
     *
     * @param Closure $callback Receiver.
     * @throws RequestError Invalid request exception.
     */
    public function response(Closure $callback): void
    {
        $path = $this->source["path"];
        $versions = $this->cache->getVersions($this->source["api"], $path,
            $this->source["reference"]);

        // synchronized but no match
        if (!$versions) {
            $sources = [];

            if ($this->api instanceof RemoteApi) {
                if ($this->api instanceof RemoteOffsetApi)
                    foreach ($this->offsets as $inline => $inflated)
                        $sources[] = $this->api->getOffsetUrl($path, $inline);

                $sources[] = $this->api->getReferencesUrl($path);

            } else {

                // local paths are relative to projects parent dir
                $source = Dir::getRootDir();
                $source = dirname($source);

                if ($this->api instanceof LocalOffsetApi)
                    foreach ($this->offsets as $inline => $inflated)
                        $sources[] = "$source$path | $inline";

                $sources[] = $source . $path;
            }

            $this->throwError(
                "The source reference does not match any version.",
                $sources
            );
        }

        $callback(new VersionsResponse(
            $this->id,
            $versions
        ));
    }
}