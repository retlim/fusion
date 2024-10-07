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

namespace Valvoid\Fusion\Tasks\Inflate;

use PhpToken;
use Valvoid\Fusion\Dir\Dir;
use Valvoid\Fusion\Log\Events\Errors\Error;
use Valvoid\Fusion\Log\Events\Infos\Content;
use Valvoid\Fusion\Log\Log;
use Valvoid\Fusion\Metadata\External\Category as ExternalMetaCategory;
use Valvoid\Fusion\Metadata\Internal\Category as InternalMetaCategory;
use Valvoid\Fusion\Tasks\Group;
use Valvoid\Fusion\Tasks\Task;

/**
 * Inflate task to generate normalized package state.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Inflate extends Task
{
    /** @var array Current meta loadable. */
    private array $loadable;

    /** @var array Current meta exploded ID. */
    private array $idParts;

    /** @var array As soon as possible loadable code. */
    private array $asap = [];

    /** @var array On demand loadable code. */
    private array $lazy = [];

    /**
     * Executes the task.
     * @throws Error
     */
    public function execute(): void
    {
        // optional
        // register new state
        Group::hasDownloadable() ?
            $this->inflateNewState() :
            $this->inflateCurrentState();
    }

    /** Inflates new cached state.
     * @throws Error
     */
    private function inflateNewState(): void
    {
        Log::info("inflate new packages");

        $packagesDir = Dir::getPackagesDir();

        foreach (Group::getInternalMetas() as $id => $meta)
            if ($meta->getCategory() != InternalMetaCategory::OBSOLETE) {
                Log::info(new Content($meta->getContent()));

                // dynamic root
                // take current
                $dir = "$packagesDir/$id";
                $this->loadable = $meta->getStructureNamespaces();
                $this->idParts = explode('/', $id);
                $cache = $dir . $meta->getStructureCache();

                $this->inflatePackage($dir, $cache);
            }

        foreach (Group::getExternalMetas() as $id => $meta)
            if ($meta->getCategory() == ExternalMetaCategory::DOWNLOADABLE) {
                Log::info(new Content($meta->getContent()));

                // dynamic root
                // take current
                $dir = "$packagesDir/$id";
                $this->loadable = $meta->getStructureNamespaces();
                $this->idParts = explode('/', $id);
                $cache = $dir . $meta->getStructureCache();

                $this->inflatePackage($dir, $cache);
            }
    }

    /**
     * Inflates internal state.
     *
     * @throws Error Internal error.
     */
    private function inflateCurrentState(): void
    {
        Log::info("inflate current packages");

        foreach (Group::getInternalMetas() as $id => $meta) {
            Log::info(new Content($meta->getContent()));

            // static state root
            // take absolute source dir as it is
            $dir = $meta->getSource();
            $this->loadable = $meta->getStructureNamespaces();
            $this->idParts = explode('/', $id);
            $cache = $dir . $meta->getStructureCache();

            $this->inflatePackage($dir, $cache);
        }
    }

    /**
     * Inflates package.
     *
     * @param string $dir Dir.
     * @param string $cache Cache.
     * @throws Error Internal error.
     */
    private function inflatePackage(string $dir, string $cache): void
    {
        // reset
        $this->asap =
        $this->lazy = [];

        $this->extractLoadableFiles($dir, "", $cache);

        Dir::delete("$cache/loadable");

        $this->writeLazy($this->lazy, $cache);
        $this->writeAsap($this->asap, $cache);
    }

    /**
     * Extracts registrable files from directory.
     *
     * @param string $dir Directory.
     * @param string $cache Directory to ignore.
     * @throws Error
     */
    private function extractLoadableFiles(string $dir, string $breadcrumb,
                                          string $cache): void
    {
        $filenames = scandir($dir, SCANDIR_SORT_ASCENDING);

        if ($filenames)
            foreach ($filenames as $filename)
                if ($filename != "." && $filename != "..") {
                    $file = "$dir/$filename";

                    if (is_dir($file)) {
                        if ($file != $cache)
                            $this->extractLoadableFiles($file,
                                "$breadcrumb/$filename", $cache);

                    // only php files
                    } elseif (str_ends_with($file, ".php")) {
                        $content = file_get_contents($file);

                        if ($content === false)
                            throw new Error(
                                "Can't read the file \"$file\"."
                            );

                        $this->extractLoadable("$breadcrumb/$filename",
                            $content);
                    }
                }
    }

    /**
     * Extracts loadable from file relative to package root.
     *
     * @param string $file Relative file.
     * @param string $content Content.
     */
    private function extractLoadable(string $file, string $content): void
    {
        $tokens = PhpToken::tokenize($content);
        $size = sizeof($tokens);
        $namespace = "";
        $brace = $asap = false;
        $root = null;
        $rootSize = 0;
        $lazy = [];

        for ($i = 0; $i < $size; ++$i)
            switch ($tokens[$i]->id) {
                case T_USE:

                    // ignore namespace or
                    // just class name string
                    // like use Exception;
                    if (($i += 2) < $size && ($tokens[$i]->id == T_NAME_QUALIFIED ||
                            $tokens[$i]->id == T_FUNCTION ||
                            $tokens[$i]->id == T_STRING))
                        for ($i++; $i < $size; ++$i)
                            if ($tokens[$i]->text == ";")
                                break 2;

                    // static script
                    return;

                // namespace
                case T_NAMESPACE:
                    if (($i += 2) < $size && ($tokens[$i]->id == T_NAME_QUALIFIED ||
                            $tokens[$i]->id == T_STRING) && !$brace) {
                        $space = explode('\\', $tokens[$i]->text);

                        // validate
                        // space (namespace prefix) must be package id
                        foreach ($this->idParts as $o => $part)
                            if (isset($space[$o]) && strcasecmp($space[$o], $part) !== 0)
                                return;

                        $space = [];

                        foreach ($this->loadable as $namespacePrefix => $path)
                            if (str_starts_with($tokens[$i]->text, $namespacePrefix)) {
                                $space = explode('/', substr($path, 1));

                                break;
                            }

                        $spaceSize = sizeof($space);

                        // init
                        if ($root == null) {
                            $rootSize = $spaceSize;
                            $root = $space;

                        } else

                            // depth
                            if ($rootSize > $spaceSize) {
                                $rootSize = $spaceSize;
                                $root = $space;

                            // alphabetical
                            } elseif ($rootSize == $spaceSize)
                                foreach ($root as $o => $item) {
                                    $comparison = strcasecmp($item, $space[$o]);

                                    // equal = 0 = continue
                                    // item > then new = replace
                                    if ($comparison > 0) {
                                        $rootSize = $spaceSize;
                                        $root = $space;

                                        break;

                                        // keep old root
                                    } elseif ($comparison < 0)
                                        break;
                                }

                        $namespace = $tokens[$i]->text;

                        for ($i++; $i < $size; ++$i)
                            if ($tokens[$i]->text == ";")
                                break 2;

                            elseif ($tokens[$i]->text == "{") {
                                $brace = true;
                                break 2;
                            }
                    }

                    // static script
                    return;

                // lazy identifier
                // class, interface, trait and enum
                case T_CLASS:
                case T_INTERFACE:
                case T_TRAIT:
                case T_ENUM:
                    if (($i += 2) < $size && $namespace && $tokens[$i]->id == T_STRING) {
                        $lazy["$namespace\\" . $tokens[$i]->text] = $file;

                        if ($this->skipBracesContent($tokens, $size, $i))
                            break;
                    }

                    // static script
                    return;

                // as soon as possible identifier
                // function
                case T_FUNCTION:
                    if (($i += 2) < $size && $namespace && $tokens[$i]->id == T_STRING &&
                        $this->skipBracesContent($tokens, $size, $i)) {
                        $asap = true;
                        break;
                    }

                    // static script
                    return;

                // ignore
                case T_OPEN_TAG:
                case T_FINAL:
                case T_WHITESPACE:
                case T_DOC_COMMENT:
                case T_COMMENT:
                case T_ABSTRACT:
                    break;

                default:

                    // reset braced namespace
                    if ($brace && $tokens[$i]->text == "}") {
                        $namespace = "";
                        $brace = false;

                        break;
                    }

                    // static script
                    return;
            }

        if ($root !== null) {
            if ($root) {
                $root = "/" . implode('/', $root);

            } else
                $root = "";

            if ($asap)
                (isset($this->asap[$root])) ?
                    $this->asap[$root][] = $file :
                    $this->asap[$root] = [$file];

            else
                (isset($this->lazy[$root])) ?
                    $this->lazy[$root] = array_merge($this->lazy[$root], $lazy) :
                    $this->lazy[$root] = $lazy;
        }
    }

    /**
     * Skips braces content.
     *
     * @param array $tokens Tokens.
     * @param int $size Size of tokens.
     * @param int $i Pointer.
     * @return bool
     */
    private function skipBracesContent(array $tokens, int $size, int &$i): bool
    {
        for (; $i < $size; ++$i)
            if ($tokens[$i]->text === '{') {
                $i++;

                for ($indicator = 1; $indicator && $i < $size; ++$i)
                    if ($tokens[$i]->text === '{')
                        $indicator++;

                    elseif ($tokens[$i]->text === '}')
                        $indicator--;

                if (!$indicator)
                    return true;
            }

        return false;
    }

    /**
     * Writes extracted loadable to package cache directory.
     *
     * @param array $asap Category.
     * @param string $dir Directory.
     * @throws Error
     */
    private function writeAsap(array $asap, string $dir): void
    {
        foreach ($asap as $root => $files) {
            $content = "";

            foreach ($files as $file)
                $content .= "\n\t'$file',";

            $directory = "$dir/loadable$root";

            Dir::createDir($directory);

            if (!file_put_contents(
                "$directory/asap.php",
                "<?php\n" .
                "// Auto-generated by Fusion package manager. \n// Do not modify.\n" .
                "return [$content\n];"
            ))
                throw new Error(
                    "Can't write to the file \"$directory/asap.php\"."
                );
        }
    }

    /**
     * Writes extracted loadable to package cache directory.
     *
     * @param array $lazy Category.
     * @param string $dir Directory.
     * @throws Error
     */
    private function writeLazy(array $lazy, string $dir): void
    {
        foreach ($lazy as $root => $map) {
            $content = "";

            foreach ($map as $loadable => $file)
                $content .= "\n\t'$loadable' => '$file',";

            $directory = "$dir/loadable$root";

            Dir::createDir($directory);

            if (!file_put_contents(
                "$directory/lazy.php",
                "<?php\n" .
                "// Auto-generated by Fusion package manager. \n// Do not modify.\n" .
                "return [$content\n];"
            ))
                throw new Error(
                    "Can't write to the file \"$directory/lazy.php\"."
                );
        }
    }
}