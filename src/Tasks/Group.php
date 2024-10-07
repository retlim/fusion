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

namespace Valvoid\Fusion\Tasks;

use Exception;
use Valvoid\Fusion\Fusion;
use Valvoid\Fusion\Metadata\External\Category;
use Valvoid\Fusion\Metadata\Internal\Internal as InternalMeta;
use Valvoid\Fusion\Metadata\External\External as ExternalMeta;
use Valvoid\Fusion\Util\Metadata\Structure;

/**
 * Task group.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Group
{
    /** @var ?Group Runtime instance. */
    private static ?Group $instance = null;

    /** @var array<string, InternalMeta> Internal metas by ID. */
    private array $internalMetas = [];

    /** @var array<string, ExternalMeta> External metas by ID. */
    private array $externalMetas = [];

    /** @var array Root-leaf metadata relations (inline sources - ID's). */
    private array $implication = [];

    /** @var bool Loadable external indicator. */
    private bool $downloadable;

    /** @var InternalMeta Internal root meta. */
    private InternalMeta $internalRootMeta;

    /** @var ?ExternalMeta Recursive external root meta. */
    private ?ExternalMeta $externalRootMeta = null;

    /** @var string[] Runtime layer implication breadcrumb. */
    private array $implicationBreadcrumb = [];

    /**
     * Constructs the group.
     *
     * @throws Exception Locked instance error.
     */
    private function __construct() {}

    /**
     * Returns initial instance or true for recycled instance.
     *
     * @return Group|bool Instance or recycled.
     */
    public static function ___init(): bool|Group
    {
        if (self::$instance)
            return true;

        self::$instance = new self;

        return self::$instance;
    }

    /**
     * Destroys the instance.
     *
     * @return bool True for success.
     */
    public function destroy(): bool
    {
        self::$instance = null;

        return true;
    }

    /**
     * Sets internal metas.
     *
     * @param array<string, InternalMeta> $metas Metas.
     */
    public static function setInternalMetas(array $metas): void
    {
        self::$instance->internalMetas = $metas;

        foreach ($metas as $meta)
            if (!$meta->getDir()) {
                self::$instance->internalRootMeta = $meta;

                break;
            }
    }

    /**
     * Sets implication.
     *
     * @param array $implication Implication.
     */
    public static function setImplication(array $implication): void
    {
        self::$instance->implication = $implication;
    }

    /**
     * Sets external metas.
     *
     * @param array<string, ExternalMeta> $metas Metas.
     */
    public static function setExternalMetas(array $metas): void
    {
        self::$instance->externalMetas = $metas;
        self::$instance->externalRootMeta = null;

        foreach ($metas as $meta)
            if (!$meta->getDir())
                self::$instance->externalRootMeta = $meta;

        unset(self::$instance->downloadable);
    }

    /**
     * Returns optional external root meta.
     *
     * @return ExternalMeta|null Meta.
     */
    public static function getExternalRootMetadata(): ?ExternalMeta
    {
        return self::$instance->externalRootMeta;
    }

    /**
     * Returns internal root meta.
     *
     * @return InternalMeta Meta.
     */
    public static function getInternalRootMetadata(): InternalMeta
    {
        return self::$instance->internalRootMeta;
    }

    /**
     * Returns root metadata.
     *
     * @return ExternalMeta|InternalMeta Meta.
     */
    public static function getRootMetadata(): ExternalMeta|InternalMeta
    {
        $group = self::$instance;

        return $group->externalRootMeta ??
            $group->internalRootMeta;
    }

    /**
     * Returns indicator for loadable meta.
     *
     * @return bool Indicator.
     */
    public static function hasDownloadable(): bool
    {
        if (!isset(self::$instance->downloadable)) {
            self::$instance->downloadable = false;

            foreach (self::$instance->externalMetas as $meta)
                if ($meta->getCategory() == Category::DOWNLOADABLE) {
                    self::$instance->downloadable = true;

                    return true;
                }
        }

        return self::$instance->downloadable;
    }

    /**
     * Returns external metas.
     *
     * @return array<string, ExternalMeta> Metas.
     */
    public static function getExternalMetas(): array
    {
        return self::$instance->externalMetas;
    }

    /**
     * Returns internal metas.
     *
     * @return array<string, InternalMeta> Metas.
     */
    public static function getInternalMetas(): array
    {
        return self::$instance->internalMetas;
    }

    /**
     * Sets implication breadcrumb. If set implication starts
     * at runtime layer passed to the Fusion object.
     *
     * @param string[] $breadcrumb Breadcrumb.
     */
    public static function setImplicationBreadcrumb(array $breadcrumb): void
    {
        self::$instance->implicationBreadcrumb = $breadcrumb;
    }

    /**
     * Returns implication.
     *
     * @return array Implication.
     */
    public static function getImplication(): array
    {
        return self::$instance->implication;
    }

    /**
     * Returns event path.
     *
     * @param string $source Source.
     * @return array Path.
     */
    public static function getPath(string $source): array
    {
        $group = self::$instance;
        $sourcePath = Group::getSourcePath($group->implication, $source);
        $path = [];

        if ($group->implicationBreadcrumb) {
            $id = array_key_first($sourcePath);

            // remove recursive root
            if ($id) {
                $source = array_shift($sourcePath);
                $metadata = $group->externalMetas[$id];
            }

            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

            // reverse
            // take first match
            foreach (array_reverse($backtrace) as $entry)
                if ($entry["class"] == Fusion::class) {
                    $layer = $entry["file"];
                    $row = $entry["line"];

                    break;
                }

            $path[] = [
                "layer" => $row . " - " . $layer . " (runtime config layer)",
                "breadcrumb" => $group->implicationBreadcrumb,
                "source" => $source
            ];

        } else
            $metadata = Group::getInternalRootMetadata();

        foreach ($sourcePath as $id => $source) {
            foreach ($metadata?->getLayers() as $layer => $content)
                if (isset($content["structure"])) {
                    $breadcrumb = Structure::getBreadcrumb(
                        $content["structure"],
                        $source,
                        ["structure"]
                    );

                    if ($breadcrumb) {
                        $path[] = [
                            "layer" => $layer,
                            "breadcrumb" => $breadcrumb,
                            "source" => $source
                        ];

                        // take first match
                        break;
                    }
                }

            // next parent
            // last own entry - maybe not built yet
            if (!isset($group->externalMetas[$id]))
                break;

            $metadata = $group->externalMetas[$id];
        }

        return $path;
    }

    /**
     * Returns first match path to a source.
     *
     * @param array $implication Implication.
     * @param string $source Source.
     * @return array Path.
     */
    public static function getSourcePath(array $implication, string $source): array
    {
        $path = [];

        foreach ($implication as $identifier => $entry) {
            if ($source == $entry["source"])
                return [
                    $identifier => $entry["source"]
                ];

            $path = self::getSourcePath($entry["implication"], $source);

            if ($path)
                return [
                    $identifier => $entry["source"],
                    ...$path
                ];
        }

        return $path;
    }
}