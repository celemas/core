<?php

declare(strict_types=1);

namespace Celema\Core\Error;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface as Logger;
use Throwable;

/**
 * Writes the records of the app and its error handler, each about an
 * exception. Without a logger, or when the logger fails, a record goes to
 * PHP's error log, with the exception's trace and previous exceptions: the
 * failures they report were caught, so PHP would never report them, and a
 * logger that cannot write must not keep a request from being answered.
 *
 * @internal
 */
final class Log
{
	/** @param array<string, string|int> $values Placeholder values */
	public static function write(
		?Logger $logger,
		string|int $level,
		string $message,
		Throwable $exception,
		array $values = [],
	): void {
		if ($logger !== null && self::attempt($logger, $level, $message, $exception, $values)) {
			return;
		}

		error_log(strtr($message, self::replacements($values)) . ': ' . (string) $exception);
	}

	/**
	 * Logs the record and tells whether that worked. A failing logger is
	 * reported to PHP's error log.
	 *
	 * @param array<string, string|int> $values Placeholder values
	 */
	public static function attempt(
		Logger $logger,
		string|int $level,
		string $message,
		Throwable $exception,
		array $values = [],
	): bool {
		try {
			$logger->log($level, $message, ['exception' => $exception, ...$values]);

			return true;
		} catch (Throwable $e) {
			error_log('Logging failed: ' . (string) $e);

			return false;
		}
	}

	/**
	 * The request's method and path, for the placeholders of a record. The
	 * path only: query strings can carry tokens.
	 *
	 * @return array{method: string, path: string}
	 */
	public static function request(Request $request): array
	{
		return ['method' => $request->getMethod(), 'path' => $request->getUri()->getPath()];
	}

	/**
	 * @param array<string, string|int> $values
	 * @return array<string, string>
	 */
	private static function replacements(array $values): array
	{
		$replacements = [];

		foreach ($values as $key => $value) {
			$replacements['{' . $key . '}'] = (string) $value;
		}

		return $replacements;
	}
}
