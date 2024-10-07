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
 * Satisfiability (SAT) solver to find a structure path.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Solver
{
    /** @var Formula CNF formula. */
    private Formula $formula;

    /** @var Graph Implication graph. */
    private Graph $graph;

    /** @var array Variables per package ID. */
    private array $variables;

    /** @var int Current unit variable counter. */
    private int $variable = 0;

    /** @var bool Satisfiability indicator. */
    private bool $satisfiable;

    /**
     * Constructs the solver.
     *
     * @param string $id Root package identifier.
     * @param string $version Root package version.
     * @param array<string, array{
     *      tree: <string, array>
     * }> $implication Structure relation.
     */
    public function __construct(string $id, string $version, array $implication)
    {
        $this->graph = new Graph;
        $this->formula = new Formula($this->graph);
        $this->variables[$id][$version] = 0;

        // root clause as it is ( A )
        // C1 = ( 0 )
        $clause = new Clause;

        // unit state
        $clause->appendLiteral(0);
        $clause->updateState();
        $this->formula->appendClause($clause);

        // implication clauses
        // if parent then one version from dependency set ( A -> B v C v ... )
        // C2 = ( !0 || 1 || 2 || ... ), C3 = ( ... ), ...
        $this->addImplicationClause(0, $implication);

        // unique clauses
        // only one dependency version ( ¬B v ¬C )
        // C4 = ( !1 || !2 ), C5 = ( !2 || !1 ), ...
        foreach ($this->variables as $variables)
            foreach ($variables as $variable)
                foreach ($variables as $var)
                    if ($variable != $var) {
                        $clause = new Clause;

                        $clause->appendLiteral($variable, true);
                        $clause->appendLiteral($var, true);
                        $this->formula->appendClause($clause);
                    }
    }

    /**
     * Adds implication clause.
     *
     * @param int $parent Version index.
     * @param array<string, array{
     *     tree: <string, array>
     * }> $implication Structure relation.
     */
    private function addImplicationClause(int $parent, array $implication): void
    {
        // clause per package ID
        foreach ($implication as $id => $entry) {
            $clause = new Clause;

            // negated parent implicates
            $clause->appendLiteral($parent, true);

            foreach ($entry["implication"] as $version => $implication) {
                $variable = $this->variables[$id][$version] ??=
                    ++$this->variable;

                // nested implication and
                // one of implicated set
                $this->addImplicationClause($variable, $implication);
                $clause->appendLiteral($variable);
            }

            $this->formula->appendClause($clause);
        }
    }

    /**
     * Returns indicator for satisfiable structure.
     *
     * @return bool Indicator.
     */
    public function isStructureSatisfiable(): bool
    {
        return $this->satisfiable ??= $this->isFormulaSatisfiable();
    }

    /**
     * Returns indicator for satisfiable formula.
     *
     * @return bool Indicator.
     */
    private function isFormulaSatisfiable(): bool
    {
        // decision level
        $level = 0;

        // propagate (0) root and linked units
        // no error handling (fallback) at top level due to
        // no back jump level there
        // unsatisfiable
        if (!$this->formula->propagateUnits($level))
            return false;

        // while has unassigned variable
        while ($this->formula->selectVariable(++$level))

            // propagate unit and
            // analysable conflict
            while (!$this->formula->propagateUnits($level)) {
                $fallback = $this->graph->getConflictFallback();

                // handleable
                if ($fallback) {
                    $level = $fallback["level"];

                    // learn conflict clause and
                    // reset to level
                    $this->formula->appendClause($fallback["clause"]);
                    $this->formula->resetAfterLevel($level);

                // fatal
                // unsatisfiable
                } else
                    return false;
            }

        // satisfiable
        return true;
    }

    /**
     * Returns package versions.
     *
     * @return array Versions.
     */
    public function getPath(): array
    {
        $path = [];

        foreach ($this->graph->getNodes() as $variable => $entry)
            foreach ($this->variables as $id => $versions)
                foreach ($versions as $version => $var)
                    if ($variable == $var && $entry["state"]) {
                        $path[$id] = $version;

                        continue 2;
                    }

        return $path;
    }

    /**
     * Returns deadlock.
     *
     * @return array{
     *     id: string,
     *     conflict: string,
     *     locked: string
     * } Deadlock.
     */
    public function getDeadlock(): array
    {
        // abstract conflict variable
        $variable = $this->graph->getConflictVariable();
        $deadlock = [];

        // set package ID and
        // semantic version
        foreach ($this->variables as $id => $versions)
            foreach ($versions as $version => $var)
                if ($variable == $var) {
                    $deadlock = [
                        "id" => $id,
                        "conflict" => $version
                    ];

                    break 2;
                }

        // set locked semantic version
        foreach ($this->getPath() as $id => $version)
            if ($deadlock["id"] == $id) {
                $deadlock["locked"] = $version;

                break;
            }

        return $deadlock;
    }
}