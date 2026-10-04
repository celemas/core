<?php

declare(strict_types=1);

namespace Celema\Core\Tests;

use Celema\Core\Error\Handler;
use Celema\Core\Exception\HttpNotFound;
use Celema\Core\Response as CoreResponse;
use Celema\Core\Tests\Fixtures\Error\TestDebugHandler;
use Celema\Core\Tests\Fixtures\Error\TestRenderer;
use Celema\Core\Tests\Fixtures\FailingLogger;
use Celema\Core\Tests\Fixtures\RecordingLogger;
use Celema\Server\Console;
use DivisionByZeroError;
use ErrorException;
use Exception;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Log\AbstractLogger;
use RuntimeException;
use Throwable;

final class ErrorHandlerTest extends TestCase
{
	public function testProcessCatchesThrowable(): void
	{
		$handler = new Handler($this->factory()->responseFactory());
		$handler->renderer(new TestRenderer(), Throwable::class);
		$response = $handler->process($this->request(), new class implements RequestHandler {
			public function handle(Request $request): Response
			{
				throw new Exception('test message middleware');
			}
		});

		$this->assertSame(
			Exception::class . ' rendered GET test message middleware',
			(string) $response->getBody(),
		);
	}

	public function testProcessReportsDeprecationWithoutInterruptingRequest(): void
	{
		$logger = new class extends AbstractLogger {
			/** @var list<array{level: mixed, message: string, context: array<string, mixed>}> */
			public array $records = [];

			/** @param array<string, mixed> $context */
			public function log(mixed $level, string|\Stringable $message, array $context = []): void
			{
				$this->records[] = [
					'level' => $level,
					'message' => (string) $message,
					'context' => $context,
				];
			}
		};
		$expected = $this->response();
		$expected->getBody()->write('completed');
		$handler = new Handler($this->factory()->responseFactory());
		$handler->logger($logger);
		$reporting = error_reporting(E_ALL);

		try {
			$response = $handler->process(
				$this->request(),
				new class($expected) implements RequestHandler {
					public function __construct(
						private readonly Response $response,
					) {}

					public function handle(Request $request): Response
					{
						trigger_error('deprecated call', E_USER_DEPRECATED);

						return $this->response;
					}
				},
			);
		} finally {
			error_reporting($reporting);
		}

		$this->assertSame($expected, $response);
		$this->assertSame('completed', (string) $response->getBody());
		$this->assertSame('notice', $logger->records[0]['level']);
		$this->assertSame('PHP {type}: {diagnostic} in {file} on line {line}', $logger->records[0]['message']);
		$exception = $logger->records[0]['context']['exception'] ?? null;
		$this->assertInstanceOf(ErrorException::class, $exception);
		$this->assertSame('deprecated call', $exception->getMessage());
		$this->assertSame(E_USER_DEPRECATED, $exception->getSeverity());
		$this->assertSame('Deprecated', $logger->records[0]['context']['type']);
		$this->assertSame('deprecated call', $logger->records[0]['context']['diagnostic']);
	}

	public function testProcessScopesPhpErrorHandler(): void
	{
		$called = false;
		$reporting = error_reporting(E_ALL);
		set_error_handler(static function () use (&$called): bool {
			$called = true;

			return true;
		});

		try {
			$handler = new Handler($this->factory()->responseFactory());
			$handler->renderer(new TestRenderer(), ErrorException::class);
			$response = $handler->process($this->request(), new class implements RequestHandler {
				public function handle(Request $request): Response
				{
					trigger_error('scoped warning', E_USER_WARNING);

					throw new RuntimeException('Unreachable.');
				}
			});

			trigger_error('restored warning', E_USER_WARNING);
		} finally {
			restore_error_handler();
			error_reporting($reporting);
		}

		$this->assertSame(
			ErrorException::class . ' rendered GET scoped warning',
			(string) $response->getBody(),
		);
		$this->assertTrue($called);
	}

