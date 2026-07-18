<?php

declare(strict_types=1);

namespace Celema\Core\Tests;

use Celema\Core\Exception\OutOfBoundsException;
use Celema\Core\Request;

final class RequestServerTest extends TestCase
{
	public function testServer(): void
	{
		$request = new Request($this->request());

		$this->assertSame('www.example.com', $request->server('HTTP_HOST'));
		$this->assertSame('HTTP/1.1', $request->server('SERVER_PROTOCOL'));
	}

	public function testServerDefault(): void
	{
		$request = new Request($this->request());

		$this->assertSame('the default', $request->server('doesnotexist', 'the default'));
	}

	public function testServerFailing(): void
	{
		$this->throws(OutOfBoundsException::class, 'Server');

		$request = new Request($this->request());

		$this->assertSame(null, $request->server('doesnotexist'));
	}

	public function testServerParams(): void
	{
		$request = new Request($this->request());
		$params = $request->serverParams();

		$this->assertSame('www.example.com', $params['HTTP_HOST']);
		$this->assertSame('HTTP/1.1', $params['SERVER_PROTOCOL']);
	}
}
