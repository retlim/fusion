# Contributing

Fusion is developed using the trunk-based approach, featuring an infinite 
development branch and version-encapsulated release branches.

## Development Branch

The `main` branch serves as the infinite development branch, containing the 
upcoming release.

## Release Branches

Release branches represent semantic minor versions with an indefinite number 
of patch, release, and build parts. For example, the release branch 
`1.0` may contain, along with the initial version `1.0.0`, the following 
versions:

- `1.0.2`
- `1.0.1`

## Merge Requests

Each merge request serves as confirmation to transfer ownership to the project
and must meet the following criteria:

- Own intellectual property.
- Neutral content. Free from political bias, for example.
- Pure PHP without exotic extensions.

### Bugfix

Create a merge request to a release branch where the bug occurs first.

### Feature

Create an issue for discussion before submitting a merge request to the `main`
branch.