<?php

declare(strict_types=1);

namespace Celema\Core\Tests;

use Celema\Container\Container;
use Celema\Container\Exception\ContainerException;
use Celema\Container\Resettable;
use Celema\Core\App;
use Celema\Core\Error\Handler;
use Celema\Core\Exception\HttpNotFound;
use Celema\Core\Factory\Factory;
use Celema\Core\Factory\Nyholm;
use Celema\Core\Response;
use Celema\Core\Tests\Fixtures\Counter;
use Celema\Core\Tests\Fixtures\FailingLogger;
use Celema\Core\Tests\Fixtures\RecordingEmitter;
use Celema\Core\Tests\Fixtures\RecordingLogger;
use Celema\Core\Tests\Fixtures\SapiState;
use Celema\Router\After;
use Celema\Router\Before;
use Celema\Router\Group;
use Celema\Router\Router;
use Celema\Server\Console;
use Closure;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Log\LoggerInterface;
use RuntimeException;

final class AppLifecycleTest extends TestCase
{
	public function testScopedServicesAreFreshForEveryRequest(): void
	{
		$app = $this->countingApp();
		$emitter = new RecordingEmitter();
		$app->emitter($emitter);

		$app->run($this->request());
		$app->run($this->request());
		$app->run($this->request());

		$this->assertSame(['1', '1', '1'], $emitter->bodies());
		$this->assertSame(true, $app->reusable());
	}

	public function testHandlersAndMiddlewareRunOnceInEveryRequest(): void
	{
		$app = App::create();
		$emitter = new RecordingEmitter();
		$app->emitter($emitter);
		$app->middleware($this->middlewareTag('app'));
		$app->before($this->beforeTrail('app'));
		$app->after($this->afterTag('app'));
		$view = static function (ServerRequestInterface $request, Factory $factory): ResponseInterface {
			$trail = (array) $request->getAttribute('trail', []);

			return Response::create($factory)->text(implode(',', $trail))->unwrap();
		};
		$app->get('/', $view);
		$app->group('/api', function (Group $api) use ($view): void {
			$api->middleware($this->middlewareTag('group'));
			$api->before($this->beforeTrail('group'));
			$api->after($this->afterTag('group'));
			$api->get('/items', $view);
		});

		for ($i = 0; $i < 3; $i++) {
			$app->run($this->request());
			$app->run($this->request(['REQUEST_URI' => '/api/items']));
		}

		$home = ['trail' => 'app', 'after' => ['app'], 'middleware' => ['app']];
		$items = ['trail' => 'app,group', 'after' => ['app', 'group'], 'middleware' => ['group', 'app']];

		$this->assertSame(
			[$home, $items, $home, $items, $home, $items],
			array_map(static fn(ResponseInterface $response): array => [
				'trail' => (string) $response->getBody(),
				'after' => $response->getHeader('X-After'),
				'middleware' => $response->getHeader('X-Middleware'),
			], $emitter->responses),
		);
	}

	public function testTeardownRunsAfterEmissionAndScopeReset(): void
	{
		$events = [];
		$app = $this->countingApp(static function (Counter $counter) use (&$events): void {
			$events[] = 'resolve';
			$counter->count++;
		});
		$app->emitter(new RecordingEmitter(onEmit: static function () use (&$events): void {
			$events[] = 'emit';
		}));
		$app->teardown(static function () use (&$events): void {
			$events[] = 'first teardown';
		});
		$app->teardown(static function () use (&$events): void {
			$events[] = 'second teardown';
		});

		$app->run($this->request());

		$this->assertSame(['resolve', 'emit', 'first teardown', 'second teardown'], $events);
	}

	public function testTheRequestEndsBetweenEmissionAndTeardown(): void
	{
		SapiState::reset();
		$events = [];
		$app = $this->countingApp();
		$app->emitter(new RecordingEmitter(onEmit: static function () use (&$events): void {
			$events[] = 'emit, ended ' . SapiState::$endedRequests;
		}));
		$app->teardown(static function () use (&$events): void {
			$events[] = 'teardown, ended ' . SapiState::$endedRequests;
		});

		$app->run($this->request());

		$this->assertSame(['emit, ended 0', 'teardown, ended 1'], $events);
	}

