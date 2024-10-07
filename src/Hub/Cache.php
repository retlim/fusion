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

namespace Valvoid\Fusion\Hub;

use Valvoid\Fusion\Dir\Dir;
use Valvoid\Fusion\Hub\APIs\Local\Local;
use Valvoid\Fusion\Hub\APIs\Remote\Remote;
use Valvoid\Fusion\Log\Events\Errors\Error;
use Valvoid\Fusion\Util\Reference\Normalizer;
use Valvoid\Fusion\Util\Version\Interpreter as VersionInterpreter;
use Valvoid\Fusion\Util\Version\Parser;

/**
 * Hub cache.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Cache
{
    /** @var array Versions. */
    private array $versions = [];

    /** @var array Metadata, lock, and archive files. */
    private array $files = [];

    /** @var string Projects current working directory. */
    private string $root;

    /**
     * Constructs the cache.
     *
     * @param string $root Projects current working directory.
     * @throws Error Internal error.
     */
    public function __construct(string $root)
    {
        $this->root = $root;
        $cache = dirname(__DIR__, 2) . "/cache/hub";

        if (is_dir($cache))
            $this->normalizeFiles($cache);
    }

    /**
     * Normalizes cache files.
     *
     * @param string $dir Current directory.
     * @throws Error Internal error.
     */
    private function normalizeFiles(string $dir): void
    {
        $filenames = scandir($dir, SCANDIR_SORT_NONE);

        // more than only dots
        if (!isset($filenames[2]))
            Dir::delete($dir);

        else foreach ($filenames as $filename)
            if ($filename != '.' && $filename != "..") {
                $file = "$dir/$filename";

                if (is_dir($file))
                    $this->normalizeFiles($file);

                // unknown ballast or
                // expired - older than x days
                elseif (($filename != "fusion.json" && $filename != "snapshot.json" &&
                    $filename != "archive.zip") ||
                    (time() - filemtime($file)) > 86400 * 7)
                    Dir::delete($file);
            }
    }

    /**
     * Returns references synchronization state. True for done.
     * False for nothing. Integer (ID) for current running request.
     *
     * @param array $source Source.
     * @return bool|int State.
     */
    public function getReferencesState(array $source): bool|int
    {
        // active sync request ID or
        // all done
        return $this->versions[$source["api"]][$source["path"]]["state"]["references"] ??

            // create
            // synchronize
            false;
    }

    /**
     * Locks references.
     *
     * @param array $source Source.
     * @param int $id Sync request ID.
     */
    public function lockReferences(array $source, int $id): void
    {
        $this->versions[$source["api"]][$source["path"]]["state"]["references"] =

            // running
            $id;
    }

    /**
     * Unlocks references.
     *
     * @param array $source Source.
     */
    public function unlockReferences(array $source): void
    {
        $this->versions[$source["api"]][$source["path"]]["state"]["references"] =

            // all done
            true;
    }

    /**
     * Returns references synchronization state. True for done.
     * False for nothing. Integer (ID) for current running request.
     *
     * @param array $source Source.
     * @param string $version Pseudo inline version.
     * @param string $offset Offset.
     * @return bool|int State.
     */
    public function getOffsetState(array $source, string $version,
                                   string $offset): bool|int
    {
        $offset = $this->versions[$source["api"]][$source["path"]]["state"][$offset] ??
            null;

        if ($offset && $offset["version"] == $version)

            // active sync request ID or
            // all done
            return $offset["request"];

        // synchronize or
        // provoke multi pseudo version conflict
        return false;
    }

    /**
     * Returns indicator for offset source.
     *
     * @param array $source Source.
     * @return bool Indicator.
     */
    public function isOffset(array $source): bool
    {
        return isset($this->versions[$source["api"]][$source["path"]]["state"][$source["reference"]]);
    }

    /**
     * Locks offset.
     *
     * @param array $source Source.
     * @param string $offset Real "version" reference.
     * @param int $id Sync request ID.
     */
    public function lockOffset(array $source, string $version, string $offset, int $id): bool
    {
        $api = $source["api"];
        $path = $source["path"];

        // multi pseudo version conflict
        if (isset($this->versions[$api][$path]["state"][$offset]))
            return false;

        $this->versions[$api][$path]["state"][$offset] = [
            "request" => $id,
            "version" => $version,
            "type" => "unknown"
        ];

        return true;
    }

    /**
     * Returns file synchronization state. True for done.
     * False for nothing. Integer (ID) for current running request.
     *
     * @param array $source Source.
     * @param string $filename Filename (leading slash).
     * @return bool|int State.
     * @throws Error Internal error.
     */
    public function getFileState(array $source, string $filename, Remote|Local $api): bool|int
    {
        $dir = ($api instanceof Remote) ?
            $this->getRemoteDir($source) :
            $this->getLocalDir($source);

        $api = $source["api"];
        $path = $source["path"];
        $reference = $source["reference"];

        // wait for current sync request ID
        if (isset($this->files[$api][$path][$reference][$filename]))
            return $this->files[$api][$path][$reference][$filename];

        $file = $dir . $filename;

        if (file_exists($file)) {
            $type = $this->versions[$api][$path]["state"][$reference]["type"] ??
                null;

            // dynamic branch offset
            // override each time then else
            // recycle
            if ("branch" != $type)
                return true;

            Dir::delete(dirname($file));
        }

        // download
        return false;
    }

    /**
     * Adds version.
     *
     * @param string $api API.
     * @param string $path Path.
     * @return bool Offset conflict indicator.
     */
    public function addVersion(string $api, string $path, string $inline): bool
    {
        // offset conflict
        if (isset($this->versions[$api][$path]["entries"][$inline]))
            return false;

        // inline keys are for:
        // SAT solver, URl requests, ...
        $this->versions[$api][$path]["entries"][$inline] =

            // recycle parser logic
            // inflated values are for:
            // sort, pattern selections (source reference), ...
            Parser::getInflatedVersion($inline);

        return true;
    }

    /**
     * Adds offset version.
     *
     * @param array $source Source.
     * @param string $inline Inline offset version.
     * @param array $inflated Inflated offset version.
     * @return bool Version conflict indicator.
     */
    public function addOffset(array $source, string $inline, array $inflated, string $id): bool
    {
        $api = $source["api"];
        $path = $source["path"];

        // version conflict
        if (isset($this->versions[$api][$path]["entries"][$inline]))
            return false;

        $offset = $inflated["offset"];

        // equal long version or
        // starts with short
        if (str_starts_with($id, $offset))
            $type = "commit";

        elseif (VersionInterpreter::isSemanticVersion(
            substr($offset,
                strlen($source["prefix"]))))
            $type = "tag";

        else
            $type = "branch";

        $offset = &$this->versions[$api][$path]["state"][$offset];

        // synchronized
        // dynamic or static content
        $offset["request"] = true;
        $offset["type"] = $type;

        $this->versions[$api][$path]["entries"][$inline] =

            // already inflated by source parser
            $inflated;

        return true;
    }

    /**
     * Locks metadata, lock, or archive file.
     *
     * @param int $id Request ID.
     * @param string $filename Filename.
     * @param array $source Source.
     */
    public function lockFile(array $source, string $filename, int $id): void
    {
        $this->files[$source["api"]][$source["path"]][$source["reference"]][$filename] =

            // running
            $id;
    }

    /**
     * Locks metadata, lock, or archive file.
     *
     * @param string $filename Filename.
     * @param array $source Source.
     */
    public function unlockFile(array $source, string $filename): void
    {
        $api = $source["api"];
        $path = $source["path"];
        $reference = $source["reference"];

        unset($this->files[$api][$path][$reference][$filename]);

        // clear
        if (!$this->files[$api][$path][$reference])
            unset($this->files[$api][$path][$reference]);

        // clear
        if (!$this->files[$api][$path])
            unset($this->files[$api][$path]);

        // clear
        if (!$this->files[$api])
            unset($this->files[$api]);
    }

    /**
     * Returns metadata, lock, or archive file directory.
     *
     * @param array $source Source.
     * @return string Directory.
     * @throws Error Internal error.
     */
    public function getRemoteDir(array $source): string
    {
        $dir = dirname(__DIR__, 2) . "/cache/hub/" .
            $source["api"] . $source["path"] . "/" .
            $source["reference"];

        Dir::createDir($dir);

        return $dir;
    }

    /**
     * Returns canonical metadata, lock, or archive file directory.
     *
     * @param array $source Source.
     * @return string Directory.
     * @throws Error Internal error.
     */
    public function getLocalDir(array $source): string
    {
        // clear symbolic "./.."
        $path = str_replace("'", "", $source["path"]);
        $dir = realpath($this->root . $path);

        if ($dir === false)
            throw new Error(
                "Can't get realpath of the path \"" .
                $source["path"] . "\"."
            );

        if ($dir[0] !== DIRECTORY_SEPARATOR)
            $dir = "/$dir";

        $dir = dirname(__DIR__, 2) . "/cache/hub$dir/" .
            $source["reference"];

        Dir::createDir($dir);

        return $dir;
    }

    /**
     * Returns versions or null if no match.
     *
     * @param string $api API.
     * @param string $path Path.
     * @param array $reference Reference.
     * @return array Optional inline versions.
     */
    public function getVersions(string $api, string $path, array $reference): array
    {
        $versions = $this->versions[$api][$path]["entries"] ?? [];
        $versions = Normalizer::getFilteredVersions($versions, $reference);
        $match = [];

        // sort descending order
        // by inflated values
        uasort($versions,
            function (array $a, array $b) {
                return VersionInterpreter::isBiggerThan(

                    // ($a, $b) switch params for ascending
                    $b, $a);
            });

        // references
        // offset or version
        foreach ($versions as $inline => $inflated)
            $match[] = isset($inflated["offset"]) ?
                $inline . ":" . $inflated["offset"] :
                $inline;

        return $match;
    }
}