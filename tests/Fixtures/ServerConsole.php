<?php

declare(strict_types=1);

namespace Celema\Server;

use Throwable;

/**
 * Stand-in for the optional celema/server package's Console, which the
 * error handler integrates with through a class_exists() check. Mirrors
 * the recording behavior the error handler tests assert against.
 */
final class Console
{
	private static ?Throwable $exception = null;

	public static function enabled(): bool
	{
		$value = $_SERVER['CELEMA_CLI_SERVER'] ?? getenv('CELEMA_CLI_SERVER');

		return $value === '1' || $value === 'frankenphp';
	}

	public static function recordException(Throwable $exception, bool $trace): void
	{
		if (!self::enabled()) {
			return;
		}

		self::$exception = $exception;
	}

	public static function hasException(): bool
	{
		return self::$exception !== null;
	}

	public static function clearException(): void
	{
		self::$exception = null;
	}
}
