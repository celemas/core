<?php

declare(strict_types=1);

namespace Celema\Core\Error;

use Psr\Http\Message\ResponseFactoryInterface as ResponseFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Throwable;

interface DebugHandler
{
	public function handle(Throwable $exception, ResponseFactory $factory): Response;
}
