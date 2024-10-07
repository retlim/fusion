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

namespace Valvoid\Fusion\Log\Events\Infos;

use Valvoid\Fusion\Log\Events\Event;

/**
 * Info.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Error implements Event
{
    /** @var string Message. */
    private string $message;

    /** @var int Code. */
    private int $code;

    /** @var array{
     *     file: string,
     *     line: int,
     *     function: string,
     *     class?: string,
     *     type?: string
     * } Path.
     */
    private array $path;

    /**
     * Constructs the info.
     *
     * @param string $message Message.
     * @param int $code Code.
     * @param array $path Path.
     */
    public function __construct(string $message, int $code, array $path)
    {
        $this->message = $message;
        $this->code = $code;
        $this->path = $path;
    }

    /**
     * Returns message.
     *
     * @return string Message.
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Returns code.
     *
     * @return int Code.
     */
    public function getCode(): int
    {
        return $this->code;
    }

    /**
     * Returns path.
     *
     * @return array{
     *      file: string,
     *      line: int,
     *      function: string,
     *      class?: string,
     *      type?: string
     *  } Path.
     */
    public function getPath(): array
    {
        return $this->path;
    }

    /**
     * Returns info as string.
     *
     * @return string Info.
     */
    public function __toString(): string
    {
        $string = "";

        foreach ($this->getPath() as $entry)
            echo "\nin: " . $entry["line"] . " - " . $entry["file"] .
                "\nat: " . ($entry["class"] ?? "") . ($entry["type"] ?? "") .
                $entry["function"] . "()" ;

        $string .= "\nis: " . $this->getMessage() . " | code: " . $this->getCode();

        return $string;
    }
}