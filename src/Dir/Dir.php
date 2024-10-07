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

namespace Valvoid\Fusion\Dir;

use Valvoid\Fusion\Bus\Bus;
use Valvoid\Fusion\Bus\Events\Cache;
use Valvoid\Fusion\Config\Config;
use Valvoid\Fusion\Log\Events\Errors\Error;
use Valvoid\Fusion\Log\Events\Errors\Metadata;

/**
 * Current package directory.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Dir
{
    /** @var ?Dir Runtime instance. */
    private static ?Dir $instance = null;

    /** @var string Constant cwd root. */
    private string $root;

    /** @var string Dynamic cache directory. */
    private string $cache;

    /**
     * Constructs the directory.
     *
     * @throws Metadata Invalid meta exception.
     * @throws Error Internal error exception.
     */
    private function __construct()
    {
        $config = Config::get("dir");
        $this->root = $config["path"];

        Bus::addReceiver(self::class, $this->handleBusEvent(...),Cache::class);

        if (is_dir($this->root)) {
            $filenames = scandir($this->root, SCANDIR_SORT_ASCENDING);

            foreach ($filenames as $filename) {
                if ($filename == "." || $filename == "..")
                    continue;

                $file = "$this->root/$filename";

                if (is_file($file)) {
                    if ($filename == "fusion.json") {
                        $meta = file_get_contents($file);

                        if ($meta === false)
                            self::throwMetaError(
                                "Invalid meta. Can't read it from the file.",
                                $file
                            );

                        $meta = json_decode($meta, true);

                        // json config can not be null, it is always complete
                        // only .php file config can contain reset so
                        // drop error on null or false
                        if (!is_array($meta))
                            self::throwMetaError(
                                "Invalid meta. Can't decode it as json.",
                                $file
                            );

                    } elseif (str_starts_with($filename, "fusion.") &&
                        str_ends_with($filename, ".php")) {
                        $meta = include $file;

                        if ($meta === false)
                            self::throwMetaError(
                                "Invalid meta. Can't read it from the file.",
                                $file
                            );

                        if (!is_array($meta))
                            self::throwMetaError(
                                "Invalid meta. The content must be an array.",
                                $file
                            );
                    }
                }

                if (isset($meta["structure"])) {
                    if (!is_array($meta["structure"]) || !$meta["structure"])
                        self::throwMetaError(
                            "Invalid meta. The value of the \"structure\" " .
                            "index must be an non-empty associative array.",
                            $file,
                            ["structure"]
                        );

                    $path = $this->getCachePath($meta["structure"]);

                    if ($path) {
                        $this->cache = "$this->root$path";

                        // asc order
                        // take first match
                        return;
                    }
                }
            }

            Placeholder::replace($config, $filenames);

        } else
            Placeholder::create($config);

        // default placeholder
        $this->cache = "$this->root/cache";
    }

    /**
     * Returns initial instance or true for recycled instance.
     *
     * @return Dir|bool Instance or recycled.
     */
    public static function ___init(): bool|Dir
    {
        if (self::$instance)
            return true;

        self::$instance = new self;

        return self::$instance;
    }

    /**
     * Destroys the cache instance.
     *
     * @return bool True for success.
     */
    public function destroy(): bool
    {
        Bus::removeReceiver(self::$instance::class);

        self::$instance = null;

        return true;
    }

    /**
     * Returns cache path.
     *
     * @param array $struct
     * @param string $breadcrumb
     * @return string|null
     */
    private function getCachePath(array $struct, string $breadcrumb = ""): ?string
    {
        // assoc or seq key due to loadable inside cache folder
        foreach ($struct as $key => $value)
            if ($value == "cache")
                return is_string($key) ?
                    $breadcrumb . $key :
                    $breadcrumb;

            elseif (is_array($value))
                if ($dir = self::getCachePath($value, is_string($key) ?
                    $breadcrumb . $key :
                    $breadcrumb))
                    return $dir;

        return null;
    }

    /**
     * Returns current (locked) task cache directory.
     *
     * @return string Directory.
     */
    public static function getTaskDir(): string
    {
        return self::$instance->cache . "/task";
    }

    /**
     * Returns current (locked) task cache directory.
     *
     * @return string Directory.
     */
    public static function getStateDir(): string
    {
        return self::$instance->cache. "/state";
    }

    /**
     * Returns absolute cache directory.
     *
     * @return string Directory.
     */
    public static function getCacheDir(): string
    {
        return self::$instance->cache;
    }

    /**
     * Returns other directory.
     *
     * @return string Directory.
     */
    public static function getOtherDir(): string
    {
        return self::$instance->cache. "/other";
    }

    /**
     * Returns packages directory.
     *
     * @return string Directory.
     */
    public static function getPackagesDir(): string
    {
        return self::$instance->cache. "/packages";
    }

    /**
     * Normalizes working directory.
     *
     * @throws Error Internal error.
     */
    public function normalize(): void
    {
        $cache = self::$instance->cache;

        self::delete("$cache/state");
        self::delete("$cache/task");
        self::delete("$cache/packages");
        self::delete("$cache/other");
    }

    /**
     * @return string
     */
    public static function getRootDir(): string
    {
        return self::$instance->root;
    }

    /**
     * Creates directory.
     *
     * @param string $dir Dir.
     * @param int $permissions Permissions.
     * @throws Error Internal error.
     */
    public static function createDir(string $dir, int $permissions = 0755): void
    {
        if (!file_exists($dir) &&
            !mkdir($dir, $permissions, true))
                throw new Error(
                    "Can't create the directory \"$dir\"."
                );
    }

    /**
     * Renames file or directory.
     *
     * @param string $from Current file.
     * @param string $to To file.
     * @throws Error Internal error.
     */
    public static function rename(string $from, string $to): void
    {
        if (!rename($from, $to))
            throw new Error(
                "Can't rename the file \"$from\" to \"$to\"."
            );
    }

    /**
     * Copies file.
     *
     * @param string $from Current file.
     * @param string $to To file.
     * @throws Error Internal error.
     */
    public static function copy(string $from, string $to): void
    {
        if (!copy($from, $to))
            throw new Error(
                "Can't copy the file \"$from\" to \"$to\"."
            );
    }

    /**
     * Deletes file or directory.
     *
     * @param string $file Dir or file.
     * @throws Error Internal error.
     */
    public static function delete(string $file): void
    {
        if (is_dir($file)) {
            foreach (scandir($file, SCANDIR_SORT_NONE) as $filename)
                if ($filename != "." && $filename != "..")
                    self::delete("$file/$filename");

            if (!rmdir($file))
                throw new Error(
                    "Can't delete the directory \"$file\"."
                );

        } elseif (is_file($file))
            if (!unlink($file))
                throw new Error(
                    "Can't delete the file \"$file\"."
                );
    }

    /**
     * Deletes empty path parts.
     *
     * @param string $dir Directory.
     * @param string $path Path.
     * @throws Error
     */
    public static function clear(string $dir, string $path): void
    {
        $directory = $dir . $path;

        while ($directory != $dir) {
            if (is_dir($directory)) {
                $filenames = scandir($directory);

                if (isset($filenames[2]))
                    break;

                if (!rmdir($directory))
                    throw new Error(
                        "Can't delete the directory \"$directory\".", "", "", ""
                    );
            }

            $directory = dirname($directory);
        }
    }

    /**
     * Throws metadata error.
     *
     * @param string $message Message.
     * @param string $file File.
     * @param array $index Index.
     * @throws Metadata Invalid meta exception.
     */
    private function throwMetaError(string $message, string $file, array $index = []): void
    {
        throw new Metadata(
            "runtime config layer",
            $message,
            $file,
            $index
        );
    }

    /**
     * Handles bus event.
     *
     * @param Cache $event Event.
     */
    private function handleBusEvent(Cache $event): void
    {
        $this->cache = $event->getDir();
    }
}