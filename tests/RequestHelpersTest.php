<?php

declare(strict_types=1);

namespace Celema\Core\Tests;

use Celema\Core\Request;
use Nyholm\Psr7\Stream;

final class RequestHelpersTest extends TestCase
{
	public function testHelperMethods(): void
	{
		$request = new Request($this->request());

		$this->assertSame('GET', $request->method());
		$this->assertSame(true, $request->isMethod('GET'));
		$this->assertSame(false, $request->isMethod('POST'));
	}

	public function testUriHelpers(): void
	{
		$request = new Request($this->request(server: ['REQUEST_URI' => '/albums']));

		$this->assertSame('/albums', $request->uri()->getPath());
		$this->assertSame('http://www.example.com/albums', (string) $request->uri());

		$request = new Request($this->request(server: [
			'QUERY_STRING' => 'from=1988&to=1991',
			'REQUEST_URI' => '/albums?from=1988&to=1991',
		]));

		$this->assertSame('http://www.example.com/albums?from=1988&to=1991', (string) $request->uri());
		$this->assertSame('www.example.com', $request->uri()->getHost());
		$this->assertSame('http://www.example.com', $request->origin());
		$this->assertSame('/albums?from=1988&to=1991', $request->target());
	}

	public function testBody(): void
	{
		$this->assertSame('', (string) new Request($this->request())->body());
	}

	public function testJson(): void
	{
		$stream = Stream::create(
			'[{"title": "Leprosy", "released": 1988}, {"title": "Human", "released": 1991}]',
		);
		$request = new Request($this->request()->withBody($stream));

		$this->assertSame(
			[['title' => 'Leprosy', 'released' => 1988], ['title' => 'Human', 'released' => 1991]],
			$request->json(),
		);
	}

	public function testJsonEmpty(): void
	{
		$request = new Request($this->request());

		$this->assertSame(null, $request->json());
	}

	public function testGettingAndSettingPsr7Instance(): void
	{
		$psr = $this->request();
		$request = new Request($this->request());
		$request->wrap($psr);

		$this->assertSame($psr, $request->unwrap());
	}
}
