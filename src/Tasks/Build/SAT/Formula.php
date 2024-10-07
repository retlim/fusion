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
use Valvoid\Fusion\Tasks\Build\SAT\Clause\State;

/**
 * Conjunctive normal form (CNF) formula.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Formula
{
    /** @var Clause[] AND Clauses. */
    private array $clauses = [];

    /** @var Graph Implication graph. */
    private Graph $graph;

    /**
     * Constructs the formula.
     *
     * @param Graph $graph Graph.
     */
    public function __construct(Graph $graph)
    {
        $this->graph = $graph;
    }

    /**
     * Appends clause.
     *
     * @param Clause $clause Clause.
     */
    public function appendClause(Clause $clause): void
    {
        $newLiterals = $clause->getLiterals();
        $newVariables = array_keys($newLiterals);

        // prevent redundant clauses
        foreach ($this->clauses as $entry) {
            $existingLiterals = $entry->getLiterals();

            // if variables (keys) are same then
            if (array_keys($existingLiterals) === array_keys($newLiterals)) {

                // check negations
                foreach ($newVariables as $variable)
                    if ($existingLiterals[$variable]->isNegated() !==
                        $newLiterals[$variable]->isNegated())
                        break 2;

                return;
            }
        }

        $this->clauses[] = $clause;
    }

    /**
     * Selects unassigned variable.
     *
     * @param int $level Decision level.
     * @return bool Indicator for successful or not selection.
     */
    public function selectVariable(int $level): bool
    {
        $variable = null;

        // variables are sorted
        // just take first
        foreach ($this->clauses as $clause)
            if ($clause->getState() == State::UNKNOWN)
                foreach ($clause->getLiterals() as $var => $literal)
                    if ($literal->getState() === null) {
                        $variable = $var;

                        break 2;
                    }

        if ($variable == null)
            return false;

        $this->graph->addRootNode($variable, true, $level);

        // fall through all clauses
        foreach ($this->clauses as $clause) {
            $clause->setVariableState($variable, true, $level);
            $clause->updateState();
        }

        return true;
    }

    /**
     * Completes clauses that require a final literal state.
     * (unit propagation).
     *
     * @param int $level Decision level.
     * @return bool Conflict?
     */
    public function propagateUnits(int $level): bool
    {
        // loop until all variables done due to
        // propagation maybe produces new unit clauses
        while (true) {
            $state =
            $leaf = null;

            // each clause must be true
            // make unassigned literal true
            foreach ($this->clauses as $clause)
                if ($clause->getState() == State::UNIT) {
                    $literals = $clause->getLiterals();
                    $roots = [];

                    if ($leaf && !isset($literals[$leaf]))
                        continue;

                    foreach ($literals as $variable => $literal)
                        if ($literal->getState() === null) {
                            $state = !$literal->isNegated();
                            $leaf = $variable;

                        } else
                            $roots[] = $variable;

                    // satisfy the clause
                    $clause->setVariableState($leaf, $state, $level);
                    $clause->updateState();

                    if (!$this->graph->addLeafNode($roots, $leaf,

                        // validate
                        // multi variable state conflict
                        $state, $level))
                        return false;
                }

            if ($leaf === null)
                return true;

            // no conflicts between unit clauses
            // spread the unit state
            foreach ($this->clauses as $clause)
                if ($clause->getState() == State::UNKNOWN) {
                    foreach ($clause->getLiterals() as $variable => $literal)
                        if ($variable === $leaf) {
                            $clause->setVariableState($leaf, $state, $level);
                            $clause->updateState();

                            continue 2;
                        }
                }
        }
    }

    /**
     * Resets all above level.
     *
     * @param int $level Lower limit.
     */
    public function resetAfterLevel(int $level): void
    {
        foreach ($this->clauses as $clause) {
            foreach ($clause->getLiterals() as $literal)
                if ($literal->getState() !== null &&
                    $literal->getDecisionLevel() > $level)
                    $literal->reset();

            $clause->updateState();
        }
    }
}