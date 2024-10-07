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

use Valvoid\Fusion\Tasks\Build\SAT\Solver;
use Valvoid\Fusion\Tests\Test;

/**
 * Solver test.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class SolverTest extends Test
{
    private array $structure = [
        "mosaic" => [
            "implication" => [
                "2.0.0" => [
                    "platform" => [
                        "implication" => [
                            "2.0.0" => [],
                            "1.0.0" => []
                        ]
                    ]
                ],
                "1.0.0" => [
                    "platform" => [
                        "implication" => [
                            "3.0.0" => [],
                            "2.0.0" => [],
                            "1.0.0" => [],
                        ]
                    ],
                ]
            ]
        ],
        "router" => [
            "implication" => [
                "1.0.0" => [

                    // circular root
                    "root" => [
                        "implication" => [
                            "1.2.0" => [],
                            "0.8.0-beta+23425" => []
                        ]
                    ],
                    "platform" => [
                        "implication" => [
                            "4.0.0" => [],
                            "1.0.0" => [],
                        ]
                    ]
                ],
            ]
        ]
    ];

    public function __construct()
    {
        $this->testSatisfiable();
        $this->testUnsatisfiable();
        $this->testDeadlock();
    }

    public function testSatisfiable(): void
    {
        $solver = new Solver("root", "1.2.0", $this->structure);

        // assert equal
        if ($solver->isStructureSatisfiable() !== true) {
            echo "\n[x] " . __CLASS__ . " | " . __FUNCTION__;

            $this->result = false;
        }
    }

    public function testUnsatisfiable(): void
    {
        // change root version
        // result in multi version conflict
        $solver = new Solver("root", "1.0.0", $this->structure);

        // assert equal
        if ($solver->isStructureSatisfiable() !== false) {
           echo "\n[x] " . __CLASS__ . " | " . __FUNCTION__;

          $this->result = false;
        }
    }

    public function testDeadlock(): void
    {
        // change root version
        // result in multi version conflict
        $solver = new Solver("root", "1.0.0", $this->structure);
        $expectation = [
            "id" => "root",
            "conflict" => "0.8.0-beta+23425",
            "locked" => "1.0.0"
        ];

        // assert equal
        if ($solver->isStructureSatisfiable() !== false ||
            $solver->getDeadlock() != $expectation) {
            echo "\n[x] " . __CLASS__ . " | " . __FUNCTION__;

            $this->result = false;
        }
    }
}