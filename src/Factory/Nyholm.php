<?php

declare(strict_types=1);

namespace Celema\Core\Factory;

use Celema\Core\Exception\RuntimeException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/** @api */
class Nyholm extends AbstractFactory
{
	protected Psr17Factory $factory;

	public function __construct()
	{
		try {
			$factory = new Psr17Factory();
			$this->factory = $factory;
			$this->responseFactory = $factory;
			$this->streamFactory = $factory;
			$this->requestFactory = $factory;
			$this->serverRequestFactory = $factory;
			$this->uploadedFileFactory = $factory;
			$this->uriFactory = $factory;

			// @codeCoverageIgnoreStart
		} catch (Throwable) {
			throw new RuntimeException('Install laminas/laminas-diactoros');

			// @codeCoverageIgnoreEnd
		}
	}

	#[Override]
	public function serverRequest(): ServerRequestInterface
	{
		$creator = new ServerRequestCreator(
			$this->factory, // ServerRequestFactory
			$this->factory, // UriFactory
			$this->factory, // UploadedFileFactory
			$this->factory, // StreamFactory
		);

		return $creator->fromGlobals();
	}
}
