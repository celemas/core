# Changelog

## [Unreleased](https://codefloe.com/celema/core/compare/0.6.0...HEAD)

### Breaking Changes

- Require `celema/container` 0.6 and `celema/router` 0.5.
- `App::run()` handles each request in its own container scope, created from the app's container. Scoped entries get one instance per request, and the container is sealed after the first request, so registering entries afterwards throws.
- `App::run()` no longer lets throwables escape that the error handler did not handle (for example in debug mode without a debug handler) or that the emitter threw. They are logged through the registered PSR-3 logger, otherwise with `error_log()`, and answered with a minimal `500` response if nothing was sent yet.

### Added

- `App::serve()` runs the app in the runtime that started the script: a FrankenPHP worker handles requests until it retires, any other runtime handles the current request.
- FrankenPHP worker runtime: clears the stat cache before each request, collects garbage after it, and retires after a request that failed outside the error handler or a failed teardown, after `CELEMA_WORKER_MAX_REQUESTS` requests, or above `CELEMA_WORKER_MAX_MEMORY` (default 80 % of `memory_limit`).
- `App::handle()`: `App` implements PSR-15's `RequestHandlerInterface` and handles a request without emitting the response.
- `App::teardown()` registers callbacks that run after every request, once its container scope was reset. Failing callbacks are logged and do not stop the others.

## [0.6.0](https://codefloe.com/celema/core/src/tag/0.6.0) (2026-09-27)

- Replaced the Laminas-backed `Celema\Core\Emitter` class with the `Celema\Core\Emitter\Emitter` interface and its built-in `Celema\Core\Emitter\Sapi` implementation, dropping the `laminas/laminas-httphandlerrunner` dependency. A custom emitter can be plugged in through the new `App::emitter()` method.
- Responses to `HEAD` requests and responses with a `1xx`, `204`, or `304` status now emit their headers without a body, as required by RFC 9110. Previously the body was emitted verbatim.
- Removed the `Guzzle` and `Laminas` PSR-17 factories and the `Discovery` class. `Nyholm` is the only built-in factory; `App::create()` uses it directly and reports a clear error when `nyholm/psr7` and `nyholm/psr7-server` are missing. Other PSR-7 implementations remain usable through a custom `Celema\Core\Factory\Factory` implementation passed to the `App` constructor.
- Extracted the development server into the standalone `celema/server` package. The `Celema\Core\Server` namespace is gone; the commands now live in `Celema\Server`. Core no longer depends on `celema/console`, and the error handler reports handled server errors to the development server only when `celema/server` is installed.
- Required the PHP `mbstring` extension.

### Added

- Added a development-only example app with routes for checking Core's routing, autowiring, HTTP helpers, middleware, error handling, static assets, and PHP or FrankenPHP development-server output.

### Fixed

- PHP deprecations no longer abort handled requests. Core reports them through the configured logger, or delegates them to PHP's native error handler when no logger is configured; strict applications can include deprecation levels in the error handler's `exceptionLevels` constructor argument.

## [0.5.0](https://codefloe.com/celema/core/src/tag/0.5.0) (2026-07-18)

### Changed

- Renamed the Composer package to `celema/core` and moved PHP classes from `Celemas\Core` to `Celema\Core`.
- Updated integrations to `celema/console:^0.3`, `celema/container:^0.5`, and `celema/router:^0.4`, with their corresponding `Celema` namespaces.
- Renamed the built-in development server environment variables from the `CELEMAS_` prefix to `CELEMA_`.

### Removed

- Removed the previous Composer package name, PHP namespaces, and development server environment variable names; consumers must update their dependencies and integrations.

## [0.4.0](https://codefloe.com/celema/core/src/tag/0.4.0) (2026-06-11)

### Added

- Added `Celemas\Core\Error\Handler` and related renderer interfaces for PSR-15 error handling.
- Added `App::errorHandler()` to wrap the whole request lifecycle, including routing errors.

### Changed

- Scoped PHP error conversion to handled requests instead of registering global PHP handlers.
- Required a server request when rendering errors directly; renderers now receive a non-null request.
- Moved handled server-exception diagnostics to the core dev-server console and marked affected request lines with `[EXC]`.
- Mapped router not-found and method-not-allowed failures to core HTTP exceptions before rendering.
- Declared the PSR HTTP server, HTTP message, and log interfaces used by runtime code as direct dependencies.

## [0.3.0](https://codefloe.com/celema/core/src/tag/0.3.0) (2026-06-09)

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

## [0.2.0](https://codefloe.com/celema/core/src/tag/0.2.0) (2026-02-21)

Codename: Jonas

### Changed

- BREAKING: Replaced `celemas/registry` dependency with `celemas/container`. The `Registry` class is now `Container` (`Celemas\Container\Container`), and `App::registry()` is now `App::container()`.

## [0.1.0](https://codefloe.com/celema/core/src/tag/0.1.0) (2026-01-31)

Initial release.

### Added

- Core web framework integrating CLI, container, and router components
- HTTP request/response handling with PSR-7/PSR-15 support
- Application bootstrapping and middleware pipeline
