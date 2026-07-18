<?php

declare(strict_types=1);

namespace Celema\Core\Tests;

use Celema\Core\Exception\RuntimeException;
use Celema\Core\Response;
use stdClass;

final class ResponseContentTest extends TestCase
{
	public function testWithContentTypeFromResource(): void
	{
		$resource = fopen('php://temp', 'r+');
		fwrite($resource, '<h1>Chuck resource</h1>');
		$response = Response::create($this->factory())
			->withContentType('text/html', $resource, 404, 'The Phrase');

		$this->assertSame('<h1>Chuck resource</h1>', (string) $response->getBody());
		$this->assertSame('text/html', $response->getHeader('Content-Type')[0]);
	}

	public function testWithContentTypeFromString(): void
	{
		$response = new Response($this->response())->withContentType(
			'text/html',
			'<h1>Chuck String</h1>',
			404,
			'The Phrase',
		);

		$this->assertSame('<h1>Chuck String</h1>', (string) $response->getBody());
		$this->assertSame('text/html', $response->getHeader('Content-Type')[0]);
	}

	public function testWithContentTypeFromStream(): void
	{
		$stream = $this->factory()->stream('<h1>Chuck Stream</h1>');
		$response = new Response($this->response())->withContentType(
			'text/html',
			$stream,
			404,
			'The Phrase',
		);

		$this->assertSame('<h1>Chuck Stream</h1>', (string) $response->getBody());
		$this->assertSame('text/html', $response->getHeader('Content-Type')[0]);
	}

	public function testWithContentTypeFromStringable(): void
	{
		$response = Response::create($this->factory())->withContentType(
			'text/html',
			new class {
				public function __toString(): string
				{
					return '<h1>Chuck Stringable</h1>';
				}
			},
			404,
			'The Phrase',
		);

		$this->assertSame('<h1>Chuck Stringable</h1>', (string) $response->getBody());
		$this->assertSame('text/html', $response->getHeader('Content-Type')[0]);
	}

	public function testFailingWithContentTypeFromResource(): void
	{
		$this->throws(RuntimeException::class, 'No factory available');

		$resource = fopen('php://temp', 'r+');
		new Response($this->response())->withContentType('text/html', $resource, 404, 'The Phrase');
	}

	public function testWithContentTypeInvalidData(): void
	{
		$this->throws(RuntimeException::class, 'strings, Stringable or resources');

		Response::create($this->factory())->html(new stdClass());
	}

	public function testHtmlResponse(): void
	{
		$response = Response::create($this->factory());
		$response = $response->html('<h1>Chuck string</h1>');

		$this->assertSame('<h1>Chuck string</h1>', (string) $response->getBody());
		$this->assertSame('text/html', $response->getHeader('Content-Type')[0]);
	}

	public function testTextResponse(): void
	{
		$response = Response::create($this->factory())->text('text');

		$this->assertSame('text', (string) $response->getBody());
		$this->assertSame('text/plain', $response->getHeader('Content-Type')[0]);
	}

	public function testJsonResponse(): void
	{
		$response = Response::create($this->factory())->json([1, 2, 3]);

		$this->assertSame('[1,2,3]', (string) $response->getBody());
		$this->assertSame('application/json', $response->getHeader('Content-Type')[0]);
	}

	public function testJsonResponseTraversable(): void
	{
		$response = Response::create($this->factory())
			->json(
				(static function () {
					$arr = [13, 31, 73];

					foreach ($arr as $a) {
						yield $a;
					}
				})(),
			);

		$this->assertSame('[13,31,73]', (string) $response->getBody());
		$this->assertSame('application/json', $response->getHeader('Content-Type')[0]);
	}
}
