# Celema Core Framework

<!-- prettier-ignore-start -->
[![ci](https://codefloe.com/celema/core/badges/workflows/ci.yml/badge.svg?style=flat&logo=forgejo&logoColor=white&label=ci)](https://codefloe.com/celema/core/actions)
[![code coverage](https://img.shields.io/endpoint?url=https%3A%2F%2Fcov.celema.dev%2Fcelema%2Fcore%2Fcode%2Fbadge.json)](https://cov.celema.dev/celema/core/code)
[![type coverage](https://img.shields.io/endpoint?url=https%3A%2F%2Fcov.celema.dev%2Fcelema%2Fcore%2Ftypes%2Fbadge-cover.json)](https://cov.celema.dev/celema/core/types)
[![psalm level](https://img.shields.io/endpoint?url=https%3A%2F%2Fcov.celema.dev%2Fcelema%2Fcore%2Ftypes%2Fbadge-level.json)](https://cov.celema.dev/celema/core/types)
[![Software License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE.md)
<!-- prettier-ignore-end -->

Celema Core is a lightweight and easily extendable PHP 8.5+ web framework.

> [!WARNING] This library is under active development, some of its features are still experimental and subject to change. Large parts of the documentation are missing.

It features:

- Http Routing.
- An autowiring container used for automatic dependency injection.
- Middleware.
- Error handling for PSR-15 request pipelines.
- Convenience wrappers for PSR request, response and middleware.
- Logging.

## Routing

`App` exposes the router's common route helpers and runs requests through the router `RoutingHandler` internally.

```php
use Celema\Core\App;
use Celema\Router\Group;

$app = App::create();

$app->get('/health', [HealthController::class, 'show'], 'health');
$app->map(['GET', 'POST'], '/login', [AuthController::class, 'login'], 'login');
$app->any('/webhook', $webhook, 'webhook');

$app->group('/admin', function (Group $admin) use ($auth): void {
	$admin->middleware($auth);
	$admin->controller(AdminController::class);

	$admin->get('', 'index', 'admin.index');
	$admin->post('/login', 'login', 'admin.login');
});
```

## Request lifecycle

Let the front controller call `serve()`:

```php
// public/index.php
require dirname(__DIR__) . '/vendor/autoload.php';

$app = require dirname(__DIR__) . '/app/bootstrap.php';

return $app->serve();
```

`serve()` selects the runtime. Started as a [FrankenPHP worker](#worker-mode), it handles requests until the worker retires. Everywhere else (PHP-FPM, FrankenPHP's classic mode, the CLI development server) it handles the current request and returns its response. The same file works in all of them.

Every request goes through the same steps:

1. The app opens a container scope for the request. Routing, middleware, controllers and their autowired arguments resolve through that scope, so `scoped()` entries get one instance per request.
2. The error handler, if any, wraps routing, middleware and the view.
3. The response is emitted.
4. The teardown runs: the scope is reset (services implementing `Celema\Container\Resettable` are reset), then the app's teardown hooks run.

`run()` performs these steps for one request; `handle()` implements PSR-15's `RequestHandlerInterface` and returns the response without emitting it, which is useful in tests. In `handle()`, the teardown runs before the response is returned, so a response body must not depend on scoped services.

Register routes, middleware, and container entries before the first request. The first request seals the container; registering afterwards throws.

### Teardown hooks

Use `teardown()` for resources that outlive a request and have to be brought back to a clean state after each one:

```php
$app->teardown(static function () use ($mailer): void {
	$mailer->disconnect();
});
```

Hooks run in registration order. Each one runs even if an earlier one failed.

### Failures

Exceptions thrown while handling a request are the error handler's job. A throwable that escapes it, or the emitter, is logged through the PSR-3 logger registered with `$app->logger()` (otherwise with `error_log()`), and `run()` answers with a minimal `500` response if nothing was sent yet. A failing teardown step is logged the same way; it never replaces the response that was already emitted.

## Worker mode

[FrankenPHP's worker mode](https://frankenphp.dev/docs/worker/) boots the application once and keeps it in memory for many requests. Point the worker at the normal front controller:

```caddyfile
example.org {
	root * /srv/site/public
	php_server {
		worker {
			file /srv/site/public/index.php
			num 4
		}
	}
}
```

What lives for the whole worker and what lives for one request:

- The app, its router, routes, middleware instances, `Before`/`After` handler instances, the error handler and its renderers, and all `shared()` container entries live as long as the worker. They must not keep the state of a single request.
- Each request gets a new container scope with its own `scoped()` instances, new controllers, and new autowired view arguments.
- A shared entry cannot depend on a scoped one: the container throws instead of keeping the first request's instance. Pass request data as arguments, or make the consumer scoped too. Do not register the PSR request or values derived from it in the container; take them from the request passed to middleware and views.

The worker retires, and FrankenPHP starts a fresh one, in these cases:

- A throwable escaped the error handler or the emitter, or the teardown failed. The response was answered and logged as described above; retiring keeps whatever state the failed request left behind away from the next one.
- The configured number of requests was reached: `CELEMA_WORKER_MAX_REQUESTS` (default `0`, no limit).
- Memory use exceeds `CELEMA_WORKER_MAX_MEMORY` in bytes, `K`, `M` or `G` (default 80 % of `memory_limit`, `0` disables it).

Set both in the worker's environment (`env CELEMA_WORKER_MAX_REQUESTS 1000` in the `worker` block) or pass them to `serve(maxRequests: …, maxMemory: …)`, which wins over the environment. FrankenPHP's `max_consecutive_failures` stops a worker that fails while booting.

Things to keep in mind:

- Code, configuration and other files read at boot are only read again by a new worker: restart FrankenPHP or its workers after a deployment, for example through the admin API's `POST /frankenphp/workers/restart`.
- FrankenPHP rebuilds `$_SERVER` for every request: values a script writes into it at boot, for example a `.env` loader, are gone in the requests, while process environment variables are available in each request. `$_ENV` and `putenv()` persist across requests and are shared by all threads of the process.
- The worker clears PHP's file stat cache before each request, so `filemtime()` and `filesize()` see changes made by other processes.
- Code must finish a request by returning a response. `exit()` and `die()` end the worker; FrankenPHP restarts it, at the cost of a fresh boot.
- Each worker keeps its own resources, such as a database connection, open between requests. Budget the database connections for the number of workers of all sites sharing a database server.
- `Response::sendfile()` chooses `X-Accel-Redirect` or `X-Sendfile` from `$_SERVER['SERVER_SOFTWARE']` per request.

## Development server

The development server commands live in the optional [`celema/server`](https://codefloe.com/celema/server) package, which runs applications with the PHP CLI's built-in server or FrankenPHP:

```bash
composer require --dev celema/server
```

When the package is installed, Core's error handler automatically reports handled server errors to the development server's request log.

### Development example

The repository's example app exercises routing, autowiring, request and response helpers, middleware, error handling, static assets, and request-log states. With `celema/server` installed, run it on port `1973` with either development server:

```bash
./app/run server
./app/run frankenphp
```

Add `--watch` to run BrowserSync and reload when the example or Core source changes. Both commands support host, port, request-log filtering, and BrowserSync-backed `--watch` mode.

## PSR-7 implementation

`App::create()` uses [nyholm/psr7](https://github.com/Nyholm/psr7) as its PSR-7/PSR-17 implementation:

```bash
composer require nyholm/psr7 nyholm/psr7-server
```

Any other implementation works through a custom `Celema\Core\Factory\Factory`. Extend `AbstractFactory`, assign the implementation's PSR-17 factories, and pass an instance to the `App` constructor:

```php
use Celema\Core\Factory\AbstractFactory;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\ServerRequest;
use Psr\Http\Message\ServerRequestInterface;

class Guzzle extends AbstractFactory
{
	public function __construct()
	{
		$factory = new HttpFactory();
		$this->requestFactory = $factory;
		$this->responseFactory = $factory;
		$this->serverRequestFactory = $factory;
		$this->streamFactory = $factory;
		$this->uploadedFileFactory = $factory;
		$this->uriFactory = $factory;
	}

	public function serverRequest(): ServerRequestInterface
	{
		return ServerRequest::fromGlobals();
	}
}

$app = new App(new Guzzle(), new Router(), new Container());
```

Supported PSRs:

- PSR-3 Logger Interface
- PSR-4 Autoloading
- PSR-7 Http Messages (Request, Response, Stream, and so on.)
- PSR-11 Container Interface
- PSR-12 Extended Coding Style
- PSR-15 Http Middleware
- PSR-17 Http Factories

## License

This project is licensed under the [MIT license](LICENSE.md).

The built-in SAPI emitter is derived from [laminas/laminas-httphandlerrunner](https://github.com/laminas/laminas-httphandlerrunner) (BSD-3-Clause); see the third-party code section in [LICENSE.md](LICENSE.md).
