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

use Valvoid\Fusion\Tasks\Build\SAT\Clause\State;
use Valvoid\Fusion\Tasks\Build\SAT\Graph;
use Valvoid\Fusion\Tests\Test;

/**
 * Implication graph test.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class GraphTest extends Test
{
    public function __construct()
    {
        $this->testFallback();
        $this->testConflictSeparation();
        $this->testNodeMutation();
    }

    public function testFallback(): void
    {
        // all placeholder true state
        // must not be valid
        // only result reflection must be true
        $graph = new Graph;
        $graph->addRootNode(1, true, 1);
        $graph->addRootNode(2, true, 2);
        $graph->addLeafNode([2], 3, true, 2);
        $graph->addLeafNode([2], 4, true, 2);
        $graph->addLeafNode([3, 4], 5, true, 2);
        $graph->addLeafNode([5], 6, true, 2);
        $graph->addLeafNode([5], 7, true, 2);
        $graph->addLeafNode([5], 8, true, 2);

        // different states
        // result in conflict
        $graph->addLeafNode([5, 1], 9, true, 2);
        $graph->addLeafNode([1, 6], 9, false, 2);

        $fallback = $graph->getConflictFallback();
        $clause = $fallback["clause"];
        $literals = $clause->getLiterals();

        // assert equal
        if ($fallback["level"] !== 1 ||
            $clause->getState() !== State::UNIT ||
            sizeof($literals) !== 2 ||
            isset($literals[1]) !== true ||
            isset($literals[5]) !== true) {
            echo "\n[x] " . __CLASS__ . " | " . __FUNCTION__;

            $this->result = false;
        }
    }

    public function testConflictSeparation(): void
    {
        // conflict is extra node
        $graph = new Graph;
        $expectation = [
            0 => [
                "roots" => [],
                "level" => 0,
                "state" => true
            ]
        ];

        $graph->addRootNode(0, true, 0);
        $graph->addLeafNode([0], 2, false, 1);
        $graph->addLeafNode([0], 2, true, 1);

        // assert equal
        // conflict node is separated
        if ($graph->getNodes() !== $expectation) {
            echo "\n[x] " . __CLASS__ . " | " . __FUNCTION__;

            $this->result = false;
        }
    }

    public function testNodeMutation(): void
    {
        $graph = new Graph;
        $expectation = [
            0 => [
                "roots" => [],
                "level" => 2,
                "state" => false
            ],
            1 => [
                "roots" => [],
                "level" => 1,
                "state" => true
            ],
            2 => [
                "roots" => [0, 1],
                "level" => 1,
                "state" => true
            ]
        ];

        $graph->addRootNode(0, true, 0);
        $graph->addRootNode(1, true, 1);
        $graph->addLeafNode([0, 1], 2, true, 1);
        $graph->addRootNode(0, false, 2);

        // assert equal
        if ($graph->getNodes() !== $expectation) {
            echo "\n[x] " . __CLASS__ . " | " . __FUNCTION__;

            $this->result = false;
        }
    }
}