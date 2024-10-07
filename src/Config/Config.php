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

namespace Valvoid\Fusion\Config;

use Valvoid\Fusion\Bus\Bus;
use Valvoid\Fusion\Bus\Events\Config as ConfigEvent;
use Valvoid\Fusion\Config\Normalizer\Normalizer;
use Valvoid\Fusion\Config\Parser\Dir as DirectoryParser;
use Valvoid\Fusion\Config\Interpreter\Dir as DirectoryInterpreter;
use Valvoid\Fusion\Config\Normalizer\Dir as DirectoryNormalizer;
use Valvoid\Fusion\Fusion;
use Valvoid\Fusion\Log\Events\Errors\Config as ConfigError;
use Valvoid\Fusion\Log\Events\Errors\Metadata;
use Valvoid\Fusion\Config\Interpreter\Interpreter;
use Valvoid\Fusion\Config\Parser\Parser;
use Valvoid\Fusion\Log\Events\Level;
use Valvoid\Fusion\Log\Log;

/**
 * Package manager configuration.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Config
{
    /** @var ?Config Active instance. */
    private static ?Config $instance = null;

    /** @var string @var Package manager root directory. */
    private string $root;

    /** @var array Lazy code registry. */
    private array $lazy;

    /** @var array Separated raw settings. */
    private array $configs;

    /** @var array Composite settings. */
    private array $content = [];

    /** @var string Current source. */
    private string $layer = "runtime";

    /**
     * Constructs the config.
     *
     * @param string $root
     * @param array $lazy
     * @param array $config Runtime config layer.
     * @throws ConfigError Invalid config exception.
     * @throws Metadata Invalid meta exception.
     */
    private function __construct(string $root, array &$lazy, array $config)
    {
        Bus::addReceiver(self::class, $this->handleBusEvent(...), ConfigEvent::class);

        self::$instance = $this;
        $this->root = $root;
        $this->lazy = $lazy;
        $this->configs = [
            "runtime" => $config,
            "persistence" => [],
            "default" => []
        ];

        // current working directory
        // exceptional entry
        // individually before all other entries, as they are based on it
        if (isset($config["dir"])) {
            DirectoryInterpreter::interpret($config);

            if (isset($config["dir"]["path"]))
                DirectoryParser::parse($config);
        }

        // individually before all other entries
        // init default first for reverse content checks
        DirectoryNormalizer::normalize($config);
        $this->initDefaultLayer();
        $this->initPersistenceLayer();

        $this->layer = "runtime";

        Interpreter::interpret($config);
        Parser::parse($config);
        Normalizer::overlay($this->content, $config);

        $this->layer = "all config files merged";

        Normalizer::normalize($this->content);
    }

    /**
     * Returns initial instance or true for recycled instance.
     *
     * @return Config|bool Instance or recycled.
     * @throws ConfigError Invalid config exception.
     * @throws Metadata Invalid meta exception.
     */
    public static function ___init(string $root, array &$lazy, array $config): bool|Config
    {
        if (self::$instance)
            return true;

        new self($root, $lazy, $config);

        return self::$instance;
    }

    /**
     * Destroys the cache instance.
     *
     * @return bool True for success.
     */
    public function destroy(): bool
    {
        Bus::removeReceiver(self::class);
        self::$instance = null;

        return true;
    }

    /**
     * Initializes the default config layer.
     *
     * @throws ConfigError Invalid config exception.
     */
    private function initDefaultLayer(): void
    {
        $this->loadConfigs(
            $this->configs["default"],
            "$this->root/config"
        );

        $this->overlayConfigs($this->configs["default"]);
    }

    /**
     * Overlays configs.
     *
     * @param array $wrapper
     */
    private function overlayConfigs(array $wrapper): void
    {
        foreach (array_reverse($wrapper) as $file => $content) {
            $this->layer = $file;

            // actually null is validatable config
            // but reset manually
            if ($content !== null) {
                Parser::parse($content);
                Normalizer::overlay($this->content, $content);

            // null
            // reset and keep root
            } else
                $this->content = [];
        }
    }

    /**
     * Loads configs.
     *
     * @param string $dir Directory.
     * @param array $wrapper
     * @throws ConfigError Invalid config exception.
     */
    private function loadConfigs(array &$wrapper, string $dir): void
    {
        foreach (scandir($dir, SCANDIR_SORT_ASCENDING) as $filename) {
            if ($filename == "." || $filename == "..")
                continue;

            $file = "$dir/$filename";

            if (is_dir($file))
                $this->loadConfigs($wrapper, $file);

            elseif (str_ends_with($filename, ".php")) {
                $this->layer = $file;
                $config = include $file;
                $wrapper[$file] = $config;

                if ($config === false)
                    self::handleBusEvent(new ConfigEvent(
                        "Can't read the file.",
                        Level::ERROR
                    ));

                Interpreter::interpret($config);
            }
        }
    }

    /**
     * Initializes the persistence config layer.
     *
     * @throws ConfigError Invalid config exception.
     * @throws Metadata Invalid meta exception.
     */
    private function initPersistenceLayer(): void
    {
        $id = $this->getNonNestedPackageId();
        $dir = "$this->root/extensions/config/$id";
        $persistence = &$this->configs["persistence"];

        // nested - relative to itself not
        // current working directory
        // load only own or all persisted configs
        if ($id != "valvoid/fusion") {
            $overlay = $this->configs["runtime"]["persistence"]["overlay"] ??
                null;

            if (is_dir($dir)) {
                $persistence[$id] = [];

                $this->loadConfigs($persistence[$id], $dir);

                // no runtime config
                // check persisted
                if ($overlay === null)
                    foreach ($persistence[$id] as $config)
                        if (isset($config["persistence"]["overlay"])) {
                            $overlay = $config["persistence"]["overlay"];

                            // take first match
                            // files are asc order and higher overlies
                            break;
                        }

                if ($overlay === false)
                    $this->overlayConfigs($persistence[$id]);
            }

            // default fallback if
            // no runtime or persisted config
            if ($overlay ?? true) {
                $file = "$this->root/cache/extensions.php";

                if (file_exists($file)) {
                    $extension = include $file;
                    $this->layer = $file;

                    if ($extension === false)
                        self::handleBusEvent(new ConfigEvent(
                            "Can't read the file.",
                            Level::ERROR
                        ));

                    if (!isset($extension["/extensions/config"]))
                        self::handleBusEvent(new ConfigEvent(
                            "Missing \"/extensions/config\" index.",
                            Level::ERROR
                        ));

                    if (!is_array($extension["/extensions/config"]))
                        self::handleBusEvent(new ConfigEvent(
                            "The value of the \"/extensions/config\" " .
                            "key must be an array.",
                            Level::ERROR
                        ));

                    // flatten tree may have multiple ids
                    // do not override them and
                    // load again
                    foreach ($extension["/extensions/config"] as $id) {
                        if (!isset($persistence[$id])) {
                            $dir = "$this->root/extensions/config/$id";
                            $persistence[$id] = [];

                            $this->loadConfigs($persistence[$id], $dir);
                        }

                        $this->overlayConfigs($persistence[$id]);
                    }

                } else {
                    $config = "$this->root/extensions/config";

                    // check broken persistence
                    // any ids without "extension" file
                    while ($config != $dir) {
                        $dir = dirname($dir);
                        $filenames = scandir($dir, SCANDIR_SORT_NONE);

                        // has more than
                        // ".", "..", and "id part"
                        if (isset($filenames[3]))
                            self::handleBusEvent(new ConfigEvent(
                                "Can't load persistence config layer " .
                                "due to lack of extension cache that contains the " .
                                "overlay order. Run the \"build\" or \"replicate\" " .
                                "task with \"persistence.overlay=false\" parameter " .
                                "to generate it.",
                                Level::ERROR
                            ));
                    }

                    if ($persistence)
                        $this->overlayConfigs($persistence[$id]);
                }
            }

        // standalone
        // load only own persisted config
        } elseif (is_dir($dir)) {
            $persistence[$id] = [];

            $this->loadConfigs($persistence[$id], $dir);
            $this->overlayConfigs($persistence[$id]);
        }
    }

    /**
     * Returns package id of the non-nested/topmost package relative
     * to the package manager.
     *
     * @throws Metadata Invalid meta exception.
     */
    private function getNonNestedPackageId(): string
    {
        $path = DirectoryParser::getNonNestedPath($this->root);

        // standalone
        if ($this->root == $path) {
            return "valvoid/fusion";

        // nested
        // get parent package id
        } else {
            $filenames = scandir($path, SCANDIR_SORT_ASCENDING);

            foreach ($filenames as $filename) {
                if ($filename == "." || $filename == "..")
                    continue;

                $file = "$path/$filename";

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

                // asc order
                // take first match that overrides all other
                if (isset($meta["id"])) {
                    if (!is_string($meta["id"]) || !$meta["id"])
                        self::throwMetaError(
                            "Invalid meta. The value of the \"id\" " .
                            "index must be a non-empty string.",
                            $file,
                            ["id"]
                        );

                    return $meta["id"];
                }
            }

            self::throwMetaError(
                "Invalid meta. The directory does not have a " .
                "metafile with a package ID.",
                $path
            );
        }
    }

    /**
     * Throws meta error.
     *
     * @param string $message Message.
     * @param string $file File.
     * @param array $index Index.
     * @throws Metadata Invalid meta exception.
     */
    private function throwMetaError(string $message, string $file, array $index = []): void
    {
        throw new Metadata($this->root, $message, $file, $index);
    }

    /**
     * Returns composite settings.
     *
     * @param string ...$breadcrumb Index path inside config.
     * @return array Config.
     */
    public static function get(string ...$breadcrumb): mixed
    {
        $content = self::$instance->content;

        foreach ($breadcrumb as $index)
            if (isset($content[$index]))
                $content = $content[$index];

            else
                return null;

        return $content;
    }

    /**
     * Returns lazy code registry.
     *
     * @return array Lazy.
     */
    public static function getLazy(): array
    {
        return self::$instance->lazy;
    }

    /**
     * Returns indicator for existing lazy code.
     *
     * @param string $class Class.
     * @return bool Indicator.
     */
    public static function hasLazy(string $class): bool
    {
        return isset(self::$instance->lazy[$class]);
    }

    /**
     * Handles bus event.
     *
     * @param ConfigEvent $event Root event.
     * @throws ConfigError Invalid config exception.
     */
    protected function handleBusEvent(ConfigEvent $event): void
    {
        $breadcrumb = $event->getBreadcrumb();

        // cli or object interaction
        if ($this->layer == "runtime") {
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

            // reverse
            // take first match
            foreach (array_reverse($backtrace) as $entry)
                if ($entry["class"] == Fusion::class) {
                    $this->layer = $entry["file"];
                    break;
                }

        }

        $config = new ConfigError(
            $event->getMessage(),
            $this->layer,
            $breadcrumb
        );

        match ($event->getLevel()) {
            Level::ERROR => throw $config,
            Level::WARNING => Log::warning($config),
            Level::NOTICE => Log::notice($config),
            Level::VERBOSE => Log::verbose($config),
            Level::INFO => Log::info($config)
        };
    }
}
