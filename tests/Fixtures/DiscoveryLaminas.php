<?php

declare(strict_types=1);

namespace Celema\Core\Tests\Fixtures;

use Celema\Core\Factory\AbstractFactory;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface as ServerRequest;

final class DiscoveryLaminas extends AbstractFactory
{
	#[Override]
	public function serverRequest(): ServerRequest
	{
		throw new LogicException();
	}
}
