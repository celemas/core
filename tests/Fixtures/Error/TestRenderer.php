<?php

declare(strict_types=1);

namespace Celema\Core\Tests\Fixtures\Error;

use Celema\Core\Error\Renderer;
use Override;
use Psr\Http\Message\ResponseFactoryInterface as ResponseFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;

final class TestRenderer implements Renderer
{
	#[Override]
	public function render(
		Throwable $exception,
		ResponseFactory $factory,
		Request $request,
		bool $debug,
	): Response {
		$response = $factory->createResponse();
		$method = $request->getMethod();
		$response
			->getBody()
			->write(
				$exception::class . ' rendered ' . $method . ' ' . $exception->getMessage(),
			);

		return $response;
	}
}
