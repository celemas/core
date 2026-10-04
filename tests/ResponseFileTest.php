<?php

declare(strict_types=1);

namespace Celema\Core\Tests;

use Celema\Core\Exception\FileNotFoundException;
use Celema\Core\Response;

final class ResponseFileTest extends TestCase
{
	public const FIXTURES = __DIR__ . '/Fixtures';

	public function testFileResponse(): void
	{
		$file = self::FIXTURES . '/public/image.webp';
		$response = Response::create($this->factory())->file($file);

		$this->assertSame('image/webp', $response->getHeader('Content-Type')[0]);
		$this->assertSame((string) filesize($file), $response->getHeader('Content-Length')[0]);
	}

	public function testFileDownloadResponse(): void
	{
		$file = self::FIXTURES . '/public/image.webp';
		$response = Response::create($this->factory())->download($file);

		$this->assertSame('image/webp', $response->getHeader('Content-Type')[0]);
		$this->assertSame((string) filesize($file), $response->getHeader('Content-Length')[0]);
		$this->assertSame(
			'attachment; filename="image.webp"',
			$response->getHeader('Content-Disposition')[0],
		);
	}

	public function testFileDownloadResponseWithChangedName(): void
	{
		$file = self::FIXTURES . '/public/image.webp';
		$response = Response::create($this->factory())->download($file, 'newname.jpg');

		$this->assertSame('image/webp', $response->getHeader('Content-Type')[0]);
		$this->assertSame((string) filesize($file), $response->getHeader('Content-Length')[0]);
		$this->assertSame(
			'attachment; filename="newname.jpg"',
			$response->getHeader('Content-Disposition')[0],
		);
	}

	public function testSendfileResponse(): void
	{
		$_SERVER['SERVER_SOFTWARE'] = 'nginx';

		$file = self::FIXTURES . '/public/image.webp';
		$response = Response::create($this->factory())->sendfile($file);

		$this->assertSame($file, $response->getHeader('X-Accel-Redirect')[0]);

		$_SERVER['SERVER_SOFTWARE'] = 'apache';

		$response = Response::create($this->factory())->sendfile($file);

		$this->assertSame($file, $response->getHeader('X-Sendfile')[0]);

		unset($_SERVER['SERVER_SOFTWARE']);
	}

	public function testFileResponseNonexistentFileWithRuntimeError(): void
	{
		$file = self::FIXTURES . '/public/static/pixel.jpg';
		$this->throws(FileNotFoundException::class, 'File not found: ' . $file);

		Response::create($this->factory())->file($file);
	}

	public function testSendfileResponseNonexistentFile(): void
	{
		$file = self::FIXTURES . '/public/static/pixel.jpg';
		$this->throws(FileNotFoundException::class, 'File not found: ' . $file);

		Response::create($this->factory())->sendfile($file);
	}

	public function testSendfileDetectsNginxCaseInsensitively(): void
	{
		$_SERVER['SERVER_SOFTWARE'] = 'NGINX/1.27';

		try {
			$file = self::FIXTURES . '/public/image.webp';
			$response = Response::create($this->factory())->sendfile($file);
		} finally {
			unset($_SERVER['SERVER_SOFTWARE']);
		}

		$this->assertSame($file, $response->getHeader('X-Accel-Redirect')[0]);
	}

	public function testFileHelpersRespondWithOkByDefault(): void
	{
		$file = self::FIXTURES . '/public/image.webp';
		$_SERVER['SERVER_SOFTWARE'] = 'nginx';

		try {
			$sendfile = Response::create($this->factory())->sendfile($file);
		} finally {
			unset($_SERVER['SERVER_SOFTWARE']);
		}

		$this->assertSame(200, Response::create($this->factory())->file($file)->getStatusCode());
		$this->assertSame(200, Response::create($this->factory())->download($file)->getStatusCode());
		$this->assertSame(200, $sendfile->getStatusCode());
	}

	public function testFileContentTypeComesFromTheExtension(): void
	{
		$cases = [
			'scalar.json' => 'application/json',
			'plain.html' => 'text/html',
			'upper.CSS' => 'text/css',
		];

		foreach ($cases as $filename => $expectedContentType) {
			$file = self::FIXTURES . '/public/static/' . $filename;
			$response = Response::create($this->factory())->file($file);

			$this->assertSame($expectedContentType, $response->getHeader('Content-Type')[0], $filename);
		}
	}

	public function testFileResponseContentTypesForTextFiles(): void
	{
		$cases = [
			'test.js' => 'text/javascript',
			'test.css' => 'text/css',
			'test.json' => 'application/json',
			'test.html' => 'text/html',
			'test.md' => 'text/markdown',
			'test.markdown' => 'text/markdown',
			'test.csv' => 'text/csv',
			'test.xml' => 'application/xml',
			'test.xhtml' => 'application/xhtml+xml',
		];

		foreach ($cases as $filename => $expectedContentType) {
			$file = self::FIXTURES . '/public/static/' . $filename;
			$response = Response::create($this->factory())->file($file);

			$this->assertSame(
				$expectedContentType,
				$response->getHeader('Content-Type')[0],
				"Content-Type for {$filename} should be {$expectedContentType}",
			);
		}
	}
}
