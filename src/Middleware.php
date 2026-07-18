<?php

declare(strict_types=1);

namespace Celema\Core;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** @api */
abstract class Middleware implements MiddlewareInterface
{
	#[Override]
	public function process(
		ServerRequestInterface $request,
		RequestHandlerInterface $handler,
	): ResponseInterface {
		return $this->handle(
			new Request($request),
			static fn(Request $request): Response => new Response($handler->handle($request->unwrap())),
		)->unwrap();
	}

	/**
	 * @param callable(Request): Response $next
	 */
	abstract public function handle(Request $request, callable $next): Response;
}
