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

namespace Valvoid\Fusion\Metadata\Internal;

use Valvoid\Fusion\Dir\Dir;
use Valvoid\Fusion\Log\Events\Errors\Lifecycle;
use Valvoid\Fusion\Log\Log;
use Valvoid\Fusion\Metadata\Metadata;
use Valvoid\Fusion\Tasks\Group;

/**
 * Internal metadata.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Internal extends Metadata
{
    /** @var Category Category. */
    private Category $category;

    /**
     * Sets category.
     *
     * @param Category $category Category.
     */
    public function setCategory(Category $category): void
    {
        $this->category = $category;
    }

    /**
     * Returns local development dependency IDs or null if
     * the package has no "fusion.local.php" metadata file.
     *
     * @return ?string[] IDs.
     */
    public function getLocalIds(): null|array
    {
        return $this->content["dependencies"]["local"];
    }

    /**
     * Returns shared development dependency IDs or null if
     * the package has no "fusion.dev.php" metadata file.
     *
     * @return ?string[] IDs.
     */
    public function getDevelopmentIds(): null|array
    {
        return $this->content["dependencies"]["development"];
    }

    /**
     * Returns category.
     *
     * @return Category|null Category.
     */
    public function getCategory(): ?Category
    {
        return $this->category ?? null;
    }

    /**
     * Returns source.
     *
     * @return string Source.
     */
    public function getSource(): string
    {
        return $this->content["source"];
    }

    /**
     * Handles lifecycle error.
     *
     * @param int $errno
     * @param string $message Message.
     * @throws Lifecycle Lifecycle error.
     */
    protected function errorHandlerCallback(int $errno, string $message): void
    {
        ob_clean();

        if ($errno == E_USER_ERROR)
            $this->throwLifecycleError($message);

        $lifecycle = new Lifecycle(
            $message,
            $this->getLifecycleLayer(),
            $this->getLifecycleBreadcrumb()
        );

        match ($errno) {
            E_USER_NOTICE => Log::notice($lifecycle),
            E_USER_WARNING => Log::warning($lifecycle),
            default => Log::info($lifecycle)
        };

        // log
        // print non-error
        ob_flush();
    }

    /**
     * Throws lifecycle error.
     *
     * @param string $message Message.
     * @throws Lifecycle Error.
     */
    protected function throwLifecycleError(string $message): void
    {
        throw new Lifecycle(
            $message,
            $this->getLifecycleLayer(),
            $this->getLifecycleBreadcrumb()
        );
    }

    /**
     * Returns lifecycle layer.
     *
     * @return string Layer.
     */
    private function getLifecycleLayer(): string
    {
        foreach ($this->layers as $layer => $content)
            if (isset($content["lifecycle"][$this->lifecycle["state"]]))
                break;

        return $layer;
    }

    /**
     * Triggers optional lifecycle update callback and returns
     * triggered or not indicator.
     *
     * @return bool Indicator.
     */
    public function onUpdate(): bool
    {
        if (!isset($this->content["lifecycle"]["update"]))
            return false;

        $this->lifecycle = [
            "state" => "update",
            "root" => $this->getSource(),
            "file" => $this->content["lifecycle"]["update"]
        ];

        // break variable scope
        return $this->requireCallback();
    }

    /**
     * Triggers optional lifecycle delete callback and returns
     * triggered or not indicator.
     *
     * @return bool Indicator.
     */
    public function onDelete(): bool
    {
        if (!isset($this->content["lifecycle"]["delete"]))
            return false;

        $this->lifecycle = [
            "state" => "delete",
            "root" => $this->getSource(),
            "file" => $this->content["lifecycle"]["delete"]
        ];

        // break variable scope
        return $this->requireCallback();
    }

    /**
     * Triggers optional lifecycle migrate callback and returns
     * triggered or not indicator.
     *
     * @return bool Indicator.
     */
    public function onMigrate(): bool
    {
        if (!isset($this->content["lifecycle"]["migrate"]))
            return false;

        $id = $this->getId();
        $metadata = Group::getExternalMetas()[$id];
        $this->lifecycle = [
            "state" => "migrate",
            "root" => $this->getSource(),
            "file" => $this->content["lifecycle"]["migrate"]
        ];

        // break variable scope
        return $this->requireCallback([
            "dir" => Dir::getPackagesDir() ."/$id",
            "version" => $metadata->getVersion()
        ]);
    }
}