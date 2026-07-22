<?php

declare(strict_types=1);

namespace Celema\Core\Tests\Fixtures;

/**
 * Records the header() calls made by the SAPI emitter through the
 * function overrides in tests/bootstrap.php.
 */
final class SapiState
{
	public static bool $headersSent = false;

	/** @var list<array{0: string, 1: bool, 2: int}> */
	public static array $headers = [];

	public static function reset(): void
	{
		self::$headersSent = false;
		self::$headers = [];
	}

	/** @return list<string> */
	public static function headerLines(): array
	{
		return array_map(static fn(array $header): string => $header[0], self::$headers);
	}
}
