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

namespace Valvoid\Fusion\Tests\Hub\Cache;

use Valvoid\Fusion\Hub\Cache;
use Valvoid\Fusion\Tests\Test;

/**
 * Hub cache test.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class CacheTest extends Test
{
    private Cache $cache;

    private array $versions = [
        "1.2.3",
        "2.3.4+aaaaaaa",
        "3.4.5-beta"
    ];

    private array $offsets = [
        ""
    ];

    public function __construct()
    {
        $this->cache = new Cache("");

        foreach ($this->versions as $version)
            $this->cache->addVersion("test", "test", $version);

        $this->testLogicalReference();
        $this->testIllogicalReference();
    }

    public function testLogicalReference(): void
    {
        $versions = $this->cache->getVersions("test", "test", [[
            "build" => "",
            "release" => "alpha",
            "major" => "1",
            "minor" => "0",
            "patch" => "0",
            "sign" =>  ">="
        ], "&&", [
            "build" => "",
            "release" => "",
            "major" => "3",
            "minor" => "0",
            "patch" => "0",
            "sign" =>  "<="
        ]]);

        // desc sort
        $expectation = [
            "2.3.4+aaaaaaa",
            "1.2.3"
        ];

        // assert equal
        if ($versions !== $expectation) {
            echo "\n[x] " . __CLASS__ . " | " . __FUNCTION__;

            $this->result = false;
        }
    }

    public function testIllogicalReference(): void
    {
        $versions = $this->cache->getVersions("test", "test", [
            [
                [ // get "1.2.3"
                    "build" => "",
                    "release" => "",
                    "major" => "1",
                    "minor" => "0",
                    "patch" => "0",
                    "sign" =>  ""
                ], "&&", [
                    // and not the matched "1.2.3"
                    "build" => "",
                    "release" => "",
                    "major" => "1",
                    "minor" => "2",
                    "patch" => "3",
                    "sign" =>  "!="
                ]
            ], "||", [
                [
                    "build" => "",
                    "release" => "",
                    "major" => "1",
                    "minor" => "0",
                    "patch" => "0",
                    "sign" =>  ""
                ], "&&", [ // and "==absolute" not the matched "1.2.3"
                    "build" => "",
                    "release" => "beta",
                    "major" => "3",
                    "minor" => "4",
                    "patch" => "5",
                    "sign" =>  "=="
                ]
            ]
        ]);

        // assert equal
        if ($versions !== []) {
            echo "\n[x] " . __CLASS__ . " | " . __FUNCTION__;

            $this->result = false;
        }
    }
}