	public function testHandleErrorHonorsErrorReporting(): void
	{
		$handler = new Handler($this->factory()->responseFactory());
		$reporting = error_reporting(E_ALL);

		try {
			$this->assertFalse($handler->handleError(0, 'ignored'));
			$handler->handleError(E_WARNING, 'warning message', 'file.php', 12);
		} catch (ErrorException $e) {
			$this->assertSame('warning message', $e->getMessage());
			$this->assertSame(0, $e->getCode());
			$this->assertSame(E_WARNING, $e->getSeverity());
			$this->assertSame('file.php', $e->getFile());
			$this->assertSame(12, $e->getLine());

			return;
		} finally {
			error_reporting($reporting);
		}

		$this->fail('ErrorException was not thrown.');
	}

	public function testHandleErrorDelegatesDeprecationsWithoutLogger(): void
	{
		$handler = new Handler($this->factory()->responseFactory());
		$reporting = error_reporting(E_ALL);

		try {
			$this->assertFalse($handler->handleError(E_DEPRECATED, 'deprecated'));
			$this->assertFalse($handler->handleError(E_USER_DEPRECATED, 'user deprecated'));
		} finally {
			error_reporting($reporting);
		}
	}

	/** @return iterable<string, array{int, string}> */
	public static function diagnostics(): iterable
	{
		yield 'deprecation' => [E_USER_DEPRECATED, 'Deprecated'];
		yield 'notice' => [E_USER_NOTICE, 'Notice'];
		yield 'warning' => [E_USER_WARNING, 'Warning'];
		yield 'other' => [E_RECOVERABLE_ERROR, 'Error'];
	}

	#[DataProvider('diagnostics')]
	public function testLoggedDiagnosticsNameTheirType(int $level, string $type): void
	{
		$logger = new RecordingLogger();
		$handler = new Handler($this->factory()->responseFactory(), exceptionLevels: 0);
		$handler->logger($logger);
		$reporting = error_reporting(E_ALL);

		try {
			$handler->handleError($level, 'diagnostic', 'view.php', 7);
		} finally {
			error_reporting($reporting);
		}

		$this->assertSame($type, $logger->records[0]['context']['type']);
	}

	public function testDeprecationsCanBeConvertedToExceptions(): void
	{
		$handler = new Handler($this->factory()->responseFactory(), exceptionLevels: E_ALL);
		$reporting = error_reporting(E_ALL);

		try {
			$this->throws(ErrorException::class, 'strict deprecation');
			$handler->handleError(E_USER_DEPRECATED, 'strict deprecation');
		} finally {
			error_reporting($reporting);
		}
	}

	public function testDefaultRendererHandlesUnmatchedException(): void
	{
		$handler = new Handler($this->factory()->responseFactory());
		$handler->logger(new RecordingLogger());
		$handler->renderer(new TestRenderer());
		$response = $handler->response(new DivisionByZeroError('test'), $this->request());

		$this->assertSame(
			DivisionByZeroError::class . ' rendered GET test',
			(string) $response->getBody(),
		);
	}

