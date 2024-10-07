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

namespace Valvoid\Fusion\Log\Serializers\Files\JSON;

use Valvoid\Fusion\Dir\Dir;
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
use Valvoid\Fusion\Log\Serializers\Files\File;

/**
 * JSON file serializer.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class JSON implements File
{
    /** @var Level  */
    private Level $threshold;

    /** @var string Filename. */
    private string $filename;

    /**
     * Constructs the JSON file serializer.
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->threshold = $config["threshold"];
        $this->filename = $config["filename"];
    }

    /**
     * @inheritdoc
     * @throws Error
     */
    public function log(Level $level, Event|string $event): void
    {
        if ($level->value > $this->threshold->value)
            return;

        if (is_string($event))
            $content = self::getMessage($level, $event);

        elseif ($event instanceof Deadlock)
            $content = self::getDeadlock($level, $event);

        elseif ($event instanceof Environment)
            $content = self::getEnvironment($level, $event);

        elseif ($event instanceof Metadata)
            $content = self::getMetadata($level, $event);

        elseif ($event instanceof Request)
            $content = self::getRequest($level, $event);

        elseif ($event instanceof Error)
            $content = self::getError($level, $event);

        elseif ($event instanceof ErrorInfo)
            $content = self::getErrorInfo($level, $event);

        elseif ($event instanceof Content)
            $content = self::getContent($level, $event);

        elseif ($event instanceof Lifecycle)
            $content = self::getLifecycle($level, $event);

        elseif ($event instanceof Config)
            $content = self::getConfig($level, $event);

        elseif ($event instanceof Id)
            $content = self::getID($level, $event);

        elseif ($event instanceof Name)
            $content = self::getName($level, $event);

        // custom unknown
        // generic message fallback
        else
            $content = self::getGeneric($level, $event->__toString());

        // cache dir is dynamic
        $dir = Dir::getCacheDir() . "/log";
        $file = "$dir/$this->filename";

        if (is_file($file)) {
            $events = file_get_contents($file);

            if ($events === false)
                throw new Error(
                    "Can't read the file \"$file\"."
                );

            $events = json_decode($events);

            if ($events === null)
                throw new Error(
                    "Can't decode the file \"$file\". " .
                    json_last_error_msg()
                );

            if (is_array($events))
                $events[] = $content;

            else
                $events = [$content];

        } else {
            Dir::createDir($dir);

            // has always wrapper
            $events = [$content];
        }

        $events = json_encode($events,

            // make raw content readable for the eye
            JSON_PRETTY_PRINT);

        if ($events === false)
            throw new Error(
                "Can't encode events for the file \"$file\"."
            );

        if (!file_put_contents($file, $events))
            throw new Error(
                "Can't write to the file \"$file\"."
            );
    }

    /**
     * Returns string message.
     *
     * @param Level $level Level.
     * @param string $message Message.
     * @return array
     */
    private function getMessage(Level $level, string $message): array
    {
        return [
            "date" => date("Y.m.d_H:i:s"),
            "category" => "generic", // no category
            "type" => "string",
            "payload" => [
                "message" => $message
            ],
            "level" => [
                "name" => strtolower($level->name),
                "ordinal" => $level->value
            ]
        ];
    }

    /**
     * Returns generic string message.
     *
     * @param Level $level Level.
     * @param string $message Message.
     * @return array
     */
    private function getGeneric(Level $level, string $message): array
    {
        return [
            "date" => date("Y.m.d_H:i:s"),
            "category" => "generic", // no category
            "type" => "string",
            "payload" => [
                "message" => $message
            ],
            "level" => [
                "name" => strtolower($level->name),
                "ordinal" => $level->value
            ]
        ];
    }

    /**
     * Returns environment event.
     *
     * @param Level $level Level.
     * @param Environment $environment Environment.
     * @return array
     */
    private function getEnvironment(Level $level, Environment $environment): array
    {
        return [
            "date" => date("Y.m.d_H:i:s"),
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
        ];
    }

    /**
     * Returns lifecycle event.
     *
     * @param Level $level Level.
     * @param Lifecycle $lifecycle Lifecycle.
     */
    private function getLifecycle(Level $level, Lifecycle $lifecycle): array
    {
        return [
            "date" => date("Y.m.d_H:i:s"),
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
        ];
    }

    /**
     * Returns deadlock event.
     *
     * @param Level $level Level.
     * @param Deadlock $deadlock Event.
     * @return array
     */
    private function getDeadlock(Level $level, Deadlock $deadlock): array
    {
        return [
            "date" => date("Y.m.d_H:i:s"),
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
        ];
    }

    /**
     * Returns metadata event.
     *
     * @param Level $level Level.
     * @param Metadata $metadata Meta.
     * @return array
     */
    private function getMetadata(Level $level, Metadata $metadata): array
    {
        return [
            "date" => date("Y.m.d_H:i:s"),
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
        ];
    }

    /**
     * Returns request event.
     *
     * @param Level $level Level.
     * @param Request $request Request.
     * @return array
     */
    private function getRequest(Level $level, Request $request): array
    {
        return [
            "date" => date("Y.m.d_H:i:s"),
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
        ];
    }

    /**
     * Returns error event.
     *
     * @param Level $level Level.
     * @param Error $error Error.
     */
    private function getError(Level $level, Error $error): array
    {
        return [
            "date" => date("Y.m.d_H:i:s"),
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
        ];
    }

    /**
     * Returns error info event.
     *
     * @param Level $level Level.
     * @param ErrorInfo $info Info.
     */
    private function getErrorInfo(Level $level, ErrorInfo $info): array
    {
        return [
            "date" => date("Y.m.d_H:i:s"),
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
        ];
    }

    /**
     * Returns parsed metadata content info event.
     *
     * @param Level $level Level.
     * @param Content $content Content.
     * @return array Info.
     */
    private function getContent(Level $level, Content $content): array
    {
        $info = [
            "date" => date("Y.m.d_H:i:s"),
            "category" => "info",
            "type" => "package",
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

        return $info;
    }

    /**
     * Returns config event.
     *
     * @param Level $level Level.
     * @param Config $config Config.
     * @return array
     */
    private function getConfig(Level $level, Config $config): array
    {
        return [
            "date" => date("Y.m.d_H:i:s"),
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
        ];
    }

    /**
     * Returns ID event.
     *
     * @param Level $level Level.
     * @param Id $id ID.
     * @return array
     */
    private function getID(Level $level, Id $id): array
    {
        return [
            "date" => date("Y.m.d_H:i:s"),
            "category" => "info",
            "type" => "id",
            "payload" => [
                "id" => $id->getId()
            ],
            "level" => [
                "name" => strtolower($level->name),
                "ordinal" => $level->value
            ]
        ];
    }

    /**
     * Returns name event.
     *
     * @param Level $level Level.
     * @param Name $name Name.
     * @return array
     */
    private function getName(Level $level, Name $name): array
    {
        return [
            "date" => date("Y.m.d_H:i:s"),
            "category" => "info",
            "type" => "name",
            "payload" => [
                "name" => $name->getName()
            ],
            "level" => [
                "name" => strtolower($level->name),
                "ordinal" => $level->value
            ]
        ];
    }
}