# Changelog

## [Unreleased](https://codefloe.com/celema/core/compare/0.10.1...HEAD)

No notable changes since the last release.

## [0.10.1](https://codefloe.com/celema/core/src/tag/0.10.1) (2026-10-25)

### Changed

- `run()` ends the request after emitting the response, with `fastcgi_finish_request()` where the runtime provides it (PHP-FPM, FrankenPHP in classic and worker mode), so the client no longer waits for the scope reset and the teardown hooks. Output from those steps no longer reaches the client; before, it was appended to the response body.

## [0.10.0](https://codefloe.com/celema/core/src/tag/0.10.0) (2026-10-17)

### Breaking Changes

- Require `celema/router` 0.7. Route groups exist only inside their `group()` callbacks, the handler setters exist only on `Route`, and the router's `AddsBeforeAfter` and `AddsMiddleware` traits are internal.
- `App` no longer uses the router's internal `AddsBeforeAfter` trait. `before()` and `after()` add the handler to the app's dispatcher, and `beforeHandlers()`, `afterHandlers()`, `mergeBeforeHandlers()`, `mergeAfterHandlers()`, `setBeforeHandlers()`, and `setAfterHandlers()` are gone.

### Changed

- The README's development-server section follows `celema/server` 0.4: watching is on by default, `--no-watch` turns it off, and FrankenPHP runs as `./app/run server frankenphp`.

## [0.9.0](https://codefloe.com/celema/core/src/tag/0.9.0) (2026-10-15)

### Breaking Changes

- Require `celema/container` 0.7 and `celema/router` 0.6, which require `celema/wire` 0.9. A container entry whose `args()` target a class without a constructor throws a `NotFoundException` instead of silently dropping the arguments.

### Fixed

- `Request::field()` without a default throws an `OutOfBoundsException` when the request has no parsed body, as it does when the field is missing from one. Before, it failed an assertion, or with assertions disabled, threw a `TypeError`.
- `Request::form()` and `Request::field()` throw a `RuntimeException` when the parsed body is an object, which PSR-7 allows, for example after a middleware decoded a JSON body. Before, they failed an assertion, or with assertions disabled, threw a `TypeError`.
- `Request::file()` and `Request::files()` throw a `RuntimeException` for keys that mix strings and arrays, such as `file('docs', ['cv'])`. Before, they threw a `TypeError`.
- `Request::headers(true)` gives an empty string for a header without values, which some PSR-7 implementations allow. Before, it threw a `TypeError`.
- `Request::origin()` keeps an authority of `0`, as in `http://0/`. Before, it returned `http:`.
- `Response::file()` and `Response::download()` throw a `RuntimeException` without a stream factory, like writing a resource body does. Before, they failed an assertion, or with assertions disabled, threw an `Error`.
- `Response::file()` opens the file before detecting its type, so an unreadable file fails with the stream factory's exception instead of `finfo` warnings first, which the error handler may turn into exceptions. When detection fails, the content type falls back to `application/octet-stream` and `Content-Transfer-Encoding` is left out. Before, `false` was passed as the header value.

### Changed

- Methods that returned a bare `array` declare their value types for static analysis: header values are `string[]`, `Request::headers(true)` returns one string per header, `App::getMiddleware()` returns a list of middleware, and request data such as query, form, cookie, server, attribute, and uploaded-file arrays is `array<array-key, mixed>`.
- The `Request::files()` description says that nested keys keep their structure; only a single file is wrapped in a list.

## [0.8.0](https://codefloe.com/celema/core/src/tag/0.8.0) (2026-10-06)

### Breaking Changes

- Log messages name the request and what went wrong, with PSR-3 placeholders: `Server error {status} for {method} {path}` and `Client error {status} for {method} {path}` replace `Unmatched exception` and `Matched exception`, `PHP {type}: {diagnostic} in {file} on line {line}` replaces `PHP diagnostic`, and `Unhandled exception` and `Request teardown failed` end in `for {method} {path}`. The context carries the placeholder values; the path leaves out the query string.
- `Error\Handler::log()` and `Error\Handler::logUnmatched()` take the request as their last argument; `App::report()` and `App::finish()` take the request, or `null` when creating it failed.
- `Response::body()` with a string and no stream factory throws when the current body is not empty, instead of writing over it from the start and leaving the tail of a longer previous body behind. Pass a stream factory to replace bodies.

### Fixed

- A logger that throws while the error handler logs an exception no longer replaces the rendered response with a minimal `500` and hides the original exception: the logging failure and the record go to `error_log()`. A logger failing on a PHP diagnostic lets PHP report the diagnostic instead of throwing at the line that raised it.
- Without a logger, the error handler writes the exceptions it logs to `error_log()`. Server errors it answered were reported nowhere before.
- Unmatched server errors are logged as `critical` instead of `alert`. Unmatched client errors, such as `404` for an unknown route, are no longer logged; a renderer entry's log level still records them.

### Changed

- The README's worker-mode section explains when a shared logger needs a reset after each request.

## [0.7.0](https://codefloe.com/celema/core/src/tag/0.7.0) (2026-10-02)

### Breaking Changes

- Require `celema/container` 0.6 and `celema/router` 0.5.
- `App::run()` handles each request in its own container scope, created from the app's container. Scoped entries get one instance per request, and the container is sealed after the first request, so registering entries afterwards throws.
- `App::run()` no longer lets throwables escape that the error handler did not handle (for example in debug mode without a debug handler) or that the emitter threw. They are logged through the registered PSR-3 logger, or with `error_log()` if there is none or it fails, and answered with a minimal `500` response if nothing was sent yet, without a body for `HEAD` requests.
- `Request` is immutable, like the PSR-7 request it wraps: `with()`, `withHeader()`, `withAddedHeader()` and `withoutHeader()` return a new wrapper and replace `set()`, `setHeader()`, `addHeader()` and `removeHeader()`, which changed the wrapper in place. `wrap()` is removed. In middleware based on `Middleware`, pass the changed wrapper on: `$next($request->with('key', $value))`.

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
