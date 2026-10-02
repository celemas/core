<?php

declare(strict_types=1);

namespace Celema\Core\Tests\Fixtures;

/**
 * Records the header() calls made by the SAPI emitter and controls the
 * other function overrides in tests/bootstrap.php.
 */
final class SapiState
{
	public static bool $headersSent = false;

	/** @var list<array{0: string, 1: bool, 2: int}> */
	public static array $headers = [];

	/** @var list<int> */
	public static array $statusCodes = [];

	/** Flags ob_get_status() reports for the innermost buffer instead of its own. */
	public static ?int $bufferFlags = null;

	public static function reset(): void
	{
		self::$headersSent = false;
		self::$headers = [];
		self::$statusCodes = [];
		self::$bufferFlags = null;
	}

	/** @return list<string> */
	public static function headerLines(): array
	{
		return array_map(static fn(array $header): string => $header[0], self::$headers);
	}
}
