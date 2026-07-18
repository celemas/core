<?php

declare(strict_types=1);

namespace Celema\Core\Tests;

use Celema\Core\Response;

final class ResponseHelpersTest extends TestCase
{
	public function testGetSetPsr7Response(): void
	{
		$psr = $this->response();
		$response = new Response($psr);

		$this->assertSame($psr, $response->unwrap());

		$response->wrap($this->response());

		$this->assertNotSame($psr, $response->unwrap());
	}

	public function testGetStatusCode(): void
	{
		$response = new Response($this->response());

		$this->assertSame(200, $response->getStatusCode());
		$this->assertSame('OK', $response->getReasonPhrase());
	}

	public function testSetStatusCode(): void
	{
		$response = new Response($this->response(), $this->factory()->streamFactory());
		$response->status(404);

		$this->assertSame(404, $response->getStatusCode());
		$this->assertSame('Not Found', $response->getReasonPhrase());
	}

	public function testSetStatusCodeAndReasonPhrase(): void
	{
		$response = new Response($this->response(), $this->factory()->streamFactory());
		$response->status(404, 'Nothing to see');

		$this->assertSame(404, $response->getStatusCode());
		$this->assertSame('Nothing to see', $response->getReasonPhrase());
	}

	public function testProtocolVersion(): void
	{
		$response = new Response($this->response(), $this->factory()->streamFactory());

		$this->assertSame('1.1', $response->getProtocolVersion());

		$response->protocolVersion('2.0');

		$this->assertSame('2.0', $response->getProtocolVersion());
	}
}
