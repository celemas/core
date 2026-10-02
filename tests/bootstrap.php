<?php

declare(strict_types=1);

/*
 * Overrides for the global functions called unqualified in
 * Celema\Core\Emitter. They are defined before the first emitter call so
 * every call site binds to them for the whole test run.
 */

namespace Celema\Core\Emitter {
	use Celema\Core\Tests\Fixtures\SapiState;

	function header(string $header, bool $replace = true, int $response_code = 0): void
	{
		SapiState::$headers[] = [$header, $replace, $response_code];
	}

	// @mago-expect lint:function-name
	function headers_sent(?string &$filename = null, ?int &$line = null): bool
	{
		if (SapiState::$headersSent) {
			$filename = 'output.php';
			$line = 17;

			return true;
		}

		return false;
	}

	// @mago-expect lint:function-name
	function http_response_code(int $response_code = 0): int|bool
	{
		SapiState::$statusCodes[] = $response_code;

		return true;
	}
}

namespace {
	require __DIR__ . '/../vendor/autoload.php';

	/**
	 * Stands in for FrankenPHP's worker function, which only exists in a
	 * FrankenPHP worker. Tests queue the requests it serves.
	 */
	// @mago-expect lint:function-name
	function frankenphp_handle_request(callable $callback): bool
	{
		return Celema\Core\Tests\Fixtures\WorkerState::next($callback);
	}
}