	public function testAFailedRequestEndsBeforeTheTeardown(): void
	{
		SapiState::reset();
		$app = App::create();
		$app->logger(new RecordingLogger());
		$app->emitter($emitter = new RecordingEmitter());
		$app->get('/', static fn() => throw new RuntimeException('view failed'));
		$ended = null;
		$app->teardown(static function () use (&$ended): void {
			$ended = SapiState::$endedRequests;
		});

		$app->run($this->request());

		$this->assertSame(500, $emitter->responses[0]->getStatusCode());
		$this->assertSame(1, $ended);
	}

	public function testScopeIsResetBeforeTeardownHooks(): void
	{
		$counters = [];
		$app = $this->countingApp(static function (Counter $counter) use (&$counters): void {
			$counters[] = $counter;
		});
		$app->emitter(new RecordingEmitter());
		$resets = null;
		$app->teardown(static function () use (&$counters, &$resets): void {
			$resets = $counters[0]->resets;
		});

		$app->run($this->request());

		$this->assertSame(1, $resets);
	}

	public function testTeardownRunsAfterHandledErrors(): void
	{
		$app = App::create();
		$app->errorHandler(new Handler($app->factory()->responseFactory()));
		$app->emitter($emitter = new RecordingEmitter());
		$tornDown = 0;
		$app->teardown(static function () use (&$tornDown): void {
			$tornDown++;
		});

		$app->run($this->request(['REQUEST_URI' => '/missing']));

		$this->assertSame(404, $emitter->responses[0]->getStatusCode());
		$this->assertSame(1, $tornDown);
		$this->assertSame(true, $app->reusable());
	}

	public function testUnhandledThrowableIsAnsweredWithMinimal500(): void
	{
		$app = App::create();
		$app->logger($logger = new RecordingLogger());
		$app->emitter($emitter = new RecordingEmitter());
		$app->get('/', static fn() => throw new RuntimeException('view failed'));
		$tornDown = false;
		$app->teardown(static function () use (&$tornDown): void {
			$tornDown = true;
		});

		$result = $app->run($this->request());

		$this->assertSame(false, $result);
		$this->assertSame(500, $emitter->responses[0]->getStatusCode());
		$this->assertStringStartsWith('500 Internal Server Error', $emitter->bodies()[0]);
		$this->assertSame(['Unhandled exception for {method} {path}'], $logger->messages());
		$this->assertSame('view failed', $logger->exception(0)->getMessage());
		$this->assertSame('GET', $logger->records[0]['context']['method']);
		$this->assertSame('/', $logger->records[0]['context']['path']);
		$this->assertSame(true, $tornDown);
		$this->assertSame(false, $app->reusable());
	}

	public function testFailureShowsTheExceptionWhilePhpDisplaysErrors(): void
	{
		$app = App::create();
		$app->logger(new RecordingLogger());
		$app->emitter($emitter = new RecordingEmitter());
		$app->get('/', static fn() => throw new RuntimeException('shown in development'));
		// @mago-expect lint:no-ini-set
		$previous = ini_set('display_errors', '1');

		try {
			$app->run($this->request());
		} finally {
			// @mago-expect lint:no-ini-set
			ini_set('display_errors', (string) $previous);
		}

		$this->assertStringStartsWith(
			"500 Internal Server Error\n\nRuntimeException: shown in development",
			$emitter->bodies()[0],
		);
	}

	public function testFailureShowsTheExceptionWhilePhpDisplaysErrorsOnStdout(): void
	{
		$app = App::create();
		$app->logger(new RecordingLogger());
		$app->emitter($emitter = new RecordingEmitter());
		$app->get('/', static fn() => throw new RuntimeException('shown on stdout'));
		// @mago-expect lint:no-ini-set
		$previous = ini_set('display_errors', 'STDOUT');

		try {
			$app->run($this->request());
		} finally {
			// @mago-expect lint:no-ini-set
			ini_set('display_errors', (string) $previous);
		}

		$this->assertStringContainsString('RuntimeException: shown on stdout', $emitter->bodies()[0]);
	}

