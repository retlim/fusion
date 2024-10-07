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
 * Literal.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Literal
{
    /** @var bool Negation. */
    private bool $negated;

    /** @var int Decision level. */
    private int $level;

    /** @var bool Assigned random variable state. */
    private bool $state;

    /**
     * Constructs the literal.
     *
     * @param bool $negated Negation.
     */
    public function __construct(bool $negated)
    {
        $this->negated = $negated;
    }

    /**
     * Returns decision level.
     *
     * @return int Level.
     */
    public function getDecisionLevel(): int
    {
        return $this->level;
    }

    /**
     * Sets state.
     *
     * @param bool $state Assigned random state.
     */
    public function setState(int $level, bool $state): void
    {
        $this->level = $level;

        // negation
        $this->state = $state;
    }

    /**
     * Resets
     * @return void
     */
    public function reset(): void
    {
        unset($this->state);
        unset($this->level);
    }

    /**
     * Returns state.
     *
     * @return bool|null State.
     */
    public function getState(): bool|null
    {
        return $this->state ?? null;
    }

    /**
     * Returns negation indicator.
     *
     * @return bool Indicator.
     */
    public function isNegated(): bool
    {
        return $this->negated;
    }
}