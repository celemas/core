<?php

declare(strict_types=1);

namespace Celema\Core\Tests\Fixtures\Error;

use Celema\Core\Error\DebugHandler;
use Override;
use Psr\Http\Message\ResponseFactoryInterface as ResponseFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Throwable;

final class TestDebugHandler implements DebugHandler
{
	#[Override]
	public function handle(Throwable $exception, ResponseFactory $factory): Response
	{
		$response = $factory->createResponse();
		$response->getBody()->write($exception::class . ' ' . $exception->getMessage());

		return $response;
	}
}
