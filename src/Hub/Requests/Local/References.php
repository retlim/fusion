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
use Valvoid\Fusion\Log\Events\Errors\Request as RequestError;
use Valvoid\Fusion\Util\Version\Interpreter;

/**
 * Local references synchronization request.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class References extends Local
{
    /** @var int Reference prefix length. */
    private int $prefix;

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

        $this->cache->lockReferences($source, $id);

        $this->prefix = strlen($source["prefix"]);
    }

    /**
     * Executes the request.
     *
     * @throws RequestError Invalid request exception.
     */
    public function execute(): void
    {
        $api = $this->source["api"];
        $path = $this->source["path"];
        $response = $this->api->getReferences($path);

        // error message
        if (is_string($response))
            $this->throwError($response);

        // validate
        // only inflatable semantic versions
        foreach ($response->getEntries() as $entry) {
            $entry = substr($entry, $this->prefix);

            if (Interpreter::isSemanticVersion($entry))
                if (!$this->cache->addVersion($api, $path, $entry))
                    $this->throwError(
                        "The offset ($entry) conflicts " .
                        "with an existing version. Remove it from the " .
                        "source or create an other one. Offset must be a " .
                        "non-existing pseudo version."
                    );
        }

        $this->cache->unlockReferences($this->source);
    }
}