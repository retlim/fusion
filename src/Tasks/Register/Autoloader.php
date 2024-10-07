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

/**
 * Autoloader to access lazy and ASAP package code.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Autoloader
{
    /** @var array<string, string> Lazy code. */
    public const LAZY = [];

    /** @var string[] ASAP code. */
    public const ASAP = [];

    /** @var ?self Runtime instance. */
    private static ?self $instance = null;

    /** @var string Project dir. */
    private string $root;

    /**
     * Constructs autoloader.
     *
     * @param bool $optional Optional loader.
     * @param bool $prepend Prepend.
     */
    private function __construct(bool $optional = false, bool $prepend = false)
    {
        $this->root = dirname(__DIR__, 2);

        // load as soon as possible code
        foreach (self::ASAP as $file)
            require $this->root . $file;

        // register lazy code
        // load on demand
        spl_autoload_register(
            ($optional ?
                self::loadOptionalCode(...) :
                self::loadCode(...)),

            // ignored
            true, $prepend);
    }

    /**
     * Constructs autoloader.
     *
     * @param bool $optional Optional loader.
     * @param bool $prepend Prepend.
     * @throws Exception Multi autoloader exception.
     */
    public static function init(bool $optional = false, bool $prepend = false): self
    {
        if (self::$instance)
            throw new Exception("Autoloader already exists.");

        return self::$instance ??=
            new self($optional, $prepend);
    }

    /** Destroys instance. */
    public function destroy(): void
    {
        spl_autoload_unregister(self::loadCode(...));
        spl_autoload_unregister(self::loadOptionalCode(...));

        self::$instance = null;
    }

    /**
     * Loads optional lazy code.
     *
     * @param string $loadable Identifier.
     */
    private function loadOptionalCode(string $loadable): void
    {
        // registered
        // hide unregistered warning
        if (@$file = self::LAZY[$loadable])

            // no file
            // hide stream warning
            include $this->root . $file;
    }

    /**
     * Loads lazy code.
     *
     * @param string $loadable Identifier.
     */
    private function loadCode(string $loadable): void
    {
        require $this->root . self::LAZY[$loadable];
    }
}