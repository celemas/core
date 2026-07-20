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

## Development servers

Core provides two console command classes for local development:

- `Celema\Core\Server\Server` runs the application with the PHP CLI's built-in server.
- `Celema\Core\Server\FrankenPhp` runs it with the `frankenphp` executable from `PATH`.

Register either class with `celema/console` using a factory that supplies the application's public directory. Both commands support host, port, request-log filtering, and BrowserSync-backed `--watch` mode. The FrankenPHP command uses classic mode, not worker mode; its `--debug` option enables verbose Caddy logs. FrankenPHP embeds its own PHP runtime, extensions, and configuration rather than using the PHP CLI that starts the command.

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
