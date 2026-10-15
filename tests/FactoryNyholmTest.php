<?php

declare(strict_types=1);

namespace Celema\Core\Tests;

use Celema\Core\Exception\RuntimeException;
use Celema\Core\Exception\ValueError;
use Celema\Core\Factory\Nyholm;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use PHPUnit\Framework\Attributes\DataProvider;
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

	/** @return iterable<string, array{class-string}> */
	public static function missingPackageProvider(): iterable
	{
		yield 'nyholm/psr7' => [Psr17Factory::class];
		yield 'nyholm/psr7-server' => [ServerRequestCreator::class];
	}

	/** @param class-string $missing */
	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	#[DataProvider('missingPackageProvider')]
	public function testNyholmFailsWithoutInstalledPackages(string $missing): void
	{
		$autoloaders = spl_autoload_functions();

		foreach ($autoloaders as $autoload) {
			spl_autoload_unregister($autoload);
		}

		$hide = static function (string $class) use ($autoloaders, $missing): void {
			if ($class === $missing) {
				return;
			}

			foreach ($autoloaders as $autoload) {
				$autoload($class);
			}
		};
		spl_autoload_register($hide);

		$message = null;

		try {
			new Nyholm();
		} catch (RuntimeException $exception) {
			$message = $exception->getMessage();
		} finally {
			spl_autoload_unregister($hide);

			foreach ($autoloaders as $autoload) {
				spl_autoload_register($autoload);
			}
		}

		$this->assertSame(
			'Install nyholm/psr7 and nyholm/psr7-server to use the default PSR-7 factory, '
				. 'or pass a custom Factory implementation to the App constructor',
			$message,
		);
	}
}