	public function testFailureToCreateTheRequestIsAnsweredWithMinimal500(): void
	{
		$factory = new class extends Nyholm {
			public function serverRequest(): ServerRequestInterface
			{
				throw new RuntimeException('no request');
			}
		};
		$app = new App($factory, new Router(), new Container());
		$app->logger($logger = new RecordingLogger());
		$app->emitter($emitter = new RecordingEmitter());

		$result = $app->run();

		$this->assertSame(false, $result);
		$this->assertSame([false], $emitter->withoutBody);
		$this->assertStringStartsWith('500 Internal Server Error', $emitter->bodies()[0]);
		$this->assertSame('no request', $logger->exception(0)->getMessage());
	}

	public function testDevServerShowsUnhandledExceptionsWithTrace(): void
	{
		$app = App::create();
		$app->logger(new RecordingLogger());
		$app->emitter(new RecordingEmitter());
		$app->get('/', static fn() => throw new RuntimeException('unhandled in dev'));
		$previous = $_SERVER['CELEMA_CLI_SERVER'] ?? null;
		$_SERVER['CELEMA_CLI_SERVER'] = '1';

		try {
			$app->run($this->request());
			$this->assertTrue(Console::hasException());
			$log = $this->captureErrorLog(Console::flushException(...));
		} finally {
			Console::clearException();

			if ($previous === null) {
				unset($_SERVER['CELEMA_CLI_SERVER']);
			} else {
				$_SERVER['CELEMA_CLI_SERVER'] = $previous;
			}
		}

		$this->assertStringContainsString('RuntimeException: unhandled in dev', $log);
		$this->assertStringContainsString('Trace:', $log);
	}

	public function testFailureHidesTheExceptionWhenPhpDoesNotDisplayErrors(): void
	{
		$app = App::create();
		$app->logger(new RecordingLogger());
		$app->emitter($emitter = new RecordingEmitter());
		$app->get('/', static fn() => throw new RuntimeException('hidden in production'));

		foreach (['0', 'stderr'] as $setting) {
			// @mago-expect lint:no-ini-set
			$previous = ini_set('display_errors', $setting);

			try {
				$app->run($this->request());
			} finally {
				// @mago-expect lint:no-ini-set
				ini_set('display_errors', (string) $previous);
			}
		}

		$this->assertSame(['500 Internal Server Error', '500 Internal Server Error'], $emitter->bodies());
	}

	public function testDebugRethrowFromTheErrorHandlerIsAnswered(): void
	{
		$app = App::create();
		$app->errorHandler(new Handler($app->factory()->responseFactory(), debug: true));
		$app->logger($logger = new RecordingLogger());
		$app->emitter($emitter = new RecordingEmitter());
		$app->get('/', static fn() => throw new RuntimeException('debug failure'));

		$app->run($this->request());

		$this->assertSame(500, $emitter->responses[0]->getStatusCode());
		$this->assertSame(['Unhandled exception for {method} {path}'], $logger->messages());
		$this->assertSame(false, $app->reusable());
	}

	public function testEmitterFailureIsAnsweredWithMinimal500(): void
	{
		$app = $this->countingApp();
		$app->logger($logger = new RecordingLogger());
		$app->emitter($emitter = new RecordingEmitter(failures: 1));

		$result = $app->run($this->request());

		$this->assertSame(false, $result);
		$this->assertCount(1, $emitter->bodies());
		$this->assertStringStartsWith('500 Internal Server Error', $emitter->bodies()[0]);
		$this->assertSame(
			'Output already present in the output buffer',
			$logger->exception(0)->getMessage(),
		);
		$this->assertSame(false, $app->reusable());
	}

	public function testOutputBuffersOpenedByAFailedRequestAreDiscarded(): void
	{
		$app = App::create();
		$app->logger(new RecordingLogger());
		$app->emitter($emitter = new RecordingEmitter());
		$app->get('/', static function (): never {
			ob_start();
			echo 'partial';

			throw new RuntimeException('view failed');
		});
		$level = ob_get_level();

		$app->run($this->request());

		$this->assertSame($level, ob_get_level());
		$this->assertCount(1, $emitter->bodies());
		$this->assertStringStartsWith('500 Internal Server Error', $emitter->bodies()[0]);
	}

