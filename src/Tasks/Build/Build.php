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

namespace Valvoid\Fusion\Tasks\Build;

use Valvoid\Fusion\Fusion;
use Valvoid\Fusion\Hub\Hub;
use Valvoid\Fusion\Hub\Responses\Cache\Metadata as MetadataResponse;
use Valvoid\Fusion\Hub\Responses\Cache\Versions as VersionsResponse;
use Valvoid\Fusion\Log\Events\Errors\Deadlock as DeadlockError;
use Valvoid\Fusion\Log\Events\Errors\Environment as EnvironmentError;
use Valvoid\Fusion\Log\Events\Errors\Error;
use Valvoid\Fusion\Log\Events\Errors\Metadata as MetadataError;
use Valvoid\Fusion\Log\Events\Errors\Request;
use Valvoid\Fusion\Log\Events\Event;
use Valvoid\Fusion\Log\Events\Infos\Content;
use Valvoid\Fusion\Log\Events\Interceptor;
use Valvoid\Fusion\Log\Log;
use Valvoid\Fusion\Metadata\External\Builder as ExternalMetadataBuilder;
use Valvoid\Fusion\Metadata\External\External;
use Valvoid\Fusion\Metadata\Internal\Internal as InternalMetadata;
use Valvoid\Fusion\Metadata\Metadata;
use Valvoid\Fusion\Tasks\Build\SAT\Solver;
use Valvoid\Fusion\Tasks\Group;
use Valvoid\Fusion\Tasks\Task;
use Valvoid\Fusion\Util\Metadata\Structure;
use Valvoid\Fusion\Util\Reference\Normalizer;

