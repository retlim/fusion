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

namespace Valvoid\Fusion\Hub\APIs\Local;

use Valvoid\Fusion\Hub\Responses\Local\Archive;
use Valvoid\Fusion\Hub\Responses\Local\File;
use Valvoid\Fusion\Hub\Responses\Local\References;

/**
 * Local API.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
abstract class Local
{
    /** @var array Config. */
    protected array $config;

    /** @var string Parent working directory. */
    protected string $root;

    /**
     * Constructs the file hub.
     *
     * @param array $config Config.
     */
    public function __construct(string $root, array $config)
    {
        $this->root = $root;
        $this->config = $config;
    }

    /**
     * Returns references, potential package version.
     *
     * @param string $path Path relative to config root.
     * @return References|string Response or error message.
     */
    abstract public function getReferences(string $path): References|string;

    /**
     * Returns file content.
     *
     * @param string $path Path.
     * @param string $reference Reference.
     * @param string $filename Filename.
     * @return File|string Response or error message.
     */
    abstract public function getFileContent(string $path, string $reference, string $filename): File|string;

    /**
     * Creates archive file inside directory.
     *
     * @param string $path Path.
     * @param string $reference Reference.
     * @param string $dir Directory.
     * @return Archive|string Response or error message.
     */
    abstract public function createArchive(string $path, string $reference, string $dir): Archive|string;

    /**
     * Returns root directory.
     *
     * @return string Dir.
     */
    public function getRoot(): string
    {
        return $this->root;
    }

    /**
     * Returns absolute file.
     *
     * @param string $path Path.
     * @param string $reference Reference.
     * @param string $filename Filename.
     * @return string File.
     */
    public function getFileLocation(string $path, string $reference, string $filename): string
    {
        return $this->root . $path . $filename . " | $reference";
    }
}