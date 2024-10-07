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

namespace Valvoid\Fusion\Log\Serializers\Streams\Terminal;

use Valvoid\Fusion\Log\Events\Errors\Config;
use Valvoid\Fusion\Log\Events\Errors\Deadlock;
use Valvoid\Fusion\Log\Events\Errors\Environment;
use Valvoid\Fusion\Log\Events\Errors\Error;
use Valvoid\Fusion\Log\Events\Errors\Lifecycle;
use Valvoid\Fusion\Log\Events\Errors\Metadata;
use Valvoid\Fusion\Log\Events\Errors\Request;
use Valvoid\Fusion\Log\Events\Event;
use Valvoid\Fusion\Log\Events\Infos\Content;
use Valvoid\Fusion\Log\Events\Infos\Error as ErrorInfo;
use Valvoid\Fusion\Log\Events\Infos\Id;
use Valvoid\Fusion\Log\Events\Infos\Name;
use Valvoid\Fusion\Log\Events\Level;
use Valvoid\Fusion\Log\Serializers\Streams\Stream;

/**
 * Terminal stream serializer.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Terminal implements Stream
{
    /** @var Level  */
    private Level $threshold;

    /**
     * Constructs the terminal stream serializer.
     *
     * @param array $config Config.
     */
    public function __construct(array $config)
    {
        $this->threshold = $config["threshold"];
    }

    /** @inheritdoc */
    public function log(Level $level, Event|string $event): void
    {
        if ($level->value > $this->threshold->value)
            return;

        if (is_string($event))
            $this->formatMessage($level, $event);

        elseif ($event instanceof Error)
            $this->formatError($level, $event);

        elseif ($event instanceof Deadlock)
            $this->formatDeadlock($level, $event);

        elseif ($event instanceof Environment)
            $this->formatEnvironment($level, $event);

        elseif ($event instanceof Lifecycle)
            $this->formatLifecycle($level, $event);

        elseif ($event instanceof Metadata)
            $this->formatMetadata($level, $event);

        elseif ($event instanceof Request)
            $this->formatRequest($level, $event);

        elseif ($event instanceof Content)
            $this->formatContent($event);

        elseif ($event instanceof ErrorInfo)
            $this->formatErrorInfo($level, $event);

        elseif ($event instanceof Config)
            $this->formatConfig($level, $event);

        elseif ($event instanceof Id)
            $this->formatId($level, $event);

        elseif ($event instanceof Name)
            $this->formatName($level, $event);

        // custom unknown
        // generic message fallback
        else
            $this->formatGeneric($level, $event->__toString());
    }

    /**
     * Formats string message.
     *
     * @param Level $level Level.
     * @param string $message Message.
     */
    private function formatMessage(Level $level, string $message): void
    {
        echo "\n$message";
    }

    /**
     * Formats generic string message.
     *
     * @param Level $level Level.
     * @param string $message Message.
     */
    private function formatGeneric(Level $level, string $message): void
    {
        $this->formatCategory($level, "generic ");

        echo "\n$message";
    }

    /**
     * Formats error event.
     *
     * @param Level $level Level.
     * @param Error $error Error.
     */
    private function formatError(Level $level, Error $error): void
    {
        $this->formatCategory($level, "");

        foreach (array_reverse($error->getTrace()) as $entry)
            echo "\n\033[4min\033[0m: \033[0;4m" . $entry["line"] . " - " . $entry["file"] .
                "\n\033[0mat: " . $entry["class"] . $entry["type"] . $entry["function"] . "()" ;

        echo "\n\033[4min\033[0m: \033[0;4m" . $error->getLine() . " - " . $error->getFile() .
            "\n\033[0mis: " . $error->getMessage();
    }

    /**
     * Formats category.
     *
     * @param Level $level Level.
     * @param string $prefix Prefix.
     */
    private function formatCategory(Level $level, string $prefix): void
    {
        echo "\n\n" . match ($level) {
            Level::ERROR => "\033[1;4;31m" . $prefix . "error\033[0m:",
            Level::WARNING => "\033[1;4;33m" . $prefix . "warning\033[0m:",
            Level::NOTICE => "\033[1;4;33m" . $prefix . "notice\033[0m:",
            Level::INFO => "\033[1;4m" . $prefix . "info\033[0m:",
            Level::VERBOSE => "\033[1;4m" . $prefix . "verbose\033[0m:",
            Level::DEBUG => "\033[1;4m" . $prefix . "debug\033[0m:"
        };
    }

    /**
     * Formats path.
     *
     * @param array{
     *     layer: string,
     *     breadcrumb: string[],
     *     source: string
     * } $path Path.
     */
    private function formatPath(array $path): void
    {
        foreach ($path as $entry)
            echo "\n\033[4min\033[0m: \033[0;4m" . $entry["layer"] .
                "\n\033[0mat: " . implode(" | ", $entry["breadcrumb"]) .
                "\nas: " . $entry["source"];
    }

    /**
     * Formats deadlock event.
     *
     * @param Level $level Level.
     * @param Deadlock $deadlock Event.
     */
    private function formatDeadlock(Level $level, Deadlock $deadlock): void
    {
        // error
        $this->formatCategory($level, "deadlock");
        $this->formatPath($deadlock->getLockedPath());

        echo "\n\033[4min\033[0m: \033[0;4m" . $deadlock->getLockedLayer() .
            "\n\033[0mat: " . implode(" | ", $deadlock->getLockedBreadcrumb()) .
            "\n    ---";

        $this->formatPath($deadlock->getConflictPath());

        echo "\n\033[4min\033[0m: \033[0;4m" . $deadlock->getConflictLayer() .
            "\n\033[0mat: " . implode(" | ", $deadlock->getConflictBreadcrumb()) .
            "\nis: " . $deadlock->getMessage();
    }

    /**
     * Formats environment event.
     *
     * @param Level $level Level.
     * @param Environment $environment Environment.
     */
    private function formatEnvironment(Level $level, Environment $environment): void
    {
        $this->formatCategory($level, "environment ");
        $this->formatPath($environment->getPath());

        echo "\n\033[4min\033[0m: \033[0;4m" . $environment->getLayer() .
            "\n\033[0mat: " . implode(" | ", $environment->getBreadcrumb()) .
            "\nis: " . $environment->getMessage();
    }


    /**
     * Formats lifecycle event.
     *
     * @param Level $level Level.
     * @param Lifecycle $lifecycle Lifecycle.
     */
    private function formatLifecycle(Level $level, Lifecycle $lifecycle): void
    {
        $this->formatCategory($level, "lifecycle ");
        $this->formatPath($lifecycle->getPath());

        echo "\n\033[4min\033[0m: \033[0;4m" . $lifecycle->getLayer() .
            "\n\033[0mat: " . implode(" | ", $lifecycle->getBreadcrumb()) .
            "\nis: " . $lifecycle->getMessage();
    }

    /**
     * Formats metadata event.
     *
     * @param Level $level Level.
     * @param Metadata $metadata Metadata.
     */
    private function formatMetadata(Level $level, Metadata $metadata): void
    {
        $this->formatCategory($level, "metadata ");
        $this->formatPath($metadata->getPath());

        echo "\n\033[4min\033[0m: \033[0;4m" . $metadata->getLayer() . "\033[0m";

        $index = $metadata->getBreadcrumb();

        // meta maybe is empty
        // no index
        if ($index) {
            echo "\nat: ";

            // has row
            $row = $metadata->getRow();

            if ($row)
                echo "$row - ";

            echo implode(" | ", $index);
        }

        echo "\nis: " . $metadata->getMessage();
    }

    /**
     * Formats request event.
     *
     * @param Level $level Level.
     * @param Request $request Request.
     */
    private function formatRequest(Level $level, Request $request): void
    {
        $this->formatCategory($level, "request ");
        $this->formatPath($request->getPath());

        foreach ($request->getSources() as $source)
            echo "\n\033[4mby\033[0m: \033[0;4m$source";

        echo "\n\033[0mis: " . $request->getMessage();
    }

    /**
     * Formats parsed metadata content event.
     *
     * @param Content $content Content.
     */
    private function formatContent(Content $content): void
    {
        // complete and
        // short
        echo ($this->threshold->value > Level::INFO->value) ?
            "\n\n\033[4m". $content->getId() . "\033[0m"  . " | " . $content->getVersion() .
            "\nname: " . $content->getName() .
            "\ndescription: " . $content->getDescription() .
            "\ntype: ". $content->getType() . " | " .
            "\nsource: " . $content->getSource() .
            "\ndir: " . $content->getDir() :

            "\n[" . ($content->getType() == "internal" ? '=' : '+') . "| " .
            $content->getId() . " | " . $content->getVersion();
    }

    /**
     * Formats config event.
     *
     * @param Level $level Level.
     * @param Config $config Config.
     */
    private function formatConfig(Level $level, Config $config): void
    {
        $this->formatCategory($level, "config ");

        echo "\n\033[4min\033[0m: \033[0;4m" . $config->getLayer() .
            "\n\033[0mat: " . implode(" | ", $config->getBreadcrumb()) .
            "\nis: " . $config->getMessage();
    }

    /**
     * Formats info event.
     *
     * @param Level $level Level.
     * @param ErrorInfo $info Info.
     */
    private function formatErrorInfo(Level $level, ErrorInfo $info): void
    {
        echo "\n";

        foreach ($info->getPath() as $entry)
            echo "\n\033[4min\033[0m: \033[0;4m" . $entry["line"] . " - " . $entry["file"] .
                "\n\033[0mat: " . ($entry["class"] ?? "") . ($entry["type"] ?? "") .
                $entry["function"] . "()" ;

        echo "\nis: " . $info->getMessage() . " | code: " . $info->getCode();
    }

    /**
     * Formats ID event.
     *
     * @param Level $level Level.
     * @param Id $id ID.
     */
    private function formatId(Level $level, Id $id): void
    {
        echo "\nexecute \033[4m" . $id->getId() . "\033[0m id";
    }

    /**
     * Formats name event.
     *
     * @param Level $level Level.
     * @param Name $name Name.
     */
    private function formatName(Level $level, Name $name): void
    {
        echo "\n\n\033[1;4;32m" . $name->getName() . "\033[0m";
    }
}