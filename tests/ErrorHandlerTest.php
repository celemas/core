<?php

declare(strict_types=1);

namespace Celemas\Core\Tests;

use Celemas\Core\Error\Handler;
use Celemas\Core\Exception\HttpNotFound;
use Celemas\Core\Response as CoreResponse;
use Celemas\Core\Tests\Fixtures\Error\TestDebugHandler;
use Celemas\Core\Tests\Fixtures\Error\TestRenderer;
use DivisionByZeroError;
use ErrorException;
use Exception;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
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

	public function testDefaultRendererHandlesUnmatchedException(): void
	{
		$handler = new Handler($this->factory()->responseFactory());
		$handler->renderer(new TestRenderer());
		$response = $handler->response(new DivisionByZeroError('test'));

		$this->assertSame(
			DivisionByZeroError::class . ' rendered without request test',
			(string) $response->getBody(),
		);
	}

	public function testFallbackUsesHttpStatusAndEscapesTitle(): void
	{
		$handler = new Handler($this->factory()->responseFactory());
		$response = $handler->response(new HttpNotFound($this->request(), message: '<missing>'));

		$this->assertSame(404, $response->getStatusCode());
		$this->assertSame('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
		$this->assertSame('<h1>404 &lt;missing&gt;</h1>', (string) $response->getBody());
	}

	public function testFallbackUsesServerErrorForGenericException(): void
	{
		$handler = new Handler($this->factory()->responseFactory());
		$response = $handler->response(new Exception('Boom'));

		$this->assertSame(500, $response->getStatusCode());
		$this->assertSame('<h1>500 Internal Server Error</h1>', (string) $response->getBody());
	}

	public function testDebugHandlerHandlesUnmatchedException(): void
	{
		$handler = new Handler($this->factory()->responseFactory(), debug: true);
		$handler->debugHandler(new TestDebugHandler());
		$response = $handler->response(new DivisionByZeroError('test'));

		$this->assertSame(DivisionByZeroError::class . ' test', (string) $response->getBody());
	}

	public function testDebugModeRethrowsUnmatchedExceptionWithoutDebugHandler(): void
	{
		$handler = new Handler($this->factory()->responseFactory(), debug: true);

		$this->throws(DivisionByZeroError::class, 'test');

		$handler->response(new DivisionByZeroError('test'));
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
		$this->assertSame('Matched exception', $logger->records[0]['message']);
		$this->assertSame('alert', $logger->records[1]['level']);
		$this->assertSame('Unmatched exception', $logger->records[1]['message']);
		$this->assertSame('notice', $logger->records[2]['level']);
		$this->assertSame('Matched exception', $logger->records[2]['message']);
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

	#[RunInSeparateProcess]
	public function testEmitExceptionLogsInDebugMode(): void
	{
		$handler = new Handler($this->factory()->responseFactory(), debug: true);
		$handler->renderer(new TestRenderer(), Throwable::class);
		ob_start();

		try {
			$handler->emitException(new Exception('Boom'));
			$output = ob_get_contents();
		} finally {
			ob_end_clean();
		}

		$this->assertSame(Exception::class . ' rendered without request Boom', $output);
	}

	#[RunInSeparateProcess]
	public function testGlobalHandlersAreExplicitAndRestorable(): void
	{
		$handler = new Handler($this->factory()->responseFactory());
		$handler->registerHandlers();
		$handler->registerHandlers();
		ob_start();

		try {
			$handler->emitException(new Exception('Boom'));
			$output = ob_get_contents();
		} finally {
			ob_end_clean();
			$handler->restoreHandlers();
			$handler->restoreHandlers();
		}

		$this->assertSame('<h1>500 Internal Server Error</h1>', $output);
	}
}
