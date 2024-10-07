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

namespace Valvoid\Fusion\Tasks\Replicate;

use Valvoid\Fusion\Dir\Dir;
use Valvoid\Fusion\Hub\Hub;
use Valvoid\Fusion\Hub\Responses\Cache\Metadata as MetadataResponse;
use Valvoid\Fusion\Hub\Responses\Cache\Snapshot;
use Valvoid\Fusion\Hub\Responses\Cache\Versions;
use Valvoid\Fusion\Log\Events\Errors\Environment as EnvironmentError;
use Valvoid\Fusion\Log\Events\Errors\Error as InternalError;
use Valvoid\Fusion\Log\Events\Errors\Metadata as MetadataError;
use Valvoid\Fusion\Log\Events\Errors\Request;
use Valvoid\Fusion\Log\Events\Event;
use Valvoid\Fusion\Log\Events\Infos\Content;
use Valvoid\Fusion\Log\Events\Interceptor;
use Valvoid\Fusion\Log\Log;
use Valvoid\Fusion\Metadata\External\Builder as ExternalMetaBuilder;
use Valvoid\Fusion\Metadata\External\Builder as ExternalMetadataBuilder;
use Valvoid\Fusion\Metadata\External\External as ExternalMetadata;
use Valvoid\Fusion\Metadata\Internal\Internal as InternalMetadata;
use Valvoid\Fusion\Metadata\Metadata;
use Valvoid\Fusion\Tasks\Group;
use Valvoid\Fusion\Tasks\Task;
use Valvoid\Fusion\Util\Reference\Normalizer;
use Valvoid\Fusion\Util\Metadata\Structure;

