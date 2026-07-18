<?php

declare(strict_types=1);

namespace Celema\Core\Tests;

use Celema\Core\Exception\RuntimeException;
use Celema\Core\Response;

final class ResponseBodyTest extends TestCase
{
	public function testCreateWithStringBody(): void
	{
		$text = 'text';
		$response = new Response($this->response(), $this->factory()->streamFactory())->write($text);
		$this->assertSame($text, (string) $response->getBody());
	}

	public function testSetBodyWithStream(): void
	{
		$stream = $this->factory()->stream('Chuck text stream');
		$response = new Response($this->response());
		$response->body($stream);
		$this->assertSame('Chuck text stream', (string) $response->getBody());
	}

	public function testSetBodyWithString(): void
	{
		$response = new Response($this->response());
		$response->body('Chuck text string');
		$this->assertSame('Chuck text string', (string) $response->getBody());
	}

	public function testSetBodyWithStringUsingFactory(): void
	{
		$response = new Response($this->response(), $this->factory()->streamFactory());
		$response->body('Chuck text using factory');
		$this->assertSame('Chuck text using factory', (string) $response->getBody());
	}

	public function testFailSettingStringBodyWithoutFactory(): void
	{
		$this->throws(RuntimeException::class, 'not writable');

		$resource = fopen('php://temp', 'r');
		$stream = $this->factory()->streamFromResource($resource);
		$response = new Response($this->response());
		$response->body($stream);
		$response->body('try to overwrite');
	}
}
