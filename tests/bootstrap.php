<?php

declare(strict_types=1);

/*
 * Overrides for the global functions called unqualified in
 * Celema\Core\Emitter\Sapi. They are defined before the first emitter
 * call so every call site binds to them for the whole test run.
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
}

namespace {
	require __DIR__ . '/../vendor/autoload.php';
}
