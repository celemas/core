<?php

declare(strict_types=1);

namespace Celema\Core\Tests;

use Celema\Core\Exception\HttpBadRequest;
use Celema\Core\Exception\HttpConflict;
use Celema\Core\Exception\HttpForbidden;
use Celema\Core\Exception\HttpGone;
use Celema\Core\Exception\HttpMethodNotAllowed;
use Celema\Core\Exception\HttpNotFound;
use Celema\Core\Exception\HttpUnauthorized;
use Celema\Core\Request;
use ReflectionMethod;
use stdClass;

final class ExceptionTest extends TestCase
{
	public function testHttpBadRequestException(): void
	{
		$request = $this->request();
		$exception = new HttpBadRequest($request, '400 payload');

		$this->assertSame($request, $exception->request());
		$this->assertSame(400, $exception->statusCode());
		$this->assertSame('400 payload', $exception->payload());
		$this->assertSame('400 Bad Request', $exception->title());

		$exception = new HttpBadRequest($request, '400 payload', 'Other Message', null, 666);

		$this->assertSame($request, $exception->request());
		$this->assertSame(666, $exception->statusCode());
		$this->assertSame('400 payload', $exception->payload());
		$this->assertSame('666 Other Message', $exception->title());
	}

	public function testHttpGoneException(): void
	{
		$request = $this->request();
		$exception = new HttpGone($request, '410 payload');

		$this->assertSame($request, $exception->request());
		$this->assertSame(410, $exception->statusCode());
		$this->assertSame('410 payload', $exception->payload());
		$this->assertSame('410 Gone', $exception->title());

		$exception = new HttpGone($request, '410 payload', 'Other Message', null, 666);

		$this->assertSame($request, $exception->request());
		$this->assertSame(666, $exception->statusCode());
		$this->assertSame('410 payload', $exception->payload());
		$this->assertSame('666 Other Message', $exception->title());
	}

	public function testHttpForbiddenException(): void
	{
		$request = $this->request();
		$exception = new HttpForbidden($request, '403 payload');

		$this->assertSame($request, $exception->request());
		$this->assertSame(403, $exception->statusCode());
		$this->assertSame('403 payload', $exception->payload());
		$this->assertSame('403 Forbidden', $exception->title());

		$exception = new HttpForbidden($request, '403 payload', 'Other Message', null, 666);

		$this->assertSame($request, $exception->request());
		$this->assertSame(666, $exception->statusCode());
		$this->assertSame('403 payload', $exception->payload());
		$this->assertSame('666 Other Message', $exception->title());
	}

	public function testHttpConflictException(): void
	{
		$request = $this->request();
		$exception = new HttpConflict($request, '409 payload');

		$this->assertSame($request, $exception->request());
		$this->assertSame(409, $exception->statusCode());
		$this->assertSame('409 payload', $exception->payload());
		$this->assertSame('409 Conflict', $exception->title());

		$exception = new HttpConflict($request, '409 payload', 'Other Message', null, 666);

		$this->assertSame($request, $exception->request());
		$this->assertSame(666, $exception->statusCode());
		$this->assertSame('409 payload', $exception->payload());
		$this->assertSame('666 Other Message', $exception->title());
	}

	public function testHttpMethodNotAllowedException(): void
	{
		$request = $this->request();
		$exception = new HttpMethodNotAllowed($request, '405 payload');

		$this->assertSame($request, $exception->request());
		$this->assertSame(405, $exception->statusCode());
		$this->assertSame('405 payload', $exception->payload());
		$this->assertSame('405 Method Not Allowed', $exception->title());

		$exception = new HttpMethodNotAllowed($request, '405 payload', 'Other Message', null, 666);

		$this->assertSame($request, $exception->request());
		$this->assertSame(666, $exception->statusCode());
		$this->assertSame('405 payload', $exception->payload());
		$this->assertSame('666 Other Message', $exception->title());
	}

	public function testHttpNotFoundException(): void
	{
		$request = $this->request();
		$exception = new HttpNotFound($request, '404 payload');

		$this->assertSame($request, $exception->request());
		$this->assertSame(404, $exception->statusCode());
		$this->assertSame('404 payload', $exception->payload());
		$this->assertSame('404 Not Found', $exception->title());

		$exception = new HttpNotFound($request, '404 payload', 'Other Message', null, 666);

		$this->assertSame($request, $exception->request());
		$this->assertSame(666, $exception->statusCode());
		$this->assertSame('404 payload', $exception->payload());
		$this->assertSame('666 Other Message', $exception->title());
	}

	public function testHttpUnauthorizedException(): void
	{
		$request = $this->request();
		$exception = new HttpUnauthorized($request, '401 payload');

		$this->assertSame($request, $exception->request());
		$this->assertSame(401, $exception->statusCode());
		$this->assertSame('401 payload', $exception->payload());
		$this->assertSame('401 Unauthorized', $exception->title());

		$exception = new HttpUnauthorized($request, '401 payload', 'Other Message', null, 666);

		$this->assertSame($request, $exception->request());
		$this->assertSame(666, $exception->statusCode());
		$this->assertSame('401 payload', $exception->payload());
		$this->assertSame('666 Other Message', $exception->title());
	}

	public function testHttpExceptionWithWrappedRequest(): void
	{
		$psrRequest = $this->request();
		$request = new Request($psrRequest);
		$exception = new HttpNotFound($request, 'payload');

		$this->assertSame($psrRequest, $exception->request());
	}

	public function testGetPrettyTraceStructure(): void
	{
		$exception = new HttpNotFound();
		$trace = $exception->getPrettyTrace();

		$this->assertStringContainsString('<p class="trace">', $trace);
		$this->assertStringContainsString('<span class="trace-number">', $trace);
		$this->assertStringContainsString('<span class="trace-file">', $trace);
		$this->assertStringContainsString('<span class="trace-line-number">', $trace);
		$this->assertStringContainsString('<code class="trace-code">', $trace);
		$this->assertStringContainsString('#0', $trace);
	}

	public function testFormatTraceArgWithVariousTypes(): void
	{
		$exception = new HttpNotFound();
		$method = new ReflectionMethod($exception, 'formatTraceArg');

		$this->assertSame("'string arg'", $method->invoke($exception, 'string arg'));
		$this->assertSame('Array', $method->invoke($exception, ['array', 'arg']));
		$this->assertSame('NULL', $method->invoke($exception, null));
		$this->assertSame('true', $method->invoke($exception, true));
		$this->assertSame('false', $method->invoke($exception, false));
		$this->assertSame('stdClass', $method->invoke($exception, new stdClass()));
		$this->assertSame('42', $method->invoke($exception, 42));
		$this->assertSame('3.14', $method->invoke($exception, 3.14));
	}

	public function testFormatTraceArgWithResource(): void
	{
		$exception = new HttpNotFound();
		$method = new ReflectionMethod($exception, 'formatTraceArg');

		$resource = fopen('php://memory', 'r');
		$result = $method->invoke($exception, $resource);
		fclose($resource);

		$this->assertSame('stream', $result);
	}
}
