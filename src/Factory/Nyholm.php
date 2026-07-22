<?php

declare(strict_types=1);

namespace Celema\Core\Factory;

use Celema\Core\Exception\RuntimeException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/** @api */
class Nyholm extends AbstractFactory
{
	protected Psr17Factory $factory;

	public function __construct()
	{
		if (!class_exists(Psr17Factory::class) || !class_exists(ServerRequestCreator::class)) {
			throw new RuntimeException(
				'Install nyholm/psr7 and nyholm/psr7-server to use the default '
				. 'PSR-7 factory, or pass a custom Factory implementation to the App constructor',
			);
		}

		$factory = new Psr17Factory();
		$this->factory = $factory;
		$this->responseFactory = $factory;
		$this->streamFactory = $factory;
		$this->requestFactory = $factory;
		$this->serverRequestFactory = $factory;
		$this->uploadedFileFactory = $factory;
		$this->uriFactory = $factory;
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
