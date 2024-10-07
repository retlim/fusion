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

namespace Valvoid\Fusion\Tasks\Download;

use Exception;
use PharData;
use Valvoid\Fusion\Dir\Dir;
use Valvoid\Fusion\Hub\Hub;
use Valvoid\Fusion\Hub\Responses\Cache\Archive;
use Valvoid\Fusion\Log\Events\Errors\Error;
use Valvoid\Fusion\Log\Events\Errors\Request;
use Valvoid\Fusion\Log\Events\Event;
use Valvoid\Fusion\Log\Events\Infos\Content;
use Valvoid\Fusion\Log\Events\Interceptor;
use Valvoid\Fusion\Log\Log;
use Valvoid\Fusion\Metadata\External\Category as ExternalMetaCategory;
use Valvoid\Fusion\Metadata\External\External as ExternalMeta;
use Valvoid\Fusion\Tasks\Group;
use Valvoid\Fusion\Tasks\Task;
use ZipArchive;

/**
 * Download task to fetch external packages.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Download extends Task implements Interceptor
{
    /** @var string Task cache directory. */
    private string $taskDir;

    /** @var string Packages cache directory. */
    private string $packagesDir;

    /** @var array<int, ExternalMeta> External metadata queue. */
    private array $metas;

    /**
     * Executes the task.
     *
     * @throws Error Internal exception.
     * @throws Request Request exception.
     */
    public function execute(): void
    {
        Log::info("cache external packages");

        if (!Group::hasDownloadable())
            return;

        $this->packagesDir = Dir::getPackagesDir();
        $this->taskDir = Dir::getTaskDir() .
            "/" . $this->config["id"];

        // enqueue all for parallel download
        foreach (Group::getExternalMetas() as $metadata)
            if ($metadata->getCategory() == ExternalMetaCategory::DOWNLOADABLE) {
                $id = Hub::addArchiveRequest($metadata->getSource());
                $this->metas[$id] = $metadata;
            }

        // download and
        // notify when done
        Hub::executeRequests(

            // recommended zip or
            // higher memory usage phar data
            extension_loaded("zip") ?
                $this->extractZipArchive(...) :
                $this->extractPharData(...)
        );
    }

    /**
     * Extracts zip archive to common state cache directory.
     *
     * @param Archive $response Archive response.
     * @throws Error Internal exception.
     */
    private function extractZipArchive(Archive $response): void
    {
        $file = $response->getFile();
        $archive = new ZipArchive;
        $status = $archive->open($file);

        if ($status !== true)
            throw new Error(
                "Can't open the archive file \"$file\". " .

                // no status string here
                // create self
                match ($status) {
                    ZipArchive::ER_EXISTS => "File already exists.",
                    ZipArchive::ER_INCONS => "Zip archive inconsistent.",
                    ZipArchive::ER_INVAL => "Invalid argument.",
                    ZipArchive::ER_MEMORY => "Malloc failure.",
                    ZipArchive::ER_NOENT => "No such file.",
                    ZipArchive::ER_NOZIP => "Not a zip archive.",
                    ZipArchive::ER_OPEN => "Can't open file.",
                    ZipArchive::ER_READ => "Read error.",
                    ZipArchive::ER_SEEK => "Seek error.",

                    // false or whatever
                    default => "Unknown error."
                });

        $metadata = $this->metas[$response->getId()];
        $id = $metadata->getId();
        $from = "$this->taskDir/$id";
        $to = "$this->packagesDir/$id";

        if (!$archive->extractTo($from))
            throw new Error(
                "Can't extract the archive file \"$file\". " .
                $archive->getStatusString()
            );

        if (!$archive->close())
            throw new Error(
                "Can't close the archive file \"$file\". " .
                $archive->getStatusString()
            );

        Dir::createDir($to);

        // validate/normalize
        // get root directory
        $from = $this->getNormalizedFromDir($from, $file);

        Dir::rename($from, $to);
        $this->addBotLayer($to, $metadata->getLayers());
        Log::info(new Content($metadata->getContent()));
    }

    /**
     * Extracts phar data archive to common state cache directory.
     *
     * @param Archive $response Archive response.
     * @throws Error Internal exception.
     */
    private function extractPharData(Archive $response): void
    {
        $file = $response->getFile();

        try {
            $archive = new PharData($file);
            $metadata = $this->metas[$response->getId()];
            $id = $metadata->getId();
            $from = "$this->taskDir/$id";
            $to = "$this->packagesDir/$id";

            $archive->extractTo($from, null, true);

            Dir::createDir($to);

            // validate/normalize
            // get root directory
            $from = $this->getNormalizedFromDir($from, $file);

            Dir::rename($from, $to);
            $this->addBotLayer($to, $metadata->getLayers());
            Log::info(new Content($metadata->getContent()));

        } catch (Exception $exception) {
            throw new Error($exception->getMessage());
        }
    }

    /**
     * Adds bot layer.
     *
     * @param string $to Directory.
     * @param array $layers Raw layers.
     * @throws Error generic exception.
     */
    private function addBotLayer(string $to, array $layers): void
    {
        // persist
        // runtime version = offset overlay
        if (isset($layers["object"]["version"])) {
            $status = file_put_contents(
                "$to/fusion.bot.php",
                "<?php\n" .
                "// Auto-generated by Fusion package manager. \n// Do not modify.\n" .
                "return [\n" .
                "t\\\"version\" => \"" . $layers["object"]["version"] . "\"\n" .
                "];"
            );

            if (!$status)
                throw new Error(
                    "Can't create the file \"$to/fusion.bot.php\"."
                );
        }
    }

    /**
     * Returns package root directory.
     *
     * @param string $dir Potential root.
     * @param string $file Archive file.
     * @return string Directory.
     * @throws Error Invalid archive content.
     */
    private function getNormalizedFromDir(string $dir, string $file): string
    {
        // without root directory
        if (file_exists("$dir/fusion.json"))
            return $dir;

        // most common prefixed
        foreach (scandir($dir, SCANDIR_SORT_NONE) as $filename)
            if ($filename != "." && $filename != ".." &&
                file_exists("$dir/$filename/fusion.json"))
                return "$dir/$filename";

        // invalid package content
        throw new Error(
            "Can't find the production metadata file " .
            "\"fusion.json\" inside the archive file \"$file\". "
        );
    }

    /**
     * Extends event.
     *
     * @param Event|string $event Event.
     */
    public function extend(Event|string $event): void
    {
        if ($event instanceof Request)
            $event->setPath(
                Group::getPath(
                    $this->metas[$event->getId()]->getLayers()

                        // object layer is raw
                        ["object"]["source"]
                )
            );
    }
}