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

namespace Valvoid\Fusion\Hub\APIs\Local\Git;

use Error;
use Valvoid\Fusion\Hub\APIs\Local\Offset as LocalOffsetApi;
use Valvoid\Fusion\Hub\Responses\Local\Archive;
use Valvoid\Fusion\Hub\Responses\Local\File;
use Valvoid\Fusion\Hub\Responses\Local\Offset as OffsetResponse;
use Valvoid\Fusion\Hub\Responses\Local\References;
use Valvoid\Fusion\Log\Log;

// for error message
// otherwise PHP adds this class as parent namespace
use function exec;

/**
 * Git hub to get local OS packages.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Git extends LocalOffsetApi
{
    /**
     * Returns references.
     *
     * @param string $path Path relative to config root.
     * @return References|string Response or error message.
     */
    public function getReferences(string $path): References|string
    {
        try {
            exec(
                "git -C $this->root$path tag -l 2>&1",
                $output,
                $code
            );

            if ($code) {
                $this->logExecError($code, $output);

                return "Can't extract references.";
            }

            return new References($output);

        // Call to undefined function exec()
        } catch (Error $error) {
            return $error->getMessage();
        }
    }

    /**
     * Returns normalized offset response. A commit ID, sha, hash,
     * whatever unique identifier ...
     *
     * @param string $path Path.
     * @param string $offset Reference offset (commit|branch|...).
     * @return OffsetResponse|string Response or error message.
     */
    public function getOffset(string $path, string $offset): OffsetResponse|string
    {
        try {
            exec(
                "git -C $this->root$path rev-parse $offset 2>&1",
                $output,
                $code
            );

            if ($code) {
                $this->logExecError($code, $output);

                return "The offset \"$offset\" does not exist.";
            }

            return new OffsetResponse(reset($output));

        // Call to undefined function exec()
        } catch (Error $error) {
            return $error->getMessage();
        }
    }

    /**
     * Returns file content.
     *
     * @param string $path Path.
     * @param string $filename Filename.
     * @return File|string Response or error message
     */
    public function getFileContent(string $path, string $reference, string $filename): File|string
    {
        try {
            $filename = substr($filename, 1);

            exec(
                "git -C $this->root$path show $reference:$filename -- 2>&1",
                $output,
                $code
            );

            if ($code) {
                $this->logExecError($code, $output);

                return "Can't get content of the file \"$this->root$path/$filename\" " .
                    "at reference \"$reference\".";
            }

            $output = implode("", $output);

            return new File($output);

        // Call to undefined function exec()
        } catch (Error $error) {
            return $error->getMessage();
        }
    }

    /**
     * Logs exec error.
     *
     * @param int $code Exit code.
     * @param string[] $output Output.
     */
    private function logExecError(int $code, array $output): void
    {
        Log::verbose("\"$code\" exit code.");

        foreach ($output as $line)
            Log::verbose($line);
    }

    /**
     * Creates archive file inside directory.
     *
     * @param string $path Path.
     * @param string $dir Directory.
     * @return Archive|string Response or error message.
     */
    public function createArchive(string $path, string $reference, string $dir): Archive|string
    {
        try {
            $archive = "$dir/archive.zip";
            $extension = "";

            // get current branch name
            // detached HEAD is empty
            exec(
                "git -C $this->root$path branch --show-current 2>&1",
                $output,
                $code
            );

            if ($code) {
                $this->logExecError($code, $output);

                return "Can't get current branch name.";
            }

            // current branch reference feature
            // archive all - uncommitted (added, others), ...
            if (in_array($reference, $output)) {
                $files =
                $output =
                $filter = [];

                exec(

                    // collect added files
                    "git -C $this->root$path diff --name-only --diff-filter=A HEAD 2>&1",
                    $output,
                    $code
                );

                if ($code) {
                    $this->logExecError($code, $output);

                    return "Can't get added files.";
                }

                foreach ($output as $filename)
                    if ($filename != "." && $filename != "..")
                        $files[] = $filename;

                if ($files) {

                    // clear output
                    $output = [];

                    exec(
                        "git -C $this->root$path check-ignore --no-index -- " .
                        implode(" ", $files) . " 2>&1",
                        $output,
                        $code
                    );

                    // 0 and 1 are success
                    if ($code > 1) {
                        $this->logExecError($code, $output);

                        return "Can't check ignored files.";
                    }

                    // filter ignored files and
                    $files = array_diff($files, $output);
                }

                // clear output
                $output = [];

                exec(
                    "git -C $this->root$path ls-files -o --exclude-standard 2>&1",
                    $output,
                    $code
                );

                if ($code) {
                    $this->logExecError($code, $output);

                    return "Can't get other files.";
                }

                $files = array_merge($files, $output);

                if ($files) {

                    // create dirs for export ignore check
                    foreach ($files as $file) {
                        $parts = explode('/', $file);

                        if (isset($parts[1])) {
                            $entry = $parts[0];

                            if (!in_array($entry, $files))
                                $files[] = $entry;

                            for ($i = 1; $i < sizeof($parts); ++$i) {
                                $entry .= "/$parts[$i]";

                                if (!in_array($entry, $files))
                                    $files[] = $entry;
                            }
                        }
                    }

                    // clear output
                    $output = [];

                    exec(
                        "git -C $this->root$path check-attr export-ignore -- " .
                        implode(" ", $files) . " 2>&1",
                        $output,
                        $code
                    );

                    if ($code) {
                        $this->logExecError($code, $output);

                        return "Can't check export ignore attribute.";
                    }

                    foreach ($output as $line) {
                        if (str_ends_with($line, ": set")) {
                            $filter[] = substr($line, 0, -20);
                        }
                    }

                    // remove nested export-ignore
                    foreach ($filter as $ignore)
                        foreach ($files as $i => $file)
                            if (str_starts_with($file, $ignore))
                                unset($files[$i]);

                    $files = array_diff($files, $filter);

                    foreach ($files as $file) {
                        $extension .= " --prefix=$reference/";

                        if (str_contains($file, '/'))
                            $extension .= dirname($file) . "/";

                        $extension .= " --add-file=$file";
                    }
                }
            }

            exec(
                "git -C $this->root$path archive $reference --format=zip " .
                "--output=$archive $extension --prefix=$reference/ 2>&1",
                $output,
                $code
            );

            if ($code) {
                $this->logExecError($code, $output);

                return "Can't create archive file.";
            }

            return new Archive($archive);

        // Call to undefined function exec()
        } catch (Error $error) {
            return $error->getMessage();
        }
    }
}