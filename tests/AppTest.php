<?php

declare(strict_types=1);

namespace Celema\Core\Tests;

use Celema\Container\Container;
use Celema\Core\App;
use Celema\Core\Factory\Factory;
use Celema\Core\Factory\Nyholm;
use Celema\Core\Plugin;
use Celema\Core\Tests\Fixtures\TestContainer;
use Celema\Core\Tests\Fixtures\TestLogger;
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