/**
 * Replicate task to clone nested metas.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Replicate extends Task implements Interceptor
{
    /** @var array<int, array{
     *     source: string,
     *     builder: ExternalMetadataBuilder,
     *     metas: int[]
     * }> Requests.
     */
    private array $requests = [];

    /** @var array<string, string> Snapshot. */
    private array $snapshot = [];

    /** @var array Implication roots. */
    private array $roots = [];

    /** @var array<string, ExternalMetadata> External metas by ID. */
    private array $metas = [];

    /** @var array<string, array{
     *      source: string,
     *      implication: array<string, array>
     * }> Metadata implication.
     */
    private array $implication = [];

    /**
     * Executes the task.
     *
     * @throws InternalError Internal exception.
     * @throws Request Invalid request exception.
     * @throws EnvironmentError Invalid environment exception.
     */
    public function execute(): void
    {
        Log::info("replicate nested metas");

        // external root package
        // remote snapshot file
        if ($this->config["source"]) {
            Group::setImplicationBreadcrumb(["replicate", "source"]);
            $this->replicateExternalRoot($this->config["source"]);

        // internal imaged root source
        // built optional recursive metadata and/or
        // replicate dependencies
        } else {

            // placeholder or "real" one
            $metadata = Group::getInternalRootMetadata();
            $sources = $metadata->getStructureSources();

            // recursive root source
            if (isset($sources[""]) &&

                // default behaviour
                // else take local snapshot and
                // ignore recursive source
                $this->config["source"] === false)

                // just get first
                // actually string but normalized to array
                $this->replicateExternalRoot($sources[""][0]);

            elseif ($sources)
                $this->replicateNestedRoots($metadata);
        }

        Group::setImplication($this->implication);
        Group::setExternalMetas($this->metas);
    }

    /**
     * Builds recursive root.
     *
     * @param string $source Source.
     * @throws InternalError Hub exception.
     * @throws Request Request exception.
     * @throws EnvironmentError Environment error.
     */
    private function replicateExternalRoot(string $source): void
    {
        // parse inline source
        $builder = new ExternalMetaBuilder("", $source);

        Log::info("...");

        // request topmost version
        $reqId = Hub::addVersionsRequest($builder->getParsedSource());
        $this->requests[$reqId] = [
            "builder" => $builder,
            "source" => $source,
            "metas" => []
        ];

        Hub::executeRequests(function (Versions $response) use ($builder) {

            // replace pattern reference with version
            // it's also a tag, commit, branch, ...
            $builder->normalizeReference($response->getTopEntry());
        });

        // remove error fallback
        unset($this->requests[$reqId]);

        // metadata
        // implication root
        $reqId = Hub::addMetadataRequest($builder->getNormalizedSource());
        $this->roots = [$reqId];
        $this->requests[$reqId] = [
            "builder" => $builder,
            "source" => $source,
            "metas" => []
        ];

        Hub::executeRequests(function (MetadataResponse $response) use ($builder) {
            $builder->addProductionLayer(
                $response->getContent(),
                $response->getFile()
            );
        });

        $metadata = $builder->getMetadata();
        $this->metas[$metadata->getId()] = $metadata;

        $this->validateEnvironment(
            $metadata,
            [$this->config["environment"]["php"]["version"]]
        );

        // get remote production snapshot file
        $snapId = Hub::addSnapshotRequest($metadata->getSource(), $metadata->getStructureCache());
        $this->requests[$snapId] = [

            // optional snapshot file path
            // same source as meta
            "source" => $source
        ];

        Hub::executeRequests(function (Snapshot $response) use ($metadata) {
            $this->addSnapshot(
                $response->getContent(),
                $response->getFile()
            );
        });

        $this->addMetadataRequests(
            $metadata,
            $this->requests[$reqId]["metas"],
            ""
        );

        $this->buildRequests();
        $this->buildImplication();
    }

    /**
     * Sets snapshot.
     *
     * @param string $content Content.
     * @param string $file Absolute file.
     * @throws InternalError Internal error.
     */
    private function addSnapshot(string $content, string $file): void
    {
        $snapshot = json_decode($content, true);

        if ($snapshot === null)
            throw new InternalError(
                "Can't decode the snapshot content of the \"$file\". " .
                json_last_error_msg()
            );

        $this->snapshot += $snapshot;
    }

    /**
     * Builds nested roots.
     *
     * @param InternalMetadata $metadata
     * @throws Structure Hub exception.
     * @throws Request Request exception.
     * @throws InternalError Internal error.
     */
    private function replicateNestedRoots(InternalMetadata $metadata): void
    {
        foreach (["", ".dev", ".local"] as $filename) {
            $file = Dir::getCacheDir() . "/snapshot$filename.json";

            if (file_exists($file)) {
                $snapshot = file_get_contents($file);

                if ($snapshot === false)
                    throw new InternalError(
                        "Can't read the snapshot file \"$file\"."
                    );

                $this->addSnapshot($snapshot, $file);

            // at least required production
            } elseif ($filename === "")
                throw new InternalError(
                    "The snapshot file \"$file\" does not exist."
                );
        }

        Log::info("...");

        $this->addMetadataRequests($metadata, $this->roots, "");
        $this->buildRequests();
        $this->buildImplication();
    }

    /**
     * Adds versions requests for each source.
     *
     * @param Metadata $metadata Metadata.
     * @throws Structure Hub exception.
     * @throws InternalError Internal error.
     */
    private function addMetadataRequests(Metadata $metadata, array &$metas,
                                         string $directory): void
    {
        foreach ($metadata->getStructureSources() as $dir => $sources)

            // not empty
            // jump over recursive
            if ($dir)
                foreach ($sources as $source) {

                    // fake entry
                    // enable trace if $source error drop
                    $metas["meta"] = "meta";
                    $this->requests["meta"] = [
                        "source" => $source,
                        "metas" => []
                    ];

                    $builder = new ExternalMetadataBuilder(

                        // inherit or
                        // direct directory
                        $directory ?: $dir, $source);

                    $packageId = $builder->getId();
                    $reference = $this->snapshot[$packageId] ??
                        throw new InternalError(
                            "Can't replicate the package \"$packageId\". " .
                            "The snapshot file does not contain this ID." .
                            json_last_error_msg()
                        );

                    $builder->normalizeReference($reference);

                    // clear fake entry
                    unset($metas["meta"]);
                    unset($this->requests["meta"]);

                    $id = Hub::addMetadataRequest($builder->getNormalizedSource());
                    $metas[] = $id;
                    $this->requests[$id] = [
                        "builder" => $builder,
                        "source" => $source,
                        "metas" => []
                    ];
                }
    }

    /**
     * Builds all requests.
     *
     * @throws Structure Hub exception.
     * @throws Request Request exception.
     * @throws InternalError
     */
    private function buildRequests(): void
    {
        $environment = [$this->config["environment"]["php"]["version"]];

        // recursive loop
        // add new request before queue "done" check
        Hub::executeRequests(function (MetadataResponse $response) use ($environment)
        {
            // parent
            $id = $response->getId();
            $builder = $this->requests[$id]["builder"];

            $builder->addProductionLayer(
                $response->getContent(),
                $response->getFile()
            );

            $metadata = $builder->getMetadata();
            $this->metas[$metadata->getId()] = $metadata;

            $this->validateEnvironment($metadata, $environment);
            $this->addMetadataRequests(
                $metadata,
                $this->requests[$id]["metas"],
                $builder->getRawDir()
            );
        });
    }

    /**
     * Builds implication.
     */
    private function buildImplication(): void
    {
        $this->implication = [];

        $this->addMetasImplication(
            $this->implication,

            // initial "fake" wrapper
            $this->roots
        );

        Group::setImplication($this->implication);
        Group::setExternalMetas($this->metas);
    }

    /**
     * Adds metas implication.
     *
     * @param array $implication Pointer.
     * @param int[] $metas Metadata request IDs.
     */
    private function addMetasImplication(array &$implication, array $metas): void
    {
        foreach ($metas as $mId) {
            $request = $this->requests[$mId];
            $builder =  $request["builder"];
            $id = $builder->getId();

            $implication[$id] = [
                "source" => $request["source"],
                "implication" => []
            ];

            $this->addMetasImplication(
                $implication[$id]["implication"],
                $request["metas"]
            );
        }
    }

    /**
     * Validates environment.
     *
     * @param ExternalMetadata $metadata Metadata.
     * @param array $environment Environment.
     * @throws EnvironmentError Invalid environment exception.
     */
    private function validateEnvironment(ExternalMetadata $metadata,
                                         array $environment): void
    {
        $php = $metadata->getEnvironment()["php"];

        // validate version and
        // modules
        if (!Normalizer::getFilteredVersions($environment, $php["version"])) {
            $layers = $metadata->getLayers();
            $this->buildImplication();

            throw new EnvironmentError(
                "Can't replicate the package \"" . $metadata->getId() .
                "\". The current PHP version \"" . $environment[0]["major"] . "." .
                $environment[0]["minor"] . "." . $environment[0]["patch"].
                "\" does not pass the pattern.",
                Group::getPath($layers["object"]["source"]),
                array_key_first($layers),
                ["environment", "php", "version"]
            );
        }

        $extensions = get_loaded_extensions();

        foreach ($extensions as $key => $extension)
            $extensions[$key] = strtolower($extension);

        foreach ($php["modules"] as $module)
            if (!in_array($module, $extensions)) {
                $layers = $metadata->getLayers();
                $this->buildImplication();

                throw new EnvironmentError(
                    "Can't replicate the package \"" . $metadata->getId() .
                    "\". It requires the missing module \"$module\".",
                    Group::getPath($layers["object"]["source"]),
                    array_key_first($layers),
                    ["environment", "php", "modules"]
                );
            }

        Log::info(new Content($metadata->getContent()));
    }

    /**
     * Extends event.
     *
     * @param Event|string $event Event.
     */
    public function extend(Event|string $event): void
    {
        if ($event instanceof Request) {
            $this->buildImplication();
            $event->setPath(
                Group::getPath(
                    $this->requests[$event->getId()]["source"]
                )
            );

        } elseif ($event instanceof MetadataError) {
            $this->buildImplication();
            $event->setPath(
                Group::getPath(
                    $event->getSource()
                )
            );
        }
    }
}