<?php

declare(strict_types=1);

namespace Celema\Core\Tests;

use Celema\Core\Exception\OutOfBoundsException;
use Celema\Core\Request;

final class RequestCookieTest extends TestCase
{
	public function testCookie(): void
	{
		$request = new Request($this->request(cookie: [
			'chuck' => 'schuldiner',
			'born' => '1967',
		]));

		$this->assertSame('schuldiner', $request->cookie('chuck'));
		$this->assertSame('1967', $request->cookie('born'));
	}

	public function testCookieDefault(): void
	{
		$request = new Request($this->request());

		$this->assertSame('the default', $request->cookie('doesnotexist', 'the default'));
	}

	public function testCookieFailing(): void
	{
		$this->throws(OutOfBoundsException::class, 'Cookie');

		$request = new Request($this->request());

		$request->cookie('doesnotexist')->toBe(null);
	}

	public function testCookies(): void
	{
		$request = new Request($this->request(cookie: ['chuck' => 'schuldiner', 'born' => '1967']));
		$cookies = $request->cookies();

		$this->assertSame(2, count($cookies));
		$this->assertSame('1967', $cookies['born']);
		$this->assertSame('schuldiner', $cookies['chuck']);
	}
}
