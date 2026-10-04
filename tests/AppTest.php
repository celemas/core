<?php

declare(strict_types=1);

namespace Celema\Core\Tests;

use Celema\Container\Container;
use Celema\Core\App;
use Celema\Core\Emitter\Emitter;
use Celema\Core\Emitter\Sapi;
use Celema\Core\Factory\Factory;
use Celema\Core\Factory\Nyholm;
use Celema\Core\Plugin;
use Celema\Core\Response as CoreResponse;
use Celema\Core\Tests\Fixtures\TestContainer;
use Celema\Core\Tests\Fixtures\TestLogger;
use Celema\Router\After;
use Celema\Router\Before;
use Celema\Router\Router;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface as PsrLogger;
use stdClass;

final class AppTest extends TestCase
{
	public function testCreateHelper(): void
	{
		$app = App::create();

		$this->assertInstanceOf(App::class, $app);
		$this->assertInstanceOf(Nyholm::class, $app->factory());
	}

	public function testConstructorAcceptsCustomFactory(): void
	{
		$factory = new Nyholm();
		$app = new App($factory, new Router(), new Container());

		$this->assertSame($factory, $app->factory());
	}

	public function testHelperMethods(): void
	{
		$app = App::create();

		$this->assertInstanceOf(Container::class, $app->container());
		$this->assertInstanceOf(Router::class, $app->router());
		$this->assertInstanceOf(Factory::class, $app->factory());
		$this->assertInstanceOf(Nyholm::class, $app->factory());
	}

	public function testCreateWithThirdPartyContainer(): void
	{
		$container = new TestContainer();
		$container->add('external', new stdClass());
		$app = App::create($container);

		$this->assertInstanceof(stdClass::class, $app->container()->get('external'));
	}

	public function testMiddlewareHelper(): void
	{
		$middleware = new class implements MiddlewareInterface {
			public function process(
				ServerRequestInterface $request,
				RequestHandlerInterface $handler,
			): ResponseInterface {
				return $handler->handle($request);
			}
		};
		$app = App::create();
		$app->middleware($middleware);

		$this->assertSame(1, count($app->getMiddleware()));
		$this->assertSame($middleware, $app->getMiddleware()[0]);
	}

	public function testAppRun(): void
	{
		$app = $this->app();
		$app->any('/', [Fixtures\TestController::class, 'textView']);
		ob_start();
		$app->run($this->request());
		$output = ob_get_contents();
		ob_end_clean();

		$this->assertSame('text', $output);
	}

	public function testAppRunHeadRequest(): void
	{
		$app = $this->app();
		$app->any('/', [Fixtures\TestController::class, 'textView']);
		ob_start();
		$response = $app->run($this->request(['REQUEST_METHOD' => 'HEAD']));
		$output = ob_get_contents();
		ob_end_clean();

		$this->assertSame('', $output);
		$this->assertInstanceOf(ResponseInterface::class, $response);
	}

	public function testEmitter(): void
	{
		$app = $this->app();

		$this->assertInstanceOf(Sapi::class, $app->emitter());

		$emitter = new class implements Emitter {
			public bool $emitted = false;

			public function emit(ResponseInterface $response, bool $withoutBody = false): bool
			{
				$this->emitted = true;

				return true;
			}
		};
		$app->emitter($emitter);
		$app->any('/', [Fixtures\TestController::class, 'textView']);
		$response = $app->run($this->request());

		$this->assertSame($emitter, $app->emitter());
		$this->assertSame(true, $emitter->emitted);
		$this->assertInstanceOf(ResponseInterface::class, $response);
	}

	public function testAppRegisterHelper(): void
	{
		$app = $this->app();
		$app->register('Chuck', 'Schuldiner')->value();
		$container = $app->container();

		$this->assertSame('Schuldiner', $container->get('Chuck'));
	}

	public function testAddLoggerInstance(): void
	{
		$app = $this->app();
		$app->logger(new TestLogger());
		$container = $app->container();
		$logger = $container->get(PsrLogger::class);

		$this->assertInstanceOf(TestLogger::class, $logger);
	}

	public function testAddLoggerCallable(): void
	{
		$app = $this->app();
		$app->logger(static fn(): PsrLogger => new TestLogger());
		$container = $app->container();
		$logger = $container->get(PsrLogger::class);

		$this->assertInstanceOf(TestLogger::class, $logger);
	}

	public function testContainerInitialized(): void
	{
		$app = $this->app();
		$container = $app->container();

		$this->assertInstanceof(Router::class, $container->get(Router::class));
		$this->assertInstanceof(Factory::class, $container->get(Factory::class));
	}

	public function testContainerResolvesTheAppsRouterAndFactory(): void
	{
		$router = new class extends Router {};
		$factory = new Nyholm();
		$app = new App($factory, $router, new Container());
		$container = $app->container();

		$this->assertSame($router, $container->get(Router::class));
		$this->assertSame($router, $container->get($router::class));
		$this->assertSame($factory, $container->get(Factory::class));
		$this->assertSame($factory, $container->get(Nyholm::class));
	}

	public function testRunReturnsFalseWhenTheEmitterFails(): void
	{
		$app = $this->app();
		$app->emitter(new class implements Emitter {
			public function emit(ResponseInterface $response, bool $withoutBody = false): bool
			{
				return false;
			}
		});
		$app->any('/', [Fixtures\TestController::class, 'textView']);

		$this->assertFalse($app->run($this->request()));
	}

	public function testAppLevelBeforeAndAfterHandlersWrapTheView(): void
	{
		$app = $this->app();
		$app->emitter(new Fixtures\RecordingEmitter());
		$app->before(new class implements Before {
			public function handle(ServerRequestInterface $request): ServerRequestInterface
			{
				return $request->withAttribute('greeting', 'before');
			}

			public function replace(Before $handler): bool
			{
				return false;
			}
		});
		$app->after(new class implements After {
			public function handle(mixed $data): mixed
			{
				assert($data instanceof ResponseInterface, 'The view returns a response');

				return $data->withHeader('X-After', 'after');
			}

			public function replace(After $handler): bool
			{
				return false;
			}
		});
		$app->get('/', static fn(ServerRequestInterface $request): ResponseInterface => CoreResponse::create(
			$app->factory(),
		)
			->body((string) $request->getAttribute('greeting'))
			->unwrap());

		$response = $app->run($this->request());

		$this->assertInstanceOf(ResponseInterface::class, $response);
		$this->assertSame('before', (string) $response->getBody());
		$this->assertSame('after', $response->getHeaderLine('X-After'));
	}

	public function testLoadPlugin(): void
	{
		$plugin = new class implements Plugin {
			public function load(App $app): void
			{
				$app->register('test-id', stdClass::class);
			}
		};
		$app = $this->app();
		$app->load($plugin);

		$this->assertInstanceOf(stdClass::class, $app->container()->get('test-id'));
	}
}
