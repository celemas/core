<?php

declare(strict_types=1);

namespace Celema\Core\Tests;

use Celema\Core\App;
use Celema\Core\Exception\RuntimeException;
use Celema\Core\Factory\Factory;
use Celema\Core\Response;
use Celema\Core\Runtime\FrankenPhpWorker;
use Celema\Core\Tests\Fixtures\RecordingEmitter;
use Celema\Core\Tests\Fixtures\RecordingLogger;
use Celema\Core\Tests\Fixtures\WorkerState;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException as PhpRuntimeException;

final class FrankenPhpWorkerTest extends TestCase
{
	private array $server = [];
	private array $env = [];

	protected function setUp(): void
	{
		parent::setUp();

		$this->server = $_SERVER;
		$this->env = $_ENV;
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['REQUEST_URI'] = '/';
		$_SERVER['HTTP_HOST'] = 'www.example.com';
		WorkerState::reset();
	}

	protected function tearDown(): void
	{
		$_SERVER = $this->server;
		$_ENV = $this->env;
		putenv(FrankenPhpWorker::MAX_REQUESTS);
		WorkerState::reset();

		parent::tearDown();
	}

	public function testServeRunsOneRequestOutsideAWorker(): void
	{
		[$app, $emitter] = $this->workerApp();

		$response = $app->serve();

		$this->assertInstanceOf(ResponseInterface::class, $response);
		$this->assertSame(['/'], $emitter->bodies());
		$this->assertSame(0, WorkerState::$calls);
	}

	public function testWorkerHandlesRequestsUntilFrankenPhpStops(): void
	{
		[$app, $emitter] = $this->workerApp();
		$_SERVER['FRANKENPHP_WORKER'] = '1';
		WorkerState::request(['REQUEST_URI' => '/a']);
		WorkerState::request(['REQUEST_URI' => '/b']);
		WorkerState::request(['REQUEST_URI' => '/a']);

		$result = $app->serve();

		$this->assertSame(false, $result);
		$this->assertSame(['/a', '/b', '/a'], $emitter->bodies());
		$this->assertSame(3, WorkerState::$calls);
	}

	public function testWorkerStopsWhenFrankenPhpAsksTo(): void
	{
		[$app, $emitter] = $this->workerApp();
		$_SERVER['FRANKENPHP_WORKER'] = '1';
		WorkerState::request(['REQUEST_URI' => '/a'], stop: true);
		WorkerState::request(['REQUEST_URI' => '/b']);

		$app->serve();

		$this->assertSame(['/a'], $emitter->bodies());
	}

	public function testWorkerRetiresAfterAnUnhandledException(): void
	{
		[$app, $emitter] = $this->workerApp();
		$app->logger(new RecordingLogger());
		$_SERVER['FRANKENPHP_WORKER'] = '1';
		WorkerState::request(['REQUEST_URI' => '/a']);
		WorkerState::request(['REQUEST_URI' => '/fail']);
		WorkerState::request(['REQUEST_URI' => '/b']);

		$app->serve();

		$this->assertCount(2, $emitter->bodies());
		$this->assertSame('/a', $emitter->bodies()[0]);
		$this->assertStringStartsWith('500 Internal Server Error', $emitter->bodies()[1]);
		$this->assertCount(1, WorkerState::$queue);
	}

	public function testWorkerRetiresAfterMaxRequests(): void
	{
		[$app, $emitter] = $this->workerApp();
		$_SERVER['FRANKENPHP_WORKER'] = '1';
		WorkerState::request(['REQUEST_URI' => '/a']);
		WorkerState::request(['REQUEST_URI' => '/b']);
		WorkerState::request(['REQUEST_URI' => '/c']);

		$app->serve(maxRequests: 2);

		$this->assertSame(['/a', '/b'], $emitter->bodies());
	}

	public function testWorkerRetiresAboveMaxMemory(): void
	{
		[$app, $emitter] = $this->workerApp();
		$_SERVER['FRANKENPHP_WORKER'] = '1';
		WorkerState::request(['REQUEST_URI' => '/a']);
		WorkerState::request(['REQUEST_URI' => '/b']);

		$app->serve(maxMemory: 1);

		$this->assertSame(['/a'], $emitter->bodies());
	}

	public function testRequestsSeeFileChangesMadeSincePreviousRequests(): void
	{
		$file = (string) tempnam(sys_get_temp_dir(), 'celema-stat');
		file_put_contents($file, 'a');
		$app = App::create();
		$app->emitter($emitter = new RecordingEmitter());
		$app->get('/', static function (Factory $factory) use ($file): Response {
			$size = filesize($file);
			// PHP clears its stat cache for its own writes, so the change
			// has to come from another process, like a deployment would.
			exec('printf b >> ' . escapeshellarg($file));

			return Response::create($factory)->text((string) $size);
		});
		$_SERVER['FRANKENPHP_WORKER'] = '1';
		WorkerState::request();
		WorkerState::request();

		try {
			$app->serve();
		} finally {
			unlink($file);
		}

		$this->assertSame(['1', '2'], $emitter->bodies());
	}

	public function testWorkerKeepsRunningRequestsWhenClientsDisconnect(): void
	{
		[$app] = $this->workerApp();
		$_SERVER['FRANKENPHP_WORKER'] = '1';
		WorkerState::request();
		$previous = ignore_user_abort();

		try {
			ignore_user_abort(false);
			$app->serve();
			$this->assertSame(1, ignore_user_abort());
		} finally {
			ignore_user_abort((bool) $previous);
		}
	}

