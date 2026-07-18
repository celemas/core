<?php

declare(strict_types=1);

namespace Celema\Core\Factory;

use Celema\Core\Exception\RuntimeException;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\ServerRequest;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/** @api */
class Guzzle extends AbstractFactory
{
	public function __construct()
	{
		try {
			$factory = new HttpFactory();
			$this->responseFactory = $factory;
			$this->streamFactory = $factory;
			$this->requestFactory = $factory;
			$this->serverRequestFactory = $factory;
			$this->uploadedFileFactory = $factory;
			$this->uriFactory = $factory;

			// @codeCoverageIgnoreStart
		} catch (Throwable) {
			throw new RuntimeException('Install guzzlehttp/psr7');

			// @codeCoverageIgnoreEnd
		}
	}

	#[Override]
	public function serverRequest(): ServerRequestInterface
	{
		return ServerRequest::fromGlobals();
	}
}
