# Celema Core Framework

<!-- prettier-ignore-start -->
[![ci](https://codeberg.org/celema/core/badges/workflows/ci.yml/badge.svg?style=flat&logo=codeberg&logoColor=white&label=ci)](https://codeberg.org/celema/core/actions)
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

## Development server

The development server commands live in the optional [`celema/server`](https://codeberg.org/celema/server) package, which runs applications with the PHP CLI's built-in server or FrankenPHP:

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
