<?php

declare(strict_types=1);

namespace Celema\Core\Factory;

use Celema\Core\Exception\RuntimeException;
use Laminas\Diactoros\RequestFactory;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\Diactoros\StreamFactory;
use Laminas\Diactoros\UploadedFileFactory;
use Laminas\Diactoros\UriFactory;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/** @api */
class Laminas extends AbstractFactory
{
	public function __construct()
	{
		try {
			$this->requestFactory = new RequestFactory();
			$this->responseFactory = new ResponseFactory();
			$this->serverRequestFactory = new ServerRequestFactory();
			$this->streamFactory = new StreamFactory();
			$this->uploadedFileFactory = new UploadedFileFactory();
			$this->uriFactory = new UriFactory();

			// @codeCoverageIgnoreStart
		} catch (Throwable) {
			throw new RuntimeException('Install nyholm/psr7-server');

			// @codeCoverageIgnoreEnd
		}
	}

	#[Override]
	public function serverRequest(): ServerRequestInterface
	{
		return ServerRequestFactory::fromGlobals();
	}
}
