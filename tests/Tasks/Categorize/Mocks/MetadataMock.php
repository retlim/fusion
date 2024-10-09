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

namespace Valvoid\Fusion\Tests\Tasks\Categorize\Mocks;

use ReflectionClass;
use ReflectionException;
use Valvoid\Fusion\Metadata\External\External;
use Valvoid\Fusion\Metadata\Internal\Internal;
use Valvoid\Fusion\Tasks\Group;

/**
 * Mocked internal and external metadata.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class MetadataMock
{
    /**
     * @throws ReflectionException
     */
    public static function addMockedMetadata(): void
    {
        $internal = [];
        $reflection = new ReflectionClass(Internal::class);
        $metadata = $reflection->newInstanceWithoutConstructor();
        $content = $reflection->getProperty('content');

        $content->setValue($metadata, [
            "id" => "metadata1",
            "name" => "metadata1",
            "description" => "metadata1",
            "source" => "metadata1",
            "dir" => __DIR__,
            "version" => "1.0.0"
        ]);

        $internal[] = $metadata;

        $reflection = new ReflectionClass(Internal::class);
        $metadata = $reflection->newInstanceWithoutConstructor();
        $content = $reflection->getProperty('content');

        $content->setValue($metadata, [
            "id" => "metadata2",
            "name" => "metadata2",
            "description" => "metadata2",
            "source" => "metadata2",
            "dir" => __DIR__,
            "version" => "1.0.0"
        ]);

        $internal[] = $metadata;

        Group::setInternalMetas($internal);

        $external = [];
        $reflection = new ReflectionClass(External::class);
        $metadata = $reflection->newInstanceWithoutConstructor();
        $content = $reflection->getProperty('content');

        $content->setValue($metadata, [
            "id" => "metadata1",
            "name" => "metadata1",
            "description" => "metadata1",
            "source" => "metadata1",
            "dir" => __DIR__,
            "version" => "1.0.1"
        ]);

        $external[] = $metadata;

        $reflection = new ReflectionClass(External::class);
        $metadata = $reflection->newInstanceWithoutConstructor();
        $content = $reflection->getProperty('content');

        $content->setValue($metadata, [
            "id" => "metadata2",
            "name" => "metadata2",
            "description" => "metadata2",
            "source" => "metadata2",
            "dir" => __DIR__ . "/whatever",
            "version" => "1.0.0"
        ]);

        $external[] = $metadata;

        Group::setExternalMetas($external);
    }
}