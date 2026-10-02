<?php

declare(strict_types=1);

namespace Celema\Core;

use Celema\Container\Container;
use Celema\Container\Entry;
use Celema\Container\Exception\ResetFailed;
use Celema\Core\Emitter\Emitter;
use Celema\Core\Emitter\Fallback;
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
use Celema\Server\Console as ServerConsole;
use Closure;
use Override;
use Psr\Container\ContainerInterface as PsrContainer;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Log\LoggerInterface as Logger;
use Throwable;

/** @api */
class App implements RouteAdder, RequestHandler
{
	use AddsRoutes;
	use AddsBeforeAfter;

	protected readonly Dispatcher $dispatcher;
	protected ?ErrorHandler $errorHandler = null;
	protected Emitter $emitter;

	/** @var list<Closure(): void> */
	protected array $teardownHooks = [];

	/**
	 * False once a request left state behind that the app cannot vouch for:
	 * a throwable escaped the request, or its teardown failed.
	 */
	protected bool $reusable = true;

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

	/**
	 * Registers a callback that runs after every request, once the request's
	 * container scope was reset: in run() after the response was emitted, in
	 * handle() before the response is returned. Use it to bring resources
	 * that outlive a request back to a clean state, such as rolling back a
	 * database transaction left open.
	 *
	 * Callbacks run in registration order. A failing callback does not stop
	 * the others; failures are logged and the app is no longer reusable.
	 *
	 * @param Closure(): void $hook
	 */
	public function teardown(Closure $hook): void
	{
		$this->teardownHooks[] = $hook;
	}

	/**
	 * Handles the request in its own container scope without emitting the
	 * response. The scope is reset and the teardown hooks have run when the
	 * response is returned, so its body must not depend on scoped services.
	 */
	#[Override]
	public function handle(Request $request): Response
	{
		$scope = $this->container->scope();

		try {
			return $this->respond($request, $scope);
		} catch (Throwable $e) {
			$this->reusable = false;

			throw $e;
		} finally {
			$this->finish($scope);
		}
	}

	/**
	 * Handles exactly one request in its own container scope, emits the
	 * response, then runs the teardown. Throwables that escape the error
	 * handler or the emitter are logged and answered with a minimal 500
	 * response if nothing was sent yet; they do not leave this method.
	 */
	public function run(?Request $request = null): Response|false
	{
		$bufferLevel = ob_get_level();
		$scope = $this->container->scope();

		try {
			$request ??= $this->factory->serverRequest();
			$response = $this->respond($request, $scope);

			return $this->emitter->emit($response, $request->getMethod() === 'HEAD') ? $response : false;
		} catch (Throwable $e) {
			$this->reusable = false;
			$this->report('Unhandled exception', $e);
			$this->recordServerException($e);
			$this->emitFailure($bufferLevel);

			return false;
		} finally {
			$this->finish($scope);
		}
	}

	/**
	 * Whether the app may handle further requests in the same process.
	 *
	 * @internal
	 */
	public function reusable(): bool
	{
		return $this->reusable;
	}

	protected function respond(Request $request, Container $scope): Response
	{
		$this->dispatcher->setBeforeHandlers($this->beforeHandlers);
		$this->dispatcher->setAfterHandlers($this->afterHandlers);
		$handler = new RoutingHandler(
			$this->router,
			$this->dispatcher,
			$scope,
		);

		return $this->errorHandler
			? $this->errorHandler->process($request, $handler)
			: $handler->handle($request);
	}

	/**
	 * Resets the request scope, then runs the teardown hooks. Every step is
	 * attempted; failures are logged and make the app unusable for further
	 * requests, but never replace the request's response or exception.
	 */
	protected function finish(Container $scope): void
	{
		$failures = [];

		try {
			$scope->reset();
		} catch (ResetFailed $e) {
			$failures = $e->failures;
		}

		foreach ($this->teardownHooks as $hook) {
			try {
				$hook();
			} catch (Throwable $e) {
				$failures[] = $e;
			}
		}

		foreach ($failures as $failure) {
			$this->reusable = false;
			$this->report('Request teardown failed', $failure);
		}
	}

	protected function report(string $message, Throwable $exception): void
	{
		try {
			/** @var mixed $logger */
			$logger = $this->container->has(Logger::class) ? $this->container->get(Logger::class) : null;
		} catch (Throwable) {
			$logger = null;
		}

		if ($logger instanceof Logger) {
			$logger->critical($message, ['exception' => $exception]);

			return;
		}

		error_log($message . ': ' . (string) $exception);
	}

	protected function recordServerException(Throwable $exception): void
	{
		// Shows the exception in the celema/server request log, as the error
		// handler does for the exceptions it renders.
		if (class_exists(ServerConsole::class)) {
			ServerConsole::recordException($exception, trace: true);
		}
	}

	/** Answers with a bare 500 response if the failed request sent nothing yet. */
	protected function emitFailure(int $bufferLevel): void
	{
		$response = $this->factory->response(500)->withHeader('Content-Type', 'text/plain; charset=utf-8');
		$response->getBody()->write('500 Internal Server Error');

		Fallback::emit($this->emitter, $response, $bufferLevel);
	}
}
