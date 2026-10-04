<?php

declare(strict_types=1);

namespace Celema\Core\Runtime;

use Celema\Core\App;
use Celema\Core\Exception\RuntimeException;
use Closure;

/**
 * Handles requests in a FrankenPHP worker until FrankenPHP stops it or it
 * retires: after a request that left the app unusable, after a configured
 * number of requests, or above a memory threshold. FrankenPHP then starts
 * a fresh worker.
 *
 * @internal
 */
final class FrankenPhpWorker
{
	public const string MAX_REQUESTS = 'CELEMA_WORKER_MAX_REQUESTS';
	public const string MAX_MEMORY = 'CELEMA_WORKER_MAX_MEMORY';

	/**
	 * @param Closure(callable(): void): bool $handleRequest Waits for a request and runs the
	 *     callback for it; returns false when the worker should stop (frankenphp_handle_request).
	 */
	public function __construct(
		private readonly Closure $handleRequest,
		public readonly int $maxRequests,
		public readonly int $maxMemory,
	) {}

	/**
	 * Explicit limits win over the worker's environment (for example the
	 * `env` directive of a Caddyfile worker block). Without either, the
	 * request limit is off and the memory limit is 80 % of memory_limit.
	 *
	 * @param Closure(callable(): void): bool $handleRequest
	 */
	public static function configure(
		Closure $handleRequest,
		?int $maxRequests = null,
		?int $maxMemory = null,
	): self {
		return new self(
			$handleRequest,
			$maxRequests ?? self::requestsSetting() ?? 0,
			$maxMemory ?? self::memorySetting() ?? self::defaultMaxMemory(),
		);
	}

	public function serve(App $app): false
	{
		// A client that disconnects must not abort a request halfway, as
		// its teardown has to run before the worker takes the next one.
		ignore_user_abort(true);
		$handled = 0;

		do {
			$running = ($this->handleRequest)(static function () use ($app): void {
				// PHP's stat cache would keep file sizes and modification
				// times from earlier requests.
				clearstatcache();
				$app->run();
			});
			$handled++;
			gc_collect_cycles();
		} while ($running && !$this->retires($app, $handled));

		return false;
	}

	private function retires(App $app, int $handled): bool
	{
		return (
			!$app->reusable()
				|| $this->maxRequests > 0
				&& $handled >= $this->maxRequests
				|| $this->maxMemory > 0
				&& memory_get_usage(true) >= $this->maxMemory
		);
	}

	private static function requestsSetting(): ?int
	{
		$value = self::setting(self::MAX_REQUESTS);

		if ($value === null) {
			return null;
		}

		if (preg_match('/^\d+$/', $value) !== 1) {
			throw new RuntimeException(self::MAX_REQUESTS . ' must be a number of requests, 0 for no limit');
		}

		return (int) $value;
	}

	private static function memorySetting(): ?int
	{
		$value = self::setting(self::MAX_MEMORY);

		if ($value === null) {
			return null;
		}

		if (preg_match('/^\d+[KMG]?$/i', $value) !== 1) {
			throw new RuntimeException(
				self::MAX_MEMORY . ' must be a number of bytes, optionally with K, M or G, 0 for no limit',
			);
		}

		return ini_parse_quantity($value);
	}

	/** @return ?non-empty-string */
	private static function setting(string $name): ?string
	{
		$value = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);
		$value = is_string($value) ? trim($value) : '';

		return $value === '' ? null : $value;
	}

	private static function defaultMaxMemory(): int
	{
		$limit = (string) ini_get('memory_limit');
		$bytes = $limit === '' ? 0 : ini_parse_quantity($limit);

		return $bytes > 0 ? intdiv($bytes * 4, 5) : 0;
	}
}
