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

namespace Valvoid\Fusion\Log;

use Valvoid\Fusion\Config\Config;
use Valvoid\Fusion\Log\Events\Event;
use Valvoid\Fusion\Log\Events\Infos\Error as ErrorInfo;
use Valvoid\Fusion\Log\Events\Interceptor;
use Valvoid\Fusion\Log\Events\Level;
use Valvoid\Fusion\Log\Serializers\Files\File;
use Valvoid\Fusion\Log\Serializers\Streams\Stream;
use Valvoid\Fusion\Tasks\Task;

/**
 * Event log.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Log
{
    /** @var ?Log Runtime instance. */
    private static ?Log $instance = null;

    /** @var File[]|Stream[] Output formatters. */
    private array $serializers = [];

    /** @var Interceptor Event interceptor. */
    private Interceptor $interceptor;

    /** Constructs the log. */
    private function __construct()
    {
        $config = Config::get("log");

        foreach ($config["serializers"] as $serializer)
            $this->serializers[] = new $serializer["serializer"]($serializer);

        // verbose debug log
        // wrap all to extended serializer info
        set_error_handler(function ($code, $message) {
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

            // clear self-entry
            unset($backtrace[0]);

            // top down flow
            $backtrace = array_reverse($backtrace);

            self::verbose(new ErrorInfo($message, $code, $backtrace));
        });
    }

    /**
     * Returns initial instance or true for recycled instance.
     *
     * @return Log|bool Instance or recycled.
     */
    public static function ___init(): bool|Log
    {
        if (self::$instance)
            return true;

        self::$instance = new self;

        return self::$instance;
    }

    /**
     * Destroys the log instance.
     *
     * @return bool True for success.
     */
    public function destroy(): bool
    {
        self::$instance = null;

        return restore_error_handler();
    }

    /**
     * Adds task as event interceptor.
     *
     * @param Task $task Task.
     */
    public function addInterceptor(Task $task): void
    {
        if (is_subclass_of($task, Interceptor::class))
            $this->interceptor = $task;
    }

    /** Removes event interceptor. */
    public function removeInterceptor(): void
    {
        unset($this->interceptor);
    }

    /**
     * Logs event.
     *
     * @param Level $level Level.
     * @param Event|string $event Event.
     */
    private function log(Level $level, Event|string $event): void
    {
        // extend manually
        if (isset($this->interceptor))
            $this->interceptor->extend($event);

        foreach (self::$instance->serializers as $serializer)
            $serializer->log($level, $event);
    }

    /**
     * Logs error event.
     *
     * @param Event|string $event Event.
     */
    public static function error(Event|string $event): void
    {
        self::$instance->log(Level::ERROR, $event);
    }

    /**
     * Logs warning event.
     *
     * @param Event|string $event Event.
     */
    public static function warning(Event|string $event): void
    {
        self::$instance->log(Level::WARNING, $event);
    }

    /**
     * Logs notice event.
     *
     * @param Event|string $event Event.
     */
    public static function notice(Event|string $event): void
    {
        self::$instance->log(Level::NOTICE, $event);
    }

    /**
     * Logs info event.
     *
     * @param Event|string $event Event.
     */
    public static function info(Event|string $event): void
    {
        self::$instance->log(Level::INFO, $event);
    }

    /**
     * Logs verbose event.
     *
     * @param Event|string $event Event.
     */
    public static function verbose(Event|string $event): void
    {
        self::$instance->log(Level::VERBOSE, $event);
    }

    /**
     * Logs debug event.
     *
     * @param Event|string $event Event.
     */
    public static function debug(Event|string $event): void
    {
        self::$instance->log(Level::DEBUG, $event);
    }
}