	public function testTeardownFailuresAreLoggedWithoutReplacingTheResponse(): void
	{
		$app = $this->countingApp();
		$app->logger($logger = new RecordingLogger());
		$app->emitter($emitter = new RecordingEmitter());
		$app->register('failing', static fn() => new class implements Resettable {
			public function reset(): void
			{
				throw new RuntimeException('reset failed');
			}
		})->scoped();
		$app->get('/failing', static function (Container $scope, Factory $factory): Response {
			$scope->get('failing');

			return Response::create($factory)->text('ok');
		});
		$secondRan = false;
		$app->teardown(static fn() => throw new RuntimeException('hook failed'));
		$app->teardown(static function () use (&$secondRan): void {
			$secondRan = true;
		});

		$result = $app->run($this->request(['REQUEST_URI' => '/failing']));

		$this->assertInstanceOf(ResponseInterface::class, $result);
		$this->assertSame(['ok'], $emitter->bodies());
		$this->assertSame(true, $secondRan);
		$this->assertSame(
			['Request teardown failed for {method} {path}', 'Request teardown failed for {method} {path}'],
			$logger->messages(),
		);
		$this->assertSame('reset failed', $logger->exception(0)->getMessage());
		$this->assertSame('hook failed', $logger->exception(1)->getMessage());
		$this->assertSame(false, $app->reusable());
	}

	public function testFailuresAreWrittenToTheErrorLogWithoutLogger(): void
	{
		$app = App::create();
		$app->emitter(new RecordingEmitter());
		$app->get('/', static fn() => throw new RuntimeException('logged without logger'));

		$request = $this->request();
		$log = $this->captureErrorLog(static fn() => $app->run($request));

		$this->assertStringContainsString(
			'Unhandled exception for GET /: RuntimeException: logged without logger',
			$log,
		);
	}

	public function testUnresolvableLoggerFallsBackToTheErrorLog(): void
	{
		$app = App::create();
		$app->register(LoggerInterface::class, RecordingLogger::class)->scoped();
		$app->emitter(new RecordingEmitter());
		$app->get('/', static fn() => throw new RuntimeException('scoped logger'));

		$request = $this->request();
		$log = $this->captureErrorLog(static fn() => $app->run($request));

		$this->assertStringContainsString('scoped logger', $log);
		$this->assertMatchesRegularExpression('/Logging failed: [\\\\\w]+Exception: /', $log);
	}

	public function testFailingLoggerDoesNotPreventThe500Response(): void
	{
		$app = App::create();
		$app->logger(new FailingLogger());
		$app->emitter($emitter = new RecordingEmitter());
		$app->get('/', static fn() => throw new RuntimeException('view failed'));
		$tornDown = false;
		$app->teardown(static function () use (&$tornDown): void {
			$tornDown = true;
		});

		$request = $this->request();
		$log = $this->captureErrorLog(static fn() => $app->run($request));

		$this->assertSame(500, $emitter->responses[0]->getStatusCode());
		$this->assertSame(true, $tornDown);
		$this->assertStringContainsString('Logging failed: RuntimeException: log not writable', $log);
		$this->assertStringContainsString('Unhandled exception for GET /: RuntimeException: view failed', $log);
	}

	public function testFailingLoggerDoesNotReplaceTheResponseDuringTeardown(): void
	{
		$app = $this->countingApp();
		$app->logger(new FailingLogger());
		$app->emitter($emitter = new RecordingEmitter());
		$app->teardown(static fn() => throw new RuntimeException('hook failed'));

		$request = $this->request();
		$result = null;
		$log = $this->captureErrorLog(static function () use ($app, $request, &$result): void {
			$result = $app->run($request);
		});

		$this->assertInstanceOf(ResponseInterface::class, $result);
		$this->assertSame(['1'], $emitter->bodies());
		$this->assertStringContainsString('Request teardown failed for GET /: RuntimeException: hook failed', $log);
		$this->assertSame(false, $app->reusable());
	}

