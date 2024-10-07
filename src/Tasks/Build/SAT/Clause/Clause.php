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

namespace Valvoid\Fusion\Tasks\Build\SAT\Clause;

/**
 * Clause.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Clause
{
    /** @var State State. */
    private State $state = State::UNKNOWN;

    /** @var array<int, Literal> OR literals. */
    private array $literals = [];

    /**
     * Appends literal.
     *
     * @param int $variable Cross version index.
     * @param bool $negated Negation.
     */
    public function appendLiteral(int $variable, bool $negated = false): void
    {
        // keep variable outside
        // isset faster loop
        $this->literals[$variable] = new Literal($negated);
    }

    /**
     * Sets variable state.
     *
     * @param int $level
     * @param int $variable
     * @param bool $state
     */
    public function setVariableState(int $variable, bool $state, int $level): void
    {
        if (isset($this->literals[$variable]))
            $this->literals[$variable]->setState($level, $state);
    }

    /**
     * Updates state.
     */
    public function updateState(): void
    {
        $unassigned =
        $true = 0;

        foreach ($this->literals as $literal)
            if ($literal->getState() === null)
                $unassigned++;

            elseif ($literal->isNegated()) {
                if (!$literal->getState())
                    $true++;

            } elseif ($literal->getState())
                $true++;

        if ($unassigned == 1 && $true == 0)
            $this->state = State::UNIT;

        elseif ($unassigned == 0)
            $this->state = ($true == 0) ?
                State::UNSATISFIED :
                State::SATISFIED;
        else
            $this->state = State::UNKNOWN;
    }

    /**
     * Returns state.
     *
     * @return State State.
     */
    public function getState(): State
    {
        return $this->state;
    }

    /**
     * Returns literals.
     *
     * @return array Literals.
     */
    public function getLiterals(): array
    {
        return $this->literals;
    }
}