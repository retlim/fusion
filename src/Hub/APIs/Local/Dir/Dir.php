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

namespace Valvoid\Fusion\Hub\APIs\Local\Dir;

use Phar;
use PharData;
use Valvoid\Fusion\Hub\APIs\Local\Local as LocalApi;
use Valvoid\Fusion\Hub\Responses\Local\File;
use Valvoid\Fusion\Hub\Responses\Local\References;
use Valvoid\Fusion\Hub\Responses\Local\Archive;

/**
 * Directory hub to get local OS packages.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Dir extends LocalApi
{
    /**
     * Returns versions.
     *
     * @param string $path Path relative to config root.
     * @return References|string Response or error message.
    */
    public function getReferences(string $path): References|string
    {
        // directory has only one pointer
        // version inside metadata
        $file = "$this->root$path/fusion.json";

        if (!file_exists($file))
            return "Invalid directory (package) content. The required " .
                "metadata file \"$file\" does not exist.";

        $content = file_get_contents($file);

        if ($content === false)
            return "Can't read the file \"$file\".";

        $metadata = json_decode($content, true);

        if (!isset($metadata["version"]))
            return "Can't extract version from the file \"$file\".";

        // real version
        return new References([$metadata["version"]]);
    }

    /**
     * Returns indicator for existing reference.
     *
     * @param string $path Path.
     * @param string $reference Reference.
     * @return bool Indicator.
     */
    private function hasReference(string $path, string $reference): bool
    {
        $response = $this->getReferences($path);

        return $response instanceof References &&
            in_array($reference, $response->getEntries());
    }

    /**
     * Returns file content.
     *
     * @param string $path Path.
     * @param string $reference Reference.
     * @param string $filename Filename.
     * @return File|string Response or error message.
     */
    public function getFileContent(string $path, string $reference, string $filename): File|string
    {
        $file = "$this->root$path$filename";

        if (!$this->hasReference($path, $reference))
            return "Can't get content from the file \"$file\"" .
                " at reference \"$reference\". Reference does not exist.";

        if (!file_exists($file))
            return "The file \"$file\" does not exist.";

        $content = file_get_contents($file);

        if ($content === false)
            return "Can't read the file \"$file\".";

        return new File($content);
    }

    /**
     * Creates archive file inside directory.
     *
     * @param string $path Path.
     * @param string $reference Reference.
     * @param string $dir Directory.
     * @return Archive|string Response or error message.
     */
    public function createArchive(string $path, string $reference, string $dir): Archive|string
    {
        if (!$this->hasReference($path, $reference))
            return "Can't create the archive \"$dir/archive.zip\"" .
                " of reference \"$reference\". Reference does not exist.";

        $file = "$dir/archive.zip";
        $archive = new PharData($file);

        $archive->buildFromDirectory($this->root . $path);

        return new Archive($file);
    }
}