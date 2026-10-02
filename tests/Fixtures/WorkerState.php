<?php

declare(strict_types=1);

namespace Celema\Core\Tests\Fixtures;

/**
 * Drives the frankenphp_handle_request() stub: each queued request runs the
 * worker's callback once; the stub returns false when the queue is empty or
 * when a request was queued as the last one before FrankenPHP stops.
 */
final class WorkerState
{
	/** @var list<array{server: array<string, string>, stop: bool}> */
	public static array $queue = [];

	public static int $calls = 0;

	/** @param array<string, string> $server */
	public static function request(array $server = [], bool $stop = false): void
	{
		self::$queue[] = ['server' => $server, 'stop' => $stop];
	}

	public static function next(callable $callback): bool
	{
		self::$calls++;
		$request = array_shift(self::$queue);

		if ($request === null) {
			return false;
		}

		$previous = $_SERVER;
		$_SERVER = array_merge($_SERVER, $request['server']);

		try {
			$callback();
		} finally {
			$_SERVER = $previous;
		}

		return !$request['stop'] && self::$queue !== [];
	}

	public static function reset(): void
	{
		self::$queue = [];
		self::$calls = 0;
	}
}
