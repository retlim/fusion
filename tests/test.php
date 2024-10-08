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

use Valvoid\Fusion\Tests\Test;

$root = dirname(__DIR__);
$lazy = require "$root/cache/loadable/lazy.php";
$lazy += require "$root/cache/loadable/tests/lazy.php";

spl_autoload_register(function (string $loadable) use ($root, $lazy)
{
    require $root . $lazy[$loadable];
});

/** @var Test[] $tests */
$tests = [
    new Valvoid\Fusion\Tests\Bus\BusTest,
    new Valvoid\Fusion\Tests\Config\ConfigTest,
    new Valvoid\Fusion\Tests\Dir\DirTest,
    new Valvoid\Fusion\Tests\Hub\HubTest,
    new Valvoid\Fusion\Tests\Hub\Cache\CacheTest,
    new Valvoid\Fusion\Tests\Log\LogTest,
    new Valvoid\Fusion\Tests\Util\Version\InterpreterTest,
    new Valvoid\Fusion\Tests\Util\Pattern\InterpreterTest,
    new Valvoid\Fusion\Tests\Tasks\Build\SAT\GraphTest,
    new Valvoid\Fusion\Tests\Tasks\Build\SAT\ClauseTest,
    new Valvoid\Fusion\Tests\Tasks\Build\SAT\SolverTest
];

foreach ($tests as $test)
    if (!$test->getResult()) {
        echo "\n";
        exit(1);
    }

exit;