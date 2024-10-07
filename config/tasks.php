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

use Valvoid\Fusion\Tasks\Build\Build;
use Valvoid\Fusion\Tasks\Categorize\Categorize;
use Valvoid\Fusion\Tasks\Copy\Copy;
use Valvoid\Fusion\Tasks\Download\Download;
use Valvoid\Fusion\Tasks\Image\Image;
use Valvoid\Fusion\Tasks\Inflate\Inflate;
use Valvoid\Fusion\Tasks\Extend\Extend;
use Valvoid\Fusion\Tasks\Snap\Snap;
use Valvoid\Fusion\Tasks\Register\Register;
use Valvoid\Fusion\Tasks\Replicate\Replicate;
use Valvoid\Fusion\Tasks\Shift\Shift;
use Valvoid\Fusion\Tasks\Stack\Stack;

return [
    "tasks" => [

        // update/upgrade
        "build" => [

            // potential latest state
            "image" => Image::class,
            "build" => Build::class,
            "categorize" => Categorize::class,

            // separated packages
            // flat ID cache directory structure
            "download" => Download::class,
            "copy" => Copy::class,
            "extend" => Extend::class,
            "inflate" => Inflate::class,
            "register" => Register::class,
            "snap" => Snap::class,

            // cached latest state
            "stack" => Stack::class,

            // latest state
            "shift" => Shift::class,
        ],

        // clone
        "replicate" => [

            // potential locked state
            "image" => Image::class,
            "replicate" => Replicate::class,
            "categorize" => Categorize::class,

            // separated packages
            // flat ID cache directory structure
            "download" => Download::class,
            "copy" => Copy::class,
            "extend" => Extend::class,
            "inflate" => Inflate::class,
            "register" => Register::class,
            "snap" => Snap::class,

            // cached locked state
            "stack" => Stack::class,

            // locked state
            "shift" => Shift::class,
        ],

        // normalize
        "inflate" => [
            "image" => Image::class,
            "inflate" => Inflate::class
        ],

        // connect
        "register" => [
            "image" => Image::class,
            "inflate" => Inflate::class,
            "register" => Register::class
        ]
    ]
];