	public function testFallbackUsesHttpStatusAndEscapesTitle(): void
	{
		$request = $this->request();
		$handler = new Handler($this->factory()->responseFactory());
		$response = $handler->response(new HttpNotFound($request, message: '<missing>'), $request);

		$this->assertSame(404, $response->getStatusCode());
		$this->assertSame('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
		$this->assertSame('<h1>404 &lt;missing&gt;</h1>', (string) $response->getBody());
	}

	public function testFallbackUsesServerErrorForGenericException(): void
	{
		$handler = new Handler($this->factory()->responseFactory());
		$handler->logger(new RecordingLogger());
		$response = $handler->response(new Exception('Boom'), $this->request());

		$this->assertSame(500, $response->getStatusCode());
		$this->assertSame('<h1>500 Internal Server Error</h1>', (string) $response->getBody());
	}

	public function testDebugHandlerHandlesUnmatchedException(): void
	{
		$handler = new Handler($this->factory()->responseFactory(), debug: true);
		$handler->debugHandler(new TestDebugHandler());
		$response = $handler->response(new DivisionByZeroError('test'), $this->request());

		$this->assertSame(DivisionByZeroError::class . ' test', (string) $response->getBody());
	}

	public function testDevServerMarksHandledServerExceptions(): void
	{
		$this->withCliServer(function (): void {
			Console::clearException();
			$handler = new Handler($this->factory()->responseFactory());
			$handler->logger(new RecordingLogger());
			$handler->renderer(new TestRenderer());

			$handler->response(new Exception('Boom'), $this->request());

			$this->assertTrue(Console::hasException());
			Console::clearException();
		});
	}

	public function testDevServerDoesNotMarkHandledClientErrors(): void
	{
		$this->withCliServer(function (): void {
			Console::clearException();
			$request = $this->request();
			$handler = new Handler($this->factory()->responseFactory());

			$handler->response(new HttpNotFound($request), $request);

			$this->assertFalse(Console::hasException());
		});
	}

	public function testDebugModeRethrowsUnmatchedExceptionWithoutDebugHandler(): void
	{
		$handler = new Handler($this->factory()->responseFactory(), debug: true);

		$this->throws(DivisionByZeroError::class, 'test');

		$handler->response(new DivisionByZeroError('test'), $this->request());
	}

	public function testLoggerReceivesMatchedAndUnmatchedExceptions(): void
	{
		$logger = new class extends AbstractLogger {
			/** @var list<array{level: mixed, message: string}> */
			public array $records = [];

			/** @param array<string, mixed> $context */
			public function log(mixed $level, string|\Stringable $message, array $context = []): void
			{
				$this->records[] = [
					'level' => $level,
					'message' => (string) $message,
				];
			}
		};
		$handler = new Handler($this->factory()->responseFactory());
		$handler->logger($logger);
		$handler->renderer(new TestRenderer(), ErrorException::class)->log('critical');

		$handler->response(new ErrorException('matched'), $this->request());
		$handler->response(new Exception('unmatched'), $this->request());
		$defaultHandler = new Handler($this->factory()->responseFactory());
		$defaultHandler->logger($logger);
		$defaultHandler->renderer(new TestRenderer())->log('notice');
		$defaultHandler->response(new Exception('default'), $this->request());

		$this->assertSame('critical', $logger->records[0]['level']);
		$this->assertSame('Server error {status} for {method} {path}', $logger->records[0]['message']);
		$this->assertSame('critical', $logger->records[1]['level']);
		$this->assertSame('Server error {status} for {method} {path}', $logger->records[1]['message']);
		$this->assertSame('notice', $logger->records[2]['level']);
		$this->assertSame('Server error {status} for {method} {path}', $logger->records[2]['message']);
	}

	public function testLoggedExceptionsCarryTheRequestMethodAndPath(): void
	{
		$logger = new RecordingLogger();
		$handler = new Handler($this->factory()->responseFactory());
		$handler->logger($logger);
		$request = $this->request(['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/reset?token=secret']);

		$handler->response(new Exception('Boom'), $request);

		$this->assertSame('POST', $logger->records[0]['context']['method']);
		$this->assertSame('/reset', $logger->records[0]['context']['path']);
	}

	public function testClientErrorsAreLoggedAtTheLevelOfTheirRenderer(): void
	{
		$logger = new RecordingLogger();
		$handler = new Handler($this->factory()->responseFactory());
		$handler->logger($logger);
		$handler->renderer(new TestRenderer(), HttpNotFound::class)->log('info');
		$request = $this->request(['REQUEST_URI' => '/missing']);

		$handler->response(new HttpNotFound($request), $request);

		$this->assertSame('info', $logger->records[0]['level']);
		$this->assertSame('Client error {status} for {method} {path}', $logger->records[0]['message']);
		$this->assertSame(404, $logger->records[0]['context']['status']);
		$this->assertSame('/missing', $logger->records[0]['context']['path']);
	}

	public function testUnmatchedClientErrorsAreNotLogged(): void
	{
		$logger = new RecordingLogger();
		$handler = new Handler($this->factory()->responseFactory());
		$handler->logger($logger);
		$request = $this->request();

		$handler->response(new HttpNotFound($request), $request);

		$this->assertSame([], $logger->records);
	}

	public function testServerErrorsGoToTheErrorLogWithoutALogger(): void
	{
		$handler = new Handler($this->factory()->responseFactory());
		$request = $this->request(['REQUEST_URI' => '/broken']);

		$log = $this->captureErrorLog(static fn() => $handler->response(new Exception('Boom'), $request));

		$this->assertStringContainsString('Server error 500 for GET /broken: Exception: Boom', $log);
	}

	public function testAFailingLoggerKeepsTheResponseAndTheException(): void
	{
		$handler = new Handler($this->factory()->responseFactory());
		$handler->logger(new FailingLogger());
		$handler->renderer(new TestRenderer());
		$request = $this->request(['REQUEST_URI' => '/broken']);
		$response = null;

		$log = $this->captureErrorLog(static function () use ($handler, $request, &$response): void {
			$response = $handler->response(new Exception('Boom'), $request);
		});

		$this->assertSame(Exception::class . ' rendered GET Boom', (string) $response?->getBody());
		$this->assertStringContainsString('Logging failed: RuntimeException: log not writable', $log);
		$this->assertStringContainsString('Server error 500 for GET /broken: Exception: Boom', $log);
	}

	public function testHandleErrorLetsPhpReportWhenTheLoggerFails(): void
	{
		$handler = new Handler($this->factory()->responseFactory());
		$handler->logger(new FailingLogger());
		$reporting = error_reporting(E_ALL);

		try {
			$log = $this->captureErrorLog(function () use ($handler): void {
				$this->assertFalse($handler->handleError(E_USER_DEPRECATED, 'old api'));
			});
		} finally {
			error_reporting($reporting);
		}

		$this->assertStringContainsString('Logging failed: RuntimeException: log not writable', $log);
	}

	public function testAppErrorHandlerWrapsRouting(): void
	{
		$app = $this->app();
		$app->errorHandler(new Handler($app->factory()->responseFactory()));
		$request = $app->factory()->serverRequestFactory()->createServerRequest('GET', '/missing');
		ob_start();

		try {
			$response = $app->run($request);
			$output = ob_get_contents();
		} finally {
			ob_end_clean();
		}

		$this->assertSame(404, $response->getStatusCode());
		$this->assertSame('<h1>404 Not Found</h1>', $output);
	}

	public function testAppErrorHandlerMapsMethodNotAllowed(): void
	{
		$app = $this->app();
		$app->errorHandler(new Handler($app->factory()->responseFactory()));
		$app->get('/only-get', static fn(): CoreResponse => CoreResponse::create($app->factory())->body(
			'ok',
		));
		$request = $app->factory()->serverRequestFactory()->createServerRequest('POST', '/only-get');
		ob_start();

		try {
			$response = $app->run($request);
			$output = ob_get_contents();
		} finally {
			ob_end_clean();
		}

		$this->assertSame(405, $response->getStatusCode());
		$this->assertSame('<h1>405 Method Not Allowed</h1>', $output);
	}

	/** @param callable(): void $callback */
	private function withCliServer(callable $callback): void
	{
		$oldValue = $_SERVER['CELEMA_CLI_SERVER'] ?? null;
		$_SERVER['CELEMA_CLI_SERVER'] = '1';

		try {
			$callback();
		} finally {
			Console::clearException();

			if ($oldValue === null) {
				unset($_SERVER['CELEMA_CLI_SERVER']);
			} else {
				$_SERVER['CELEMA_CLI_SERVER'] = $oldValue;
			}
		}
	}
}
