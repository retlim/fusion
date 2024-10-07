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

namespace Valvoid\Fusion\Tests\Tasks\Build\SAT;

use Valvoid\Fusion\Tasks\Build\SAT\Clause\Clause;
use Valvoid\Fusion\Tasks\Build\SAT\Clause\State;
use Valvoid\Fusion\Tests\Test;

/**
 * Implication graph test.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class ClauseTest extends Test
{
    public function __construct()
    {
        $this->testUnitState();
        $this->testSatisfiedState();
        $this->testUnsatisfiedState();
    }

    public function testUnitState(): void
    {
        $clause = new Clause;
        $clause->appendLiteral(3);
        $clause->updateState();

        // assert equal
        if ($clause->getState() !== State::UNIT) {
            echo "\n[x] " . __CLASS__ . " | " . __FUNCTION__;

            $this->result = false;
        }
    }

    public function testSatisfiedState(): void
    {
        $clause = new Clause;
        $clause->appendLiteral(3);
        $clause->appendLiteral(5);
        $clause->setVariableState(3, true, 1);
        $clause->setVariableState(5, false, 2);
        $clause->updateState();

        // assert equal
        if ($clause->getState() !== State::SATISFIED) {
            echo "\n[x] " . __CLASS__ . " | " . __FUNCTION__;

            $this->result = false;
        }
    }

    public function testUnsatisfiedState(): void
    {
        $clause = new Clause;
        $clause->appendLiteral(3);
        $clause->appendLiteral(5);
        $clause->setVariableState(3, false, 1);
        $clause->setVariableState(5, false, 3);
        $clause->updateState();

        // assert equal
        if ($clause->getState() !== State::UNSATISFIED) {
            echo "\n[x] " . __CLASS__ . " | " . __FUNCTION__;

            $this->result = false;
        }
    }
}