	public function testFailedHeadRequestIsAnsweredWithoutBody(): void
	{
		$app = App::create();
		$app->logger(new RecordingLogger());
		$app->emitter($emitter = new RecordingEmitter());
		$app->head('/', static fn() => throw new RuntimeException('view failed'));
		$app->get('/', static fn() => throw new RuntimeException('view failed'));

		$app->run($this->request(['REQUEST_METHOD' => 'HEAD']));
		$app->run($this->request());

		$this->assertSame([500, 500], array_map(
			static fn(ResponseInterface $response): int => $response->getStatusCode(),
			$emitter->responses,
		));
		$this->assertSame([true, false], $emitter->withoutBody);
	}

	public function testHandleDoesNotEmitAndTearsDown(): void
	{
		SapiState::reset();
		$app = $this->countingApp();
		$app->emitter($emitter = new RecordingEmitter());
		$tornDown = false;
		$app->teardown(static function () use (&$tornDown): void {
			$tornDown = true;
		});

		$response = $app->handle($this->request());

		$this->assertSame('1', (string) $response->getBody());
		$this->assertSame([], $emitter->responses);
		$this->assertSame(true, $tornDown);
		$this->assertSame(0, SapiState::$endedRequests);
	}

	public function testHandleRethrowsAndMarksTheAppUnusable(): void
	{
		$app = App::create();
		$app->get('/', static fn() => throw new RuntimeException('handle failed'));
		$tornDown = false;
		$app->teardown(static function () use (&$tornDown): void {
			$tornDown = true;
		});

		try {
			$app->handle($this->request());
			$this->fail('Expected the exception to leave handle()');
		} catch (RuntimeException $e) {
			$this->assertSame('handle failed', $e->getMessage());
		}

		$this->assertSame(true, $tornDown);
		$this->assertSame(false, $app->reusable());
	}

	public function testHandledHttpErrorsLeaveTheAppReusable(): void
	{
		$app = App::create();
		$app->errorHandler(new Handler($app->factory()->responseFactory()));
		$app->get('/', static fn() => throw new HttpNotFound());

		$this->assertSame(404, $app->handle($this->request())->getStatusCode());
		$this->assertSame(true, $app->reusable());
	}

	public function testRegistrationAfterTheFirstRequestFails(): void
	{
		$app = $this->countingApp();
		$app->emitter(new RecordingEmitter());
		$app->run($this->request());

		$this->throws(ContainerException::class, 'sealed');

		$app->register('late', Counter::class);
	}

	/** @param ?Closure(Counter): void $observe */
	private function countingApp(?Closure $observe = null): App
	{
		$app = App::create();
		$app->register(Counter::class, Counter::class)->scoped();
		$app->get('/', static function (Counter $counter, Factory $factory) use ($observe): Response {
			$counter->count++;

			if ($observe !== null) {
				$observe($counter);
			}

			return Response::create($factory)->text((string) $counter->count);
		});

		return $app;
	}

	private function beforeTrail(string $name): Before
	{
		return new readonly class($name) implements Before {
			public function __construct(
				private string $name,
			) {}

			public function handle(ServerRequestInterface $request): ServerRequestInterface
			{
				return $request->withAttribute('trail', [
					...((array) $request->getAttribute('trail', [])),
					$this->name,
				]);
			}

			public function replace(Before $handler): bool
			{
				return false;
			}
		};
	}

	private function afterTag(string $name): After
	{
		return new readonly class($name) implements After {
			public function __construct(
				private string $name,
			) {}

			public function handle(mixed $data): mixed
			{
				assert($data instanceof ResponseInterface, 'The view returns a response');

				return $data->withAddedHeader('X-After', $this->name);
			}

			public function replace(After $handler): bool
			{
				return false;
			}
		};
	}

	private function middlewareTag(string $name): Middleware
	{
		return new readonly class($name) implements Middleware {
			public function __construct(
				private string $name,
			) {}

			public function process(ServerRequestInterface $request, RequestHandler $handler): ResponseInterface
			{
				return $handler->handle($request)->withAddedHeader('X-Middleware', $this->name);
			}
		};
	}
}
