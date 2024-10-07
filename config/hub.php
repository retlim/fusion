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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

use Valvoid\Fusion\Hub\APIs\Remote\Valvoid\Valvoid;
use Valvoid\Fusion\Hub\APIs\Remote\GitLab\GitLab;
use Valvoid\Fusion\Hub\APIs\Remote\GitHub\GitHub;
use Valvoid\Fusion\Hub\APIs\Remote\Bitbucket\Bitbucket;
use Valvoid\Fusion\Hub\APIs\Local\Git\Git;
use Valvoid\Fusion\Hub\APIs\Local\Dir\Dir;

return [
    "hub" => [
        "apis" => [
            "valvoid.com" => Valvoid::class,
            "gitlab.com" => GitLab::class,
            "github.com" => GitHub::class,
            "bitbucket.org" => Bitbucket::class,
            "git" => Git::class,
            "dir" => Dir::class
        ]
    ]
];