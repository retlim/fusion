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

namespace Valvoid\Fusion\Log\Events;

/**
 * Event level.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
enum Level: int
{
    // category
    // extendable by amount
    // fatal
    case ERROR = 0;

    // potential error
    case WARNING = 1;

    // improve
    // recommended
    case NOTICE = 2;

    // amount
    // short output
    case INFO = 3;

    // extended output
    case VERBOSE = 4;

    // all output
    case DEBUG = 5;

    /**
     * Returns case from name.
     *
     * @param string $name Name.
     * @return Level|null Case.
     */
    public static function tryFromName(string $name): ?Level
    {
        foreach (self::cases() as $case)
            if (strcasecmp($case->name, $name) === 0)
                return $case;

        return null;
    }
}