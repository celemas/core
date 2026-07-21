<?php

declare(strict_types=1);

namespace Celema\Core\Example;

use Celema\Core\Middleware;
use Celema\Core\Request;
use Celema\Core\Response;
use Override;

final class TimingMiddleware extends Middleware
{
	#[Override]
	public function handle(Request $request, callable $next): Response
	{
		$started = hrtime(true);
		$response = $next($request->set('example.started', $started));
		$duration = (hrtime(true) - $started) / 1_000_000;

		return $response
			->header('Server-Timing', sprintf('app;dur=%.2f', $duration))
			->header('X-Core-Example', 'middleware');
	}
}
