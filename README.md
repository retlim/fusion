> This repository is a replica and is used for internal testing. If
> you are interested in the current development, visit the
> repository https://gitlab.com/valvoid/fusion/php/code.

# Fusion

Fusion is a package manager for PHP-based projects. It increases productivity by
handling all the processable redundant micromanagement that occurs during
development, deployment, and maintenance.

## Documentation

The separated [documentation repository](https://gitlab.com/valvoid/fusion/php/docs)
also has the [user-friendly output](https://valvoid.com/registry/packages/1/fusion/docs) and contains content such as:

### Architecture

The architectural approach is partly based on graph theory, where everything is an
abstract nth graph vertex. In this context, each vertex is a modular package node.
Your current project, each of its dependencies, and even the package manager
itself, everything is a modular package node.

Since all these nodes are arbitrary linkable to each other and to themselves, among
others, the following automated key features are possible:

- Nested and standalone package logic at the same time.
- Recursive project updates/upgrades.
- Common top-down stackable package extensions.
    - Even recursive.
- Separated environment builds.

### Integration

As mentioned above, Fusion is also a [package](https://valvoid.com/registry/packages/1/fusion) itself, and in addition, it 
provides the interface-based config for custom implementations, such as:

- Management logic.
  - Replace the default download or build implementation.
- Remote and local package hubs.
  - Add own registry.
- Log serializers.
  - Get whatever you implement output.

Configure, extend, customize, use as a dependency or standalone, Fusion is easy to
integrate and adapts seamlessly.

## Registry

For default packages, see the [default registry](https://valvoid.com/registry) page.

## Contribution

Each merge request serves as confirmation to transfer ownership to the project 
and must meet the following criteria:

- Own intellectual property.
- Neutral content. Free from political bias, for example.
- Pure PHP without exotic extensions.

See the contributing file if these criteria apply to you.

## License

Fusion. A package manager for PHP-based projects.  
Copyright Valvoid

This program is free software: you can redistribute it and/or modify it under the 
terms of the GNU General Public License as published by the Free Software Foundation, 
either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY 
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A 
PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this 
program. If not, see [licenses](https://www.gnu.org/licenses/).
