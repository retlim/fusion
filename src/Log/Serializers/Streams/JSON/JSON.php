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

namespace Valvoid\Fusion\Log\Serializers\Streams\JSON;

use Valvoid\Fusion\Log\Events\Errors\Config;
use Valvoid\Fusion\Log\Events\Errors\Deadlock;
use Valvoid\Fusion\Log\Events\Errors\Environment;
use Valvoid\Fusion\Log\Events\Errors\Error;
use Valvoid\Fusion\Log\Events\Infos\Error as ErrorInfo;
use Valvoid\Fusion\Log\Events\Errors\Lifecycle;
use Valvoid\Fusion\Log\Events\Errors\Metadata;
use Valvoid\Fusion\Log\Events\Errors\Request;
use Valvoid\Fusion\Log\Events\Event;
use Valvoid\Fusion\Log\Events\Infos\Content;
use Valvoid\Fusion\Log\Events\Infos\Id;
use Valvoid\Fusion\Log\Events\Infos\Name;
use Valvoid\Fusion\Log\Events\Level;
use Valvoid\Fusion\Log\Serializers\Streams\Stream;

/**
 * JSON stream serializer.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class JSON implements Stream
{
    /** @var Level  */
    private Level $threshold;

    /**
     * Constructs the JSON stream serializer.
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
            $this->encodeMessage($level, $event);

        elseif ($event instanceof Deadlock)
            $this->encodeDeadlock($level, $event);

        elseif ($event instanceof Environment)
            $this->encodeEnvironment($level, $event);

        elseif ($event instanceof Error)
            $this->encodeError($level, $event);

        elseif ($event instanceof ErrorInfo)
            $this->encodeErrorInfo($level, $event);

        elseif ($event instanceof Metadata)
            $this->encodeMetadata($level, $event);

        elseif ($event instanceof Request)
            $this->encodeRequest($level, $event);

        elseif ($event instanceof Lifecycle)
            $this->encodeLifecycle($level, $event);

        elseif ($event instanceof Content)
            $this->encodeContent($level, $event);

        elseif ($event instanceof Config)
            $this->encodeConfig($level, $event);

        elseif ($event instanceof Id)
            $this->encodeId($level, $event);

        elseif ($event instanceof Name)
            $this->encodeName($level, $event);

        // custom unknown
        // generic message fallback
        else
            $this->encodeGeneric($level, $event->__toString());
    }

    /**
     * Encodes string message.
     *
     * @param Level $level Level.
     * @param string $message Message.
     */
    private function encodeMessage(Level $level, string $message): void
    {
        echo json_encode([
            "category" => "generic", // no category
            "type" => "string",
            "payload" => [
                "message" => $message
            ],
            "level" => [
                "name" => strtolower($level->name),
                "ordinal" => $level->value
            ]
        ]);
    }

    /**
     * Encodes generic string message.
     *
     * @param Level $level Level.
     * @param string $message Message.
     */
    private function encodeGeneric(Level $level, string $message): void
    {
        echo json_encode([
            "category" => "generic", // no category
            "type" => "string",
            "payload" => [
                "message" => $message
            ],
            "level" => [
                "name" => strtolower($level->name),
                "ordinal" => $level->value
            ]
        ]);
    }

    /**
     * Encodes environment event.
     *
     * @param Level $level Level.
     * @param Environment $environment Environment.
     */
    private function encodeEnvironment(Level $level, Environment $environment): void
    {
        echo json_encode([
            "category" => "error",
            "type" => "environment",
            "payload" => [
                "path" => $environment->getPath(),
                "layer" => $environment->getLayer(),
                "breadcrumb" => $environment->getBreadcrumb(),
                "message" => $environment->getMessage()
            ],
            "level" => [
                "name" => strtolower($level->name),
                "ordinal" => $level->value
            ]
        ]);
    }

    /**
     * Encodes error event.
     *
     * @param Level $level Level.
     * @param Error $error Error.
     */
    private function encodeError(Level $level, Error $error): void
    {
        echo json_encode([
            "category" => "error",
            "type" => "error",
            "payload" => [
                "trace" => $error->getTrace(),
                "line" => $error->getLine(),
                "file" => $error->getFile(),
                "message" => $error->getMessage()
            ],
            "level" => [
                "name" => strtolower($level->name),
                "ordinal" => $level->value
            ]
        ]);
    }

    /**
     * Encodes error info event.
     *
     * @param Level $level Level.
     * @param ErrorInfo $info Info.
     */
    private function encodeErrorInfo(Level $level, ErrorInfo $info): void
    {
        echo json_encode([
            "category" => "info",
            "type" => "error",
            "payload" => [
                "path" => $info->getPath(),
                "code" => $info->getCode(),
                "message" => $info->getMessage()
            ],
            "level" => [
                "name" => strtolower($level->name),
                "ordinal" => $level->value
            ]
        ]);
    }

    /**
     * Encodes deadlock event.
     *
     * @param Level $level Level.
     * @param Deadlock $deadlock Event.
     */
    private function encodeDeadlock(Level $level, Deadlock $deadlock): void
    {
        echo json_encode([
            "category" => "error",
            "type" => "deadlock",
            "payload" => [
                "message" => $deadlock->getMessage(),
                "paths" => [
                    "built" => $deadlock->getLockedPath(),
                    "conflict" => $deadlock->getConflictPath()
                ],
                "layers" => [
                    "built" => $deadlock->getLockedLayer(),
                    "conflict" => $deadlock->getConflictLayer()
                ],
                "breadcrumbs" => [
                    "built" => $deadlock->getLockedBreadcrumb(),
                    "conflict" => $deadlock->getConflictBreadcrumb()
                ]
            ],
            "level" => [
                "name" => strtolower($level->name),
                "ordinal" => $level->value
            ]
        ]);
    }

    /**
     * Encodes metadata event.
     *
     * @param Level $level Level.
     * @param Metadata $metadata Metadata.
     */
    private function encodeMetadata(Level $level, Metadata $metadata): void
    {
        echo json_encode([
            "category" => "error",
            "type" => "metadata",
            "payload" => [
                "path" => $metadata->getPath(),
                "layer" => $metadata->getLayer(),
                "breadcrumb" => $metadata->getBreadcrumb(),
                "message" => $metadata->getMessage()
            ],
            "level" => [
                "name" => strtolower($level->name),
                "ordinal" => $level->value
            ]
        ]);
    }

    /**
     * Encodes request event.
     *
     * @param Level $level Level.
     * @param Request $request Request.
     */
    private function encodeRequest(Level $level, Request $request): void
    {
        echo json_encode([
            "category" => "error",
            "type" => "request",
            "payload" => [
                "path" => $request->getPath(),
                "sources" => $request->getSources(),
                "code" => $request->getCode(),
                "message" => $request->getMessage()
            ],
            "level" => [
                "name" => strtolower($level->name),
                "ordinal" => $level->value
            ]
        ]);
    }

    /**
     * Encodes lifecycle event.
     *
     * @param Level $level Level.
     * @param Lifecycle $lifecycle Lifecycle.
     */
    private function encodeLifecycle(Level $level, Lifecycle $lifecycle): void
    {
        echo json_encode([
            "category" => "error",
            "type" => "lifecycle",
            "payload" => [
                "path" => $lifecycle->getPath(),
                "layer" => $lifecycle->getLayer(),
                "breadcrumb" => $lifecycle->getBreadcrumb(),
                "message" => $lifecycle->getMessage()
            ],
            "level" => [
                "name" => strtolower($level->name),
                "ordinal" => $level->value
            ]
        ]);
    }

    /**
     * Encodes parsed metadata content event.
     *
     * @param Level $level Level.
     * @param Content $content Metadata.
     */
    private function encodeContent(Level $level, Content $content): void
    {
        $info = [
            "category" => "info",
            "type" => "content",
            "payload" => [
                "id" => $content->getId(),
                "version" => $content->getVersion()
            ],
            "level" => [
                "name" => strtolower($level->name),
                "ordinal" => $level->value
            ]
        ];

        if ($this->threshold == Level::VERBOSE)
            $info["payload"] += [
                "name" => $content->getName(),
                "description" => $content->getDescription(),
                "source" => $content->getSource(),
                "dir" => $content->getDir()
            ];

        echo json_encode($info);
    }

    /**
     * Encodes config event.
     *
     * @param Level $level Level.
     * @param Config $config Config.
     */
    private function encodeConfig(Level $level, Config $config): void
    {
        echo json_encode([
            "category" => "error",
            "type" => "config",
            "payload" => [
                "layer" => $config->getLayer(),
                "breadcrumb" => $config->getBreadcrumb(),
                "message" => $config->getMessage()
            ],
            "level" => [
                "name" => strtolower($level->name),
                "ordinal" => $level->value
            ]
        ]);
    }

    /**
     * Encodes ID event.
     *
     * @param Level $level Level.
     * @param Id $id ID.
     */
    private function encodeId(Level $level, Id $id): void
    {
        echo json_encode([
            "category" => "info",
            "type" => "id",
            "payload" => [
                "id" => $id->getId()
            ],
            "level" => [
                "name" => strtolower($level->name),
                "ordinal" => $level->value
            ]
        ]);
    }

    /**
     * Encodes name event.
     *
     * @param Level $level Level.
     * @param Name $name Name.
     */
    private function encodeName(Level $level, Name $name): void
    {
        echo json_encode([
            "category" => "info",
            "type" => "name",
            "payload" => [
                "name" => $name->getName()
            ],
            "level" => [
                "name" => strtolower($level->name),
                "ordinal" => $level->value
            ]
        ]);
    }
}