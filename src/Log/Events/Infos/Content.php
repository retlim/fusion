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

namespace Valvoid\Fusion\Log\Events\Infos;

use Valvoid\Fusion\Log\Events\Event;

/**
 * Parsed metadata content info.
 *
 * @Copyright Valvoid
 * @license GNU GPLv3
 */
class Content implements Event
{
    /** @var string ID. */
    private string $id;

    /** @var string Type. */
    private string $type;

    /** @var string Version. */
    private string $version;

    /** @var string Name. */
    private string $name;

    /** @var string Source. */
    private string $description;

    /** @var string Source. */
    private string $source;

    /** @var string Directory. */
    private string $dir;

    /**
     * Constructs the content info.
     *
     * @param array $content Content.
     */
    public function __construct(array $content)
    {
        $this->id = $content["id"];
        $this->version = $content["version"];
        $this->name = $content["name"];
        $this->description = $content["description"];
        $this->dir = $content["dir"];

        if (is_array($content["source"])) {
            $this->type = "external";
            $this->source = $content["source"]["api"] .
                $content["source"]["path"] . "/" .
                $content["source"]["prefix"] .
                $content["source"]["reference"];

        } else {
            $this->type = "internal";
            $this->source = $content["source"];
        }
    }

    /**
     * Returns ID.
     *
     * @return string ID.
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Returns version.
     *
     * @return string Version.
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * Returns name.
     *
     * @return string Name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Returns type.
     *
     * @return string Type.
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Returns description.
     *
     * @return string Description.
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Returns directory.
     *
     * @return string Directory.
     */
    public function getDir(): string
    {
        return $this->dir;
    }

    /**
     * Returns source.
     *
     * @return string Source.
     */
    public function getSource(): string
    {
        return $this->source;
    }

    /**
     * Returns info as string.
     *
     * @return string Info.
     */
    public function __toString(): string
    {
        return "id: ". $this->getId() .
            "\nversion: ". $this->getVersion() .
            "\nname: ". $this->getName() .
            "\ndescription: ". $this->getDescription() .
            "\ntype: ". $this->getType() .
            "\nsource: ". $this->getSource() .
            "\ndir: ". $this->getDir();
    }
}