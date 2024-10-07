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

namespace Valvoid\Fusion\Tasks\Build;

use Valvoid\Fusion\Log\Events\Errors\Deadlock as DeadlockError;
use Valvoid\Fusion\Metadata\External\External;
use Valvoid\Fusion\Tasks\Group;
use Valvoid\Fusion\Util\Metadata\Structure;

/**
 * Lazy deadlock.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Deadlock
{
    /** @var string Package ID. */
    private string $id;

    /** @var string Locked version. */
    private string $locked;

    /** @var string Conflict version. */
    private string $conflict;

    /** @var array<string, array{
     *      source: string,
     *      tree: array<string, array>
     * }> Absolute metadata implication.
     */
    private array $implication;

    /** @var array<string, array<string, External>> Absolute external metas. */
    private array $metas;

    /** @var ?string Optional root source. */
    private ?string $source;

    /**
     * Constructs the deadlock.
     *
     * @param array $deadlock
     * @param array $implication
     * @param array $metas
     * @param ?string $source
     */
    public function __construct(array $deadlock, array $implication, array $metas,
                                ?string $source = null)
    {
        $this->id = $deadlock["id"];
        $this->locked = $deadlock["locked"];
        $this->conflict = $deadlock["conflict"];
        $this->implication = $implication;
        $this->metas = $metas;
        $this->source = $source;
    }

    /**
     * Throws deadlock error.
     *
     * @throws DeadlockError Deadlock exception.
     */
    public function throwError(): void
    {
        $lockedTrace = $this->getTrace($this->locked);
        $conflictTrace = $this->getTrace($this->conflict);

        throw new DeadlockError(
            "Can't resolve packages due to multiple versions " .
            "\"$this->locked\" and \"$this->conflict\" of the package \"$this->id\". " .
            "Adjust the metadata to get a common version.",
            $lockedTrace["path"],
            $conflictTrace["path"],
            $lockedTrace["file"],
            $conflictTrace["file"],
            ["version", $this->locked],
            ["version", $this->conflict]
        );
    }

    /**
     * Returns path.
     *
     * @param string $version Version.
     * @return array Path.
     */
    private function getTrace(string $version): array
    {
        $implicationPath = Trace::getVersionPath($this->implication, $this->id, $version);
        $path = [];

        // initial parent
        if ($this->source) {
            $id = array_key_first($implicationPath);
            $entry = array_shift($implicationPath);
            $metadata = $this->metas[$id][$entry["version"]];
            $path[] = [
                "layer" => "task config",
                "breadcrumb" => ["source"],
                "source" => $this->source
            ];

        } else
            $metadata = Group::getInternalRootMetadata();

        foreach ($implicationPath as $id => $entry) {
            foreach ($metadata?->getLayers() as $layer => $content)
                if (isset($content["structure"])) {
                    $breadcrumb = Structure::getBreadcrumb(
                        $content["structure"],
                        $entry["source"],
                        ["structure"]
                    );

                    if ($breadcrumb) {
                        $path[] = [
                            "layer" => $layer,
                            "breadcrumb" => $breadcrumb,
                            "source" => $entry["source"]
                        ];

                        // take first match
                        break;
                    }
                }

            // next parent
            $metadata = $this->metas[$id][$entry["version"]];
        }

        // production layer or
        // object offset overlay
        foreach ($metadata?->getLayers() as $layer => $content)
            if (isset($content["version"]))
                $file = $layer;

        return [
            "path" => $path,
            "file" => $file
        ];
    }
}