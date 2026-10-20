# Release Notes

## [Unreleased](https://github.com/venturedrake/laravel-neto/compare/v0.1.0...1.x)


## [v0.1.0](https://github.com/venturedrake/laravel-neto/compare/...v0.1.0) - 202x-xx-xx

Initial pre-release.

- `Neto` client with header authentication, configurable timeout, retries for transient failures, and optional request logging with a masked API key.
- `Orders` resource: `get`, `find`, `create`, `update`, `cancel`, `status`, `tracking`.
- `Items` and `Customers` resources: `get`, `find`, `create`, `update` (plus `findByEmail` for customers).
- `request()` and `get()` for any other Neto API action.
- Typed exceptions: `ConfigurationException`, `RequestException`, `ResponseException`, `NotFoundException`.
