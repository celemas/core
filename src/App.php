<?php

declare(strict_types=1);

namespace Celema\Core;

use Celema\Container\Container;
use Celema\Container\Entry;
use Celema\Core\Emitter\Emitter;
use Celema\Core\Emitter\Sapi;
use Celema\Core\Error\Handler as ErrorHandler;
use Celema\Core\Factory\Factory;
use Celema\Core\Factory\Nyholm;
use Celema\Router\AddsBeforeAfter;
use Celema\Router\AddsRoutes;
use Celema\Router\Dispatcher;
use Celema\Router\Route;
use Celema\Router\RouteAdder;
use Celema\Router\Router;
use Celema\Router\RoutingHandler;
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
	protected Emitter $emitter;

	public function __construct(
		protected readonly Factory $factory,
		protected readonly Router $router,
		protected readonly Container $container,
	) {
		$this->dispatcher = new Dispatcher();
		$this->emitter = new Sapi();
		$this->initializeContainer();
	}

	public function load(Plugin $plugin): void
	{
		$plugin->load($this);
	}

	public static function create(?PsrContainer $container = null): self
	{
		return new self(
			new Nyholm(),
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

	public function emitter(?Emitter $emitter = null): Emitter
	{
		if ($emitter !== null) {
			$this->emitter = $emitter;
		}

		return $this->emitter;
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

		return $this->emitter->emit($response, $request->getMethod() === 'HEAD') ? $response : false;
	}
}
