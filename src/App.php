<?php

declare(strict_types=1);

namespace Celemas\Core;

use Celemas\Container\Container;
use Celemas\Container\Entry;
use Celemas\Core\Error\Handler as ErrorHandler;
use Celemas\Core\Factory\Discovery;
use Celemas\Core\Factory\Factory;
use Celemas\Router\AddsBeforeAfter;
use Celemas\Router\AddsRoutes;
use Celemas\Router\Dispatcher;
use Celemas\Router\Route;
use Celemas\Router\RouteAdder;
use Celemas\Router\Router;
use Celemas\Router\RoutingHandler;
use Closure;
use Override;
use Psr\Container\ContainerInterface as PsrContainer;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Log\LoggerInterface as Logger;

/** @api */
class App implements RouteAdder
{
	use AddsRoutes;
	use AddsBeforeAfter;

	protected readonly Dispatcher $dispatcher;
	protected ?ErrorHandler $errorHandler = null;

	public function __construct(
		protected readonly Factory $factory,
		protected readonly Router $router,
		protected readonly Container $container,
	) {
		$this->dispatcher = new Dispatcher();
		$this->initializeContainer();
	}

	public function load(Plugin $plugin): void
	{
		$plugin->load($this);
	}

	public static function create(?PsrContainer $container = null): self
	{
		return new self(
			Discovery::create(),
			new Router(),
			new Container(container: $container),
		);
	}

	public function router(): Router
	{
		return $this->router;
	}

	public function factory(): Factory
	{
		return $this->factory;
	}

	#[Override]
	public function addRoute(Route $route): Route
	{
		return $this->router->addRoute($route);
	}

	#[Override]
	public function group(
		string $patternPrefix,
		Closure $createClosure,
		string $namePrefix = '',
	): void {
		$this->router->group($patternPrefix, $createClosure, $namePrefix);
	}

	public function staticRoute(
		string $prefix,
		string $path,
		string $name = '',
	): void {
		$this->router->addStatic($prefix, $path, $name);
	}

	public function getMiddleware(): array
	{
		return $this->dispatcher->getMiddleware();
	}

	public function errorHandler(?ErrorHandler $handler = null): ?ErrorHandler
	{
		if ($handler !== null) {
			$this->errorHandler = $handler;
		}

		return $this->errorHandler;
	}

	public function middleware(Middleware ...$middleware): void
	{
		$this->dispatcher->middleware(...$middleware);
	}

	public function logger(Logger|callable $logger): void
	{
		if ($logger instanceof Logger) {
			$this->container->add(Logger::class, $logger);
		} else {
			$this->container->add(Logger::class, Closure::fromCallable($logger));
		}
	}

	public function container(): Container
	{
		return $this->container;
	}

	/**
	 * @param non-empty-string $key
	 * @param class-string|object $value
	 */
	public function register(string $key, object|string $value): Entry
	{
		return $this->container->add($key, $value);
	}

	public function initializeContainer(): void
	{
		$this->container->add(Router::class, $this->router);
		$this->container->add($this->router::class, $this->router);

		$this->container->add(Factory::class, $this->factory);
		$this->container->add($this->factory::class, $this->factory);
	}

	public function run(?Request $request = null): Response|false
	{
		$request ??= $this->factory->serverRequest();
		$this->dispatcher->setBeforeHandlers($this->beforeHandlers);
		$this->dispatcher->setAfterHandlers($this->afterHandlers);
		$handler = new RoutingHandler(
			$this->router,
			$this->dispatcher,
			$this->container,
		);
		$response = $this->errorHandler
			? $this->errorHandler->process($request, $handler)
			: $handler->handle($request);

		return new Emitter()->emit($response) ? $response : false;
	}
}
