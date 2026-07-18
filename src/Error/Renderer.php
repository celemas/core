<?php

declare(strict_types=1);

namespace Celema\Core\Error;

use Psr\Http\Message\ResponseFactoryInterface as ResponseFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;

interface Renderer
{
	public function render(
		Throwable $exception,
		ResponseFactory $factory,
		Request $request,
		bool $debug,
	): Response;
}
