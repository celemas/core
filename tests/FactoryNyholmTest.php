<?php

declare(strict_types=1);

namespace Celema\Core\Tests;

use Celema\Core\Exception\RuntimeException;
use Celema\Core\Exception\ValueError;
use Celema\Core\Factory\Nyholm;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;

final class FactoryNyholmTest extends TestCase
{
	public function testNyholm(): void
	{
		$factory = new Nyholm();

		$serverRequest = $factory->serverRequest();
		$this->assertInstanceOf(\Nyholm\Psr7\ServerRequest::class, $serverRequest);

		$request = $factory->request('GET', 'http://example.com');
		$this->assertInstanceOf(\Nyholm\Psr7\Request::class, $request);

		$response = $factory->response();
		$this->assertInstanceOf(\Nyholm\Psr7\Response::class, $response);

		$response = $factory->response(404, 'changed phrase');
		$this->assertEquals('changed phrase', $response->getReasonPhrase());
		$this->assertEquals(404, $response->getStatusCode());

		$stream = $factory->stream();
		$this->assertInstanceOf(\Nyholm\Psr7\Stream::class, $stream);

		$stream = $factory->streamFromResource(fopen('php://temp', 'r+'));
		$this->assertInstanceOf(\Nyholm\Psr7\Stream::class, $stream);

		$stream = $factory->streamFromFile(__DIR__ . '/Fixtures/public/image.webp');
		$this->assertInstanceOf(\Nyholm\Psr7\Stream::class, $stream);

		$uri = $factory->uri('http://example.com');
		$this->assertInstanceOf(\Nyholm\Psr7\Uri::class, $uri);

		$uploadedFile = $factory->uploadedFile($stream);
		$this->assertInstanceOf(\Nyholm\Psr7\UploadedFile::class, $uploadedFile);

		$this->assertInstanceOf(RequestFactoryInterface::class, $factory->requestFactory());
		$this->assertInstanceOf(ServerRequestFactoryInterface::class, $factory->serverRequestFactory());
		$this->assertInstanceOf(ResponseFactoryInterface::class, $factory->responseFactory());
		$this->assertInstanceOf(StreamFactoryInterface::class, $factory->streamFactory());
		$this->assertInstanceOf(UploadedFileFactoryInterface::class, $factory->uploadedFileFactory());
		$this->assertInstanceOf(UriFactoryInterface::class, $factory->uriFactory());
	}

	public function testNyholmFailingResource(): void
	{
		$this->throws(ValueError::class);

		$factory = new Nyholm();
		$factory->streamFromResource('wrong');
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function testNyholmFailsWithoutInstalledPackages(): void
	{
		$autoloaders = spl_autoload_functions() ?: [];

		foreach ($autoloaders as $autoload) {
			spl_autoload_unregister($autoload);
		}

		$exceptionClass = null;
		$message = null;

		try {
			$root = dirname(__DIR__);

			require_once $root . '/src/Exception/CoreException.php';
			require_once $root . '/src/Exception/RuntimeException.php';
			require_once $root . '/src/Factory/Factory.php';
			require_once $root . '/src/Factory/AbstractFactory.php';
			require_once $root . '/src/Factory/Nyholm.php';

			try {
				new Nyholm();
			} catch (RuntimeException $exception) {
				$exceptionClass = $exception::class;
				$message = $exception->getMessage();
			}
		} finally {
			foreach ($autoloaders as $autoload) {
				spl_autoload_register($autoload);
			}
		}

		$this->assertSame(RuntimeException::class, $exceptionClass);
		$this->assertStringContainsString('nyholm/psr7 and nyholm/psr7-server', (string) $message);
		$this->assertStringContainsString('custom Factory implementation', (string) $message);
	}
}