	public function testWorkerWithoutMemoryLimitKeepsServing(): void
	{
		[$app, $emitter] = $this->workerApp();
		$_SERVER['FRANKENPHP_WORKER'] = '1';
		WorkerState::request(['REQUEST_URI' => '/a']);
		WorkerState::request(['REQUEST_URI' => '/b']);

		$app->serve(maxMemory: 0);

		$this->assertSame(['/a', '/b'], $emitter->bodies());
	}

	public function testLimitsComeFromTheWorkerEnvironment(): void
	{
		$_SERVER[FrankenPhpWorker::MAX_REQUESTS] = '500';
		$_SERVER[FrankenPhpWorker::MAX_MEMORY] = '64M';

		$worker = FrankenPhpWorker::configure(static fn(callable $callback): bool => false);

		$this->assertSame(500, $worker->maxRequests);
		$this->assertSame(64 * 1024 * 1024, $worker->maxMemory);
	}

	public function testExplicitLimitsWinOverTheEnvironment(): void
	{
		$_SERVER[FrankenPhpWorker::MAX_REQUESTS] = '500';
		$_SERVER[FrankenPhpWorker::MAX_MEMORY] = '64M';

		$worker = FrankenPhpWorker::configure(
			static fn(callable $callback): bool => false,
			maxRequests: 0,
			maxMemory: 1024,
		);

		$this->assertSame(0, $worker->maxRequests);
		$this->assertSame(1024, $worker->maxMemory);
	}

	public function testMemoryLimitDefaultsToEightyPercentOfPhpLimit(): void
	{
		$previous = (string) ini_get('memory_limit');

		try {
			// @mago-expect lint:no-ini-set
			ini_set('memory_limit', '1000M');
			$limited = FrankenPhpWorker::configure(static fn(callable $callback): bool => false);
			// @mago-expect lint:no-ini-set
			ini_set('memory_limit', '-1');
			$unlimited = FrankenPhpWorker::configure(static fn(callable $callback): bool => false);
		} finally {
			// @mago-expect lint:no-ini-set
			ini_set('memory_limit', $previous);
		}

		$this->assertSame(800 * 1024 * 1024, $limited->maxMemory);
		$this->assertSame(0, $unlimited->maxMemory);
		$this->assertSame(0, $limited->maxRequests);
	}

	public function testLimitsAcceptSurroundingWhitespaceAndLowercaseUnits(): void
	{
		$_SERVER[FrankenPhpWorker::MAX_REQUESTS] = ' 5 ';
		$_SERVER[FrankenPhpWorker::MAX_MEMORY] = '64m';

		$worker = FrankenPhpWorker::configure(static fn(callable $callback): bool => false);

		$this->assertSame(5, $worker->maxRequests);
		$this->assertSame(64 * 1024 * 1024, $worker->maxMemory);
	}

	public function testServerVariablesWinOverEnvironmentVariables(): void
	{
		putenv(FrankenPhpWorker::MAX_REQUESTS . '=3');
		$_ENV[FrankenPhpWorker::MAX_REQUESTS] = '2';
		$fromEnv = FrankenPhpWorker::configure(static fn(callable $callback): bool => false);
		$_SERVER[FrankenPhpWorker::MAX_REQUESTS] = '1';
		$fromServer = FrankenPhpWorker::configure(static fn(callable $callback): bool => false);
		unset($_SERVER[FrankenPhpWorker::MAX_REQUESTS], $_ENV[FrankenPhpWorker::MAX_REQUESTS]);
		$fromProcess = FrankenPhpWorker::configure(static fn(callable $callback): bool => false);

		$this->assertSame(2, $fromEnv->maxRequests);
		$this->assertSame(1, $fromServer->maxRequests);
		$this->assertSame(3, $fromProcess->maxRequests);
	}

	/** @return iterable<string, array{string}> */
	public static function invalidRequestLimits(): iterable
	{
		yield 'word' => ['many'];
		yield 'trailing text' => ['12abc'];
		yield 'leading text' => ['abc12'];
	}

	#[DataProvider('invalidRequestLimits')]
	public function testInvalidRequestLimitIsRejected(string $value): void
	{
		$this->throws(
			RuntimeException::class,
			FrankenPhpWorker::MAX_REQUESTS . ' must be a number of requests, 0 for no limit',
		);

		$_SERVER[FrankenPhpWorker::MAX_REQUESTS] = $value;
		FrankenPhpWorker::configure(static fn(callable $callback): bool => false);
	}

	/** @return iterable<string, array{string}> */
	public static function invalidMemoryLimits(): iterable
	{
		yield 'percentage' => ['80%'];
		yield 'leading text' => ['x64M'];
	}

	#[DataProvider('invalidMemoryLimits')]
	public function testInvalidMemoryLimitIsRejected(string $value): void
	{
		$this->throws(
			RuntimeException::class,
			FrankenPhpWorker::MAX_MEMORY . ' must be a number of bytes, optionally with K, M or G, 0 for no limit',
		);

		$_SERVER[FrankenPhpWorker::MAX_MEMORY] = $value;
		FrankenPhpWorker::configure(static fn(callable $callback): bool => false);
	}

	/** @return array{App, RecordingEmitter} */
	private function workerApp(): array
	{
		$app = App::create();
		$app->emitter($emitter = new RecordingEmitter());
		$app->get('/fail', static fn() => throw new PhpRuntimeException('request failed'));
		$app->get('/{path}', static fn(Request $request, Factory $factory): Response => Response::create(
			$factory,
		)->text($request->getUri()->getPath()));
		$app->get('/', static fn(Request $request, Factory $factory): Response => Response::create($factory)->text(
			$request->getUri()->getPath(),
		));

		return [$app, $emitter];
	}
}
