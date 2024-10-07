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

namespace Valvoid\Fusion\Tasks\Image;

use Valvoid\Fusion\Config\Config;
use Valvoid\Fusion\Log\Events\Errors\Error as InternalError;
use Valvoid\Fusion\Log\Events\Errors\Metadata as MetaError;
use Valvoid\Fusion\Log\Events\Infos\Content;
use Valvoid\Fusion\Log\Log;
use Valvoid\Fusion\Metadata\Internal\Builder as InternalMetadataBuilder;
use Valvoid\Fusion\Metadata\Internal\Internal as InternalMetadata;
use Valvoid\Fusion\Tasks\Group;
use Valvoid\Fusion\Tasks\Task;

/**
 * Image task to get internal metas.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Image extends Task
{
    /** @var array<string, InternalMetadata> Internal metas by ID. */
    private array $metas = [];

    /**
     * Executes the task.
     *
     * @throws MetaError Invalid meta exception.
     * @throws InternalError Internal exception.
     */
    public function execute(): void
    {
        Log::info("image internal metas");

        $root = Config::get("dir", "path");

        // optional
        // internal packages may not exist yet
        // in case of built or replicate from remote source
        if (!file_exists("$root/fusion.json"))
            return;

        // root meta
        // at project package dir
        // without path
        $metadata = $this->getMetadata($root, "");
        $this->metas[$metadata->getId()] = $metadata;

        Log::info(new Content($metadata->getContent()));

        // root structure
        // extract paths
        // jump over before imaged root meta and
        // cache folder paths
        foreach ($metadata->getStructureSources() as $path => $source)

            // empty
            // jump over recursive root
            if ($path) {
                $dir = $root . $path;

                // existing dir
                // root package may in development state
                // with untracked structure paths
                // that exists after build
                if (is_dir($dir))
                    $this->extractMetadata($dir, $path);
            }

        if (isset($this->config["group"]))
            Group::setInternalMetas($this->metas);
    }

    /**
     * Extracts internal metas from root structure directories.
     *
     * @param string $dir Directory.
     * @throws MetaError Invalid meta exception.
     * @throws InternalError Internal exception.
     */
    private function extractMetadata(string $dir, string $path): void
    {
        if (file_exists("$dir/fusion.json")) {
            $metadata = $this->getMetadata($dir, $path);
            $this->metas[$metadata->getId()] = $metadata;

            Log::info(new Content($metadata->getContent()));

        } else foreach (scandir($dir, SCANDIR_SORT_NONE) as $filename)
            if ($filename != "." && $filename != "..") {
                $file = "$dir/$filename";

                if (is_dir($file))
                    $this->extractMetadata($file, $path);
            }
    }

    /**
     * Returns internal meta.
     *
     * @param string $dir Absolute.
     * @param string $path Relative.
     * @return InternalMetadata Meta.
     * @throws MetaError Invalid meta exception.
     * @throws InternalError Internal exception.
     */
    private function getMetadata(string $dir, string $path): InternalMetadata
    {
        $builder = new InternalMetadataBuilder($path, $dir);
        $file = "$dir/fusion.json";
        $content = file_get_contents($file);

        if ($content === false)
            throw new InternalError(
                "Can't get contents from the \"$file\" file."
            );

        $builder->addProductionLayer($content, $file);

        // lock nested dev feature
        // root package only for now
        if (!$path) {
            $file = "$dir/fusion.local.php";

            if (file_exists($file))
                $builder->addLocalLayer($this->getLayerContent($file), $file);

            $file = "$dir/fusion.dev.php";

            if (file_exists($file))
                $builder->addDevelopmentLayer($this->getLayerContent($file), $file);
        }

        // auto-generated content
        $file = "$dir/fusion.bot.php";

        if (file_exists($file))
            $builder->addBotLayer($this->getLayerContent($file), $file);

        return $builder->getMetadata();
    }

    /**
     * Returns metadata layer content.
     *
     * @param string $file File.
     * @return array Content.
     * @throws InternalError Internal error.
     */
    private function getLayerContent(string $file): array
    {
        $content = include $file;

        if ($content === false)
            throw new InternalError(
                "Can't get contents from the \"$file\" file."
            );

        return $content;
    }
}