# Changelog

## [Unreleased](https://codeberg.org/celemas/core/compare/0.4.0...HEAD)

No notable changes since the last release.

## [0.4.0](https://codeberg.org/celemas/core/src/tag/0.4.0) (2026-06-11)

### Added

- Added `Celemas\Core\Error\Handler` and related renderer interfaces for PSR-15 error handling.
- Added `App::errorHandler()` to wrap the whole request lifecycle, including routing errors.

### Changed

- Scoped PHP error conversion to handled requests instead of registering global PHP handlers.
- Required a server request when rendering errors directly; renderers now receive a non-null request.
- Moved handled server-exception diagnostics to the core dev-server console and marked affected request lines with `[EXC]`.
- Mapped router not-found and method-not-allowed failures to core HTTP exceptions before rendering.
- Declared the PSR HTTP server, HTTP message, and log interfaces used by runtime code as direct dependencies.

## [0.3.0](https://codeberg.org/celemas/core/src/tag/0.3.0) (2026-06-09)

### Breaking

- Renamed the package from `duon/core` to `celemas/core`, along with the root namespace, dependency names, repository URLs, homepage, contact email, and built-in server environment variables.
- Moved the PSR-17 factory interface from `Duon\Core\Factory` to `Celemas\Core\Factory\Factory`; concrete factories now live in the `Celemas\Core\Factory` namespace.
- Removed app-level configuration support, including `ConfigInterface`, `AddsConfigInterface`, `App::config()`, and config arguments in `App::__construct()` and `App::create()`.
- Changed `App::create()` to auto-discover a PSR-17 factory and accept only an optional PSR container; pass custom factories to the `App` constructor.
- Updated route helpers to match `celemas/router`: use `any()` for methodless routes, `map()` for explicit method lists, callable controller arrays, and callback groups; `routes()` and `addGroup()` were removed, and `group()` now returns `void`.
- Removed the global `Duon\Core\env()` helper and Composer file autoloading.
- Required the PHP `fileinfo` extension for file response MIME detection.

### Added

- Added `Celemas\Core\Factory\Discovery` to select an installed Nyholm, Guzzle, or Laminas PSR-17 factory automatically.
- Added BrowserSync-backed watch mode to the development server with the `--watch` option, configurable watch patterns, brace/glob expansion, symlink-aware patterns, and reload debounce settings.

### Changed

- Improved development server startup by validating port values and reporting unavailable ports before launching PHP or BrowserSync.

## [0.2.0](https://codeberg.org/celemas/core/src/tag/0.2.0) (2026-02-21)

Codename: Jonas

### Changed

- BREAKING: Replaced `celemas/registry` dependency with `celemas/container`. The `Registry` class is now `Container` (`Celemas\Container\Container`), and `App::registry()` is now `App::container()`.

## [0.1.0](https://codeberg.org/celemas/core/src/tag/0.1.0) (2026-01-31)

Initial release.

### Added

- Core web framework integrating CLI, container, and router components
- HTTP request/response handling with PSR-7/PSR-15 support
- Application bootstrapping and middleware pipeline
