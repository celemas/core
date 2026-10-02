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
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException as PhpRuntimeException;

final class FrankenPhpWorkerTest extends TestCase
{
	private array $server = [];

	protected function setUp(): void
	{
		parent::setUp();

		$this->server = $_SERVER;
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['REQUEST_URI'] = '/';
		$_SERVER['HTTP_HOST'] = 'www.example.com';
		WorkerState::reset();
	}

	protected function tearDown(): void
	{
		$_SERVER = $this->server;
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

		$this->assertSame(['/a', '500 Internal Server Error'], $emitter->bodies());
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
		$this->assertSame(1, filesize($file));
		$handle = fopen($file, 'a');
		fwrite($handle, 'b');
		fclose($handle);
		$app = App::create();
		$app->emitter($emitter = new RecordingEmitter());
		$app->get('/', static fn(Factory $factory): Response => Response::create($factory)->text(
			(string) filesize($file),
		));
		$_SERVER['FRANKENPHP_WORKER'] = '1';
		WorkerState::request();

		try {
			$app->serve();
		} finally {
			unlink($file);
		}

		$this->assertSame(['2'], $emitter->bodies());
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

	public function testInvalidRequestLimitIsRejected(): void
	{
		$this->throws(RuntimeException::class, FrankenPhpWorker::MAX_REQUESTS);

		$_SERVER[FrankenPhpWorker::MAX_REQUESTS] = 'many';
		FrankenPhpWorker::configure(static fn(callable $callback): bool => false);
	}

	public function testInvalidMemoryLimitIsRejected(): void
	{
		$this->throws(RuntimeException::class, FrankenPhpWorker::MAX_MEMORY);

		$_SERVER[FrankenPhpWorker::MAX_MEMORY] = '80%';
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
