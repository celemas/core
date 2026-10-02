<?php

declare(strict_types=1);

namespace Celema\Core\Tests;

use Celema\Core\Request;

final class RequestHeaderTest extends TestCase
{
	public function testHeader(): void
	{
		$request = new Request($this->request());

		$this->assertSame('deflate, gzip;q=1.0, *;q=0.5', $request->header('Accept-Encoding'));
		$this->assertSame('', $request->header('Does-Not-Exist'));
	}

	public function testHeaderArray(): void
	{
		$request = new Request($this->request());

		$this->assertSame(['deflate, gzip;q=1.0, *;q=0.5'], $request->headerArray('Accept-Encoding'));
		$this->assertSame([], $request->headerArray('Does-Not-Exist'));
	}

	public function testHeaders(): void
	{
		$request = new Request($this->request());

		$this->assertSame('www.example.com', $request->headers()['Host'][0]);
		$this->assertSame('deflate, gzip;q=1.0, *;q=0.5', $request->headers()['Accept-Encoding'][0]);
	}

	public function testHasHeader(): void
	{
		$request = new Request($this->request());

		$this->assertSame(true, $request->hasHeader('Host'));
		$this->assertSame(false, $request->hasHeader('Does-Not-Exist'));
	}

	public function testHeadersFirstEntryOnly(): void
	{
		$request = new Request($this->request());

		$this->assertSame('www.example.com', $request->headers(firstOnly: true)['Host']);
		$this->assertSame(
			'deflate, gzip;q=1.0, *;q=0.5',
			$request->headers(firstOnly: true)['Accept-Encoding'],
		);
	}

	public function testWritingHeaders(): void
	{
		$request = new Request($this->request());
		$changed = $request
			->withHeader('test-header', 'test-value')
			->withHeader('test-header', 'test-value-replaced')
			->withAddedHeader('test-header', 'test-value-added');

		$this->assertSame('test-value-replaced, test-value-added', $changed->header('test-header'));
		$this->assertSame(
			['test-value-replaced', 'test-value-added'],
			$changed->headerArray('test-header'),
		);
		$this->assertSame('', $changed->withoutHeader('test-header')->header('test-header'));
		$this->assertSame('test-value-replaced, test-value-added', $changed->header('test-header'));
		$this->assertSame('', $request->header('test-header'));
	}
}