/**
 * Build task to get external package metas.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Build extends Task implements Interceptor
{
    /** @var array<int, array{
     *     source: string,
     *     builder: ExternalMetadataBuilder,
     *     metas?: int[],
     *     metadata?: External,
     *     versions?: int[]
     * }> Requests.
     */
    private array $requests = [];

    /** @var array<string, array{
     *      source: string,
     *      implication: array<string, array>
     * }> Absolute metadata implication.
     */
    private array $implication = [];

    /** @var array<string, array<string, External>> Absolute external metas. */
    private array $metas = [];

    /**
     * Executes the task.
     *
     * @throws Error Hub exception.
     * @throws Request Request exception.
     * @throws DeadlockError Deadlock exception.
     * @throws EnvironmentError Environment exception.
     */
    public function execute(): void
    {
        Log::info("build external metas");

        // external source
        // build remote metadata
        if ($this->config["source"]) {
            Group::setImplicationBreadcrumb(["built", "source"]);
            $this->buildExternalRoot($this->config["source"]);

        // internal imaged root source
        // build optional recursive metadata or
        // dependencies
        } else {

            // placeholder or "real" one
            $metadata = Group::getInternalRootMetadata();
            $sources = $metadata->getStructureSources();

            // recursive root source
            // prevent redundancy
            // remote may have other packages
            if (isset($sources[""]))

                // just get first
                // actually string but normalized to array
                $this->buildExternalRoot($sources[""][0]);

            elseif ($sources)
                $this->buildNestedRoots($metadata);
        }
    }

    /**
     * Builds external root.
     *
     * @param string $source Source.
     * @throws Error Hub exception.
     * @throws Request Request exception.
     * @throws DeadlockError Deadlock exception.
     * @throws EnvironmentError Environment exception.
     */
    private function buildExternalRoot(string $source): void
    {
        Log::info("...");

        // parse inline source
        $builder = new ExternalMetadataBuilder("", $source);
        $versions = [];

        // get all versions
        $reqId = Hub::addVersionsRequest($builder->getParsedSource());
        $this->requests[$reqId] = [
            "builder" => $builder,
            "source" => $source,
            "metas" => []
        ];

        Hub::executeRequests(function (VersionsResponse $response) use (&$versions) {
            $versions = $response->getEntries();
        });

        // loop all roots in top-down order
        // until match
        foreach ($versions as $i => $version) {

            // replace pattern reference with version
            // it's also a tag, commit, branch, ...
            $builder->normalizeReference($version);

            // get metadata
            $metasId = Hub::addMetadataRequest($builder->getNormalizedSource());
            $this->requests[$reqId]["metas"][] = $metasId;
            $this->requests[$metasId] = [
                "builder" => $builder,
                "source" => $source,
                "versions" => []
            ];

            Hub::executeRequests(function (MetadataResponse $response) use ($builder) {
                $builder->addProductionLayer(
                    $response->getContent(),
                    $response->getFile()
                );
            });

            $metadata = $builder->getMetadata();
            $this->requests[$metasId]["metadata"] = $metadata;

            $this->addVersionsRequests(
                $metadata,
                $this->requests[$metasId]["versions"],
                ""
            );

            $this->buildRequests();
            $this->buildImplication();

            $id = $metadata->getId();
            $version = $metadata->getVersion();

            $solver = new Solver($id, $version, $this->implication[$id]["implication"][$version]);

            if ($solver->isStructureSatisfiable()) {
                $this->extractStructure($solver->getPath());

                // done
                return;

            // take topmost deadlock
            } elseif ($i == 0)
                $deadlock = new Deadlock(
                    $solver->getDeadlock(),
                    $this->implication,
                    $this->metas,
                    $this->config["source"] ??
                        null
                );

            // root version reset
            $this->implication =
            $this->metas = [];
            $this->requests[$reqId] = [
                "builder" => $this->requests[$reqId]["builder"],
                "source" => $source,
                "metas" => []
            ];
        }

        $deadlock->throwError();
    }

    /**
     * Adds versions requests for each source.
     *
     * @param Metadata $metadata Metadata.
     * @param int[] $versions Request wrapper.
     * @throws Error Hub exception.
     * @throws Request Request exception.
     */
    private function addVersionsRequests(Metadata $metadata, array &$versions, $directory): void
    {
        foreach ($metadata->getStructureSources() as $dir => $sources)

            // not empty
            // jump over recursive
            if ($dir)
                foreach ($sources as $source) {

                    // fake entry
                    // enable trace if $source error drop
                    $versions["version"] = "version";
                    $this->requests["version"] = [
                        "source" => $source,
                        "metas" => ["meta"]
                    ];

                    $this->requests["meta"] = [
                        "source" => $source,
                        "metas" => []
                    ];

                    $builder = new ExternalMetadataBuilder(

                        // inherit or
                        // direct directory
                        $directory ?: $dir, $source);

                    // clear fake entry
                    unset($versions["version"]);
                    unset($this->requests["version"]);
                    unset($this->requests["meta"]);

                    // add real entry
                    $id = Hub::addVersionsRequest($builder->getParsedSource());
                    $versions[] = $id;
                    $this->requests[$id] = [
                        "builder" => $builder,
                        "source" => $source,
                        "metas" => []
                    ];
                }
    }

    /**
     * Builds nested roots.
     *
     * @param InternalMetadata $metadata
     * @throws Error Hub exception.
     * @throws Request Request exception.
     * @throws DeadlockError Deadlock exception.
     * @throws EnvironmentError Environment exception.
     */
    private function buildNestedRoots(InternalMetadata $metadata): void
    {
        Log::info("...");

        // fake versions wrapper
        $wrapper = [];
        $id = $metadata->getId();
        $version = $metadata->getVersion();

        $this->addVersionsRequests($metadata, $wrapper, "");
        $this->buildRequests();
        $this->buildImplication();

        $solver = new Solver($id, $version, $this->implication);

        if ($solver->isStructureSatisfiable()) {
            $path = $solver->getPath();

            // remove internal root
            unset($path[$id]);
            $this->extractStructure($path);

        } else (new Deadlock(
            $solver->getDeadlock(),
            $this->implication,
            $this->metas

        ))->throwError();
    }

    /**
     * Builds all requests.
     *
     * @throws Error Hub exception.
     * @throws Request Request exception.
     */
    private function buildRequests(): void
    {
        // recursive loop
        // add new request before queue "done" check
        Hub::executeRequests(function (VersionsResponse|MetadataResponse $response)
        {
            $id = $response->getId();
            $builder = $this->requests[$id]["builder"];
            $source = $this->requests[$id]["source"];

            if ($response instanceof VersionsResponse)
                foreach ($response->getEntries() as $version) {
                    $builder = clone $builder;

                    // replace pattern
                    $builder->normalizeReference($version);

                    $metasId = Hub::addMetadataRequest($builder->getParsedSource());
                    $this->requests[$id]["metas"][] = $metasId;
                    $this->requests[$metasId] = [
                        "builder" => $builder,
                        "source" => $source,
                        "versions" => []
                    ];
                }

            else {
                $builder->addProductionLayer(
                    $response->getContent(),
                    $response->getFile()
                );

                $metadata = $builder->getMetadata();
                $this->requests[$id]["metadata"] = $metadata;

                $this->addVersionsRequests(
                    $metadata,
                    $this->requests[$id]["versions"],
                    $builder->getRawDir()
                );
            }
        });
    }

    /**
     * Builds absolute implication.
    */
    private function buildImplication(): void
    {
        $this->implication = [];

        // loop all roots
        // "metas" indicators until first "versions"
        foreach ($this->requests as $request)
            if (isset($request["metas"])) {
                if ($request["metas"])
                    $this->addMetasImplication(
                        $this->implication,
                        $request["metas"]
                    );

                else
                    $this->implication[] = [
                        "source" =>

                        // fake if root error
                            $request["source"]];

            // nested "versions"
            } else break;
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
            $metadata = $this->requests[$mId]["metadata"] ??
                null;

            // build only valid relation
            // in the case of error
            // some async external request maybe active and
            // there is no complete metadata yet
            if ($metadata) {
                $id = $metadata->getId();
                $version = $metadata->getVersion();
                $this->metas[$id][$version] = $metadata;

                // init wrapper
                $implication[$id]["source"] ??=
                    $this->requests[$mId]["source"];

                // wrapper entry
                $implication[$id]["implication"][$version] = [];

                foreach ($this->requests[$mId]["versions"] as $vId) {
                    if ($this->requests[$vId]["metas"])
                        $this->addMetasImplication(
                            $implication[$id]["implication"][$version],
                            $this->requests[$vId]["metas"]
                        );

                    else
                        $implication[$id]["implication"][$version][] = [
                            "source" =>
                            $this->requests[$vId]["source"]];
                }

            // error handling
            // int index instead of package ID string
            } else $implication[] = [
                "source" =>

                // first match
                // search match by source then
                $this->requests[$mId]["source"]];
        }
    }

    /**
     * Extracts structure.
     *
     * @param array $path Satisfied path.
     * @throws EnvironmentError Environment exception.
     */
    private function extractStructure(array $path): void
    {
        // fake wrapper
        $environment = [$this->config["environment"]["php"]["version"]];
        $metas = [];

        foreach ($path as $id => $version) {
            $metadata = $this->metas[$id][$version];
            $metas[$id] = $metadata;
            $php = $metadata->getEnvironment()["php"];

            // validate version and
            // modules
            if (!Normalizer::getFilteredVersions($environment, $php["version"])) {
                $layers = $metadata->getLayers();

                throw new EnvironmentError(
                    "Can't build the package \"" . $metadata->getId() .
                    "\". The current PHP version \"" . $environment[0]["major"] . "." .
                    $environment[0]["minor"] . "." . $environment[0]["patch"].
                    "\" does not pass the pattern.",
                    $this->getPath($layers["object"]["source"]),
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

                    throw new EnvironmentError(
                        "Can't build the package \"" . $metadata->getId() .
                        "\". It requires the missing module \"$module\".",
                        $this->getPath($layers["object"]["source"]),
                        array_key_first($layers),
                        ["environment", "php", "modules"]
                    );
                }

            Log::info(new Content($metadata->getContent()));
        }

        Group::setExternalMetas($metas);
        Group::setImplication(
            Trace::getTree($this->implication, $path)
        );
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
                $this->getPath(
                    $this->requests[$event->getId()]["source"]
                )
            );

        } elseif ($event instanceof MetadataError) {
            $this->buildImplication();
            $event->setPath(
                $this->getPath(
                    $event->getSource()
                )
            );
        }
    }

    /**
     * Returns event path.
     *
     * @param string $source Source.
     * @return array Path.
     */
    private function getPath(string $source): array
    {
        $implicationPath = Trace::getSourcePath($this->implication, $source);
        $path = [];

        if ($this->config["source"]) {
            $id = array_key_first($implicationPath);

            if ($id) {
                $entry = array_shift($implicationPath);
                $metadata = $this->metas[$id][$entry["version"]];
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
                "breadcrumb" => ["build", "source"],
                "source" => $source
            ];

        } else
            $metadata = Group::getInternalRootMetadata();

        foreach ($implicationPath as $id => $entry) {
            foreach ($metadata?->getLayers() as $layer => $content)
                if (isset($content["structure"])) {
                    $breadcrumb = Structure::getBreadcrumb(
                        $content["structure"],
                        $entry["source"],
                        ["structure"]
                    );

                    if ($breadcrumb) {
                        $path[] = [
                            "layer" => $layer,
                            "breadcrumb" => $breadcrumb,
                            "source" => $entry["source"]
                        ];

                        // take first match
                        break;
                    }
                }

            // next parent
            // last own entry
            if (isset($entry["version"]))
                $metadata = $this->metas[$id][$entry["version"]];
        }

        return $path;
    }
}