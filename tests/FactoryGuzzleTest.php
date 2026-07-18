<?php

declare(strict_types=1);

namespace Celema\Core\Tests;

use Celema\Core\Exception\ValueError;
use Celema\Core\Factory\Guzzle;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;

final class FactoryGuzzleTest extends TestCase
{
	public function testGuzzle(): void
	{
		$factory = new Guzzle();

		$serverRequest = $factory->serverRequest();
		$this->assertInstanceOf(\GuzzleHttp\Psr7\ServerRequest::class, $serverRequest);

		$request = $factory->request('GET', 'http://example.com');
		$this->assertInstanceOf(\GuzzleHttp\Psr7\Request::class, $request);

		$response = $factory->response();
		$this->assertInstanceOf(\GuzzleHttp\Psr7\Response::class, $response);

		$response = $factory->response(404, 'changed phrase');
		$this->assertEquals('changed phrase', $response->getReasonPhrase());
		$this->assertEquals(404, $response->getStatusCode());

		$stream = $factory->stream();
		$this->assertInstanceOf(\GuzzleHttp\Psr7\Stream::class, $stream);

		$stream = $factory->streamFromResource(fopen('php://temp', 'r+'));
		$this->assertInstanceOf(\GuzzleHttp\Psr7\Stream::class, $stream);

		$stream = $factory->streamFromFile(__DIR__ . '/Fixtures/public/image.webp');
		$this->assertInstanceOf(\GuzzleHttp\Psr7\Stream::class, $stream);

		$uri = $factory->uri('http://example.com');
		$this->assertInstanceOf(\GuzzleHttp\Psr7\Uri::class, $uri);

		$uploadedFile = $factory->uploadedFile($stream);
		$this->assertInstanceOf(\GuzzleHttp\Psr7\UploadedFile::class, $uploadedFile);

		$this->assertInstanceOf(RequestFactoryInterface::class, $factory->requestFactory());
		$this->assertInstanceOf(ServerRequestFactoryInterface::class, $factory->serverRequestFactory());
		$this->assertInstanceOf(ResponseFactoryInterface::class, $factory->responseFactory());
		$this->assertInstanceOf(StreamFactoryInterface::class, $factory->streamFactory());
		$this->assertInstanceOf(UploadedFileFactoryInterface::class, $factory->uploadedFileFactory());
		$this->assertInstanceOf(UriFactoryInterface::class, $factory->uriFactory());
	}

	public function testGuzzleFailingResource(): void
	{
		$this->throws(ValueError::class);

		$factory = new Guzzle();
		$factory->streamFromResource('wrong');
	}
}
