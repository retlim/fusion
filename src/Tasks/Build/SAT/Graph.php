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

namespace Valvoid\Fusion\Tasks\Build\SAT;

use Valvoid\Fusion\Tasks\Build\SAT\Clause\Clause;

/**
 * Implication graph.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Graph
{
    /** @var array<int ,array{
     *      roots: int[]|null,
     *      level: int,
     *      state: bool
     * }> Nodes.
     */
    private array $nodes;

    /** @var array{
     *      roots: int[],
     *      variable: int,
     *      level: int
     * } Conflict node.
     */
    private array $conflict;

    /**
     * Adds an arbitrary decision root node.
     *
     * @param int $variable Variable.
     * @param int $level State.
     */
    public function addRootNode(int $variable, bool $state, int $level): void
    {
        $this->nodes[$variable] = [
            "roots" => [],
            "level" => $level,
            "state" => $state
        ];
    }

    /**
     * Adds a propagated leaf node.
     *
     * @param int[] $roots Parent variables.
     * @param int $variable Variable.
     * @param bool $state State.
     * @param int $level Level.
     *
     * @return bool Conflict indicator.
     */
    public function addLeafNode(array $roots, int $variable, bool $state,
                                int $level): bool
    {
        // potential conflict
        if (isset($this->nodes[$variable])) {
            foreach ($roots as $parent)
                if (!in_array($parent, $this->nodes[$variable]["roots"]))
                    $this->nodes[$variable]["roots"][] = $parent;

            // multi state conflict
            if ($this->nodes[$variable]["state"] != $state) {
                $this->conflict = [
                    "roots" => $this->nodes[$variable]["roots"],
                    "variable" => $variable,
                    "level" => $level
                ];

                unset($this->nodes[$variable]);
                return false;
            }

        } else
            $this->nodes[$variable] = [
                "roots" => $roots,
                "level" => $level,
                "state" => $state
            ];

        return true;
    }

    /**
     * Returns conflict variable.
     *
     * @return int Variable.
     */
    public function getConflictVariable(): int
    {
        return $this->conflict["variable"];
    }

    /**
     * Returns potential conflict.
     *
     * @return null|array{
     *     clause: Clause,
     *     level: int
     * } Fallback.
     */
    public function getConflictFallback(): null|array
    {
        $level = $this->conflict["level"];

        // no fallback possible
        // already at top
        if ($level == 0)
            return null;

        // get all reversed implication paths
        // find intersections
        // take first (closest to conflict)
        $paths = $this->getLevelPaths($this->conflict["roots"], $level);
        $intersection = array_intersect(...$paths);
        $firstUIP = reset($intersection);
        $state = $this->nodes[$firstUIP]["state"];

        // create learn clause
        // no other root literal then
        // jump back to the first decision level (0)
        $clause = new Clause;
        $level = 0;

        foreach ($this->conflict["roots"] as $variable)
            if ($this->nodes[$variable]["level"] != $this->conflict["level"]) {
                $node = $this->nodes[$variable];

                // invert sign and
                // keep assigned state
                $clause->appendLiteral($variable, $node["state"]);
                $clause->setVariableState($variable, $node["state"],
                    $this->nodes[$variable]["level"]);

                // take the highest root level
                // if conflict at level 3 and
                // has roots at level 1 and 2 then
                // take 2
                if ($level < $node["level"])
                    $level = $node["level"];
            }

        // invert sign and
        // set state to unit
        $clause->appendLiteral($firstUIP, $state);
        $clause->updateState();
        $this->resetAfterLevel($level);

        return [
            "clause" => $clause,
            "level" => $level
        ];
    }

    /**
     * Drops all nodes with level bigger than.
     *
     * @param int $level Last level.
     */
    private function resetAfterLevel(int $level): void
    {
        foreach ($this->nodes as $variable => $node)
            if ($node["level"] > $level)
                unset($this->nodes[$variable]);
    }

    /**
     * Returns reversed level paths.
     *
     * @param array $roots Parent variables.
     * @param int $level Conflicts decision level.
     * @return array<int[]> Variable paths.
     */
    private function getLevelPaths(array $roots, int $level): array
    {
        $paths = [];

        foreach ($roots as $variable)
            if ($this->nodes[$variable]["level"] == $level) {

                // has sub (actually upper roots)
                $subPaths = $this->getLevelPaths($this->nodes[$variable]["roots"], $level);

                // has same level
                if ($subPaths)
                    foreach ($subPaths as $subPath)
                        $paths[] = [$variable, ...$subPath];

                else
                    $paths[] = [$variable];
            }

        return $paths;
    }

    /**
     * Returns nodes.
     *
     * @return array<int ,array{
     *      roots: null|int[],
     *      level: int,
     *      state:bool
     *  }> Nodes.
     */
    public function getNodes(): array
    {
        return $this->nodes;
    }
}