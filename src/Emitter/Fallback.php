<?php

declare(strict_types=1);

namespace Celema\Core\Emitter;

use Psr\Http\Message\ResponseInterface as Response;
use Throwable;

/**
 * Last resort for a request that failed outside the error handler.
 *
 * @internal
 */
final class Fallback
{
	/**
	 * Emits the response if nothing was sent yet. Output buffers the request
	 * opened are discarded first. If the emitter still fails, for example
	 * because the request wrote output into a buffer it did not open or one
	 * that cannot be cleaned, the status is set to 500 when possible and
	 * that output becomes the body.
	 */
	public static function emit(
		Emitter $emitter,
		Response $response,
		int $bufferLevel,
		bool $withoutBody = false,
	): void {
		self::discardBuffers($bufferLevel);

		try {
			$emitter->emit($response, $withoutBody);
		} catch (Throwable) {
			if (!headers_sent()) {
				http_response_code($response->getStatusCode());
			}
		}
	}

	/**
	 * A buffer opened without the removable flag cannot be ended:
	 * ob_end_clean() fails and leaves it in place. The cleanup stops there,
	 * after discarding its output if allowed, and the response goes into
	 * that buffer, which PHP sends when the request ends.
	 */
	private static function discardBuffers(int $bufferLevel): void
	{
		while (ob_get_level() > $bufferLevel) {
			$flags = (int) (ob_get_status()['flags'] ?? 0);

			if (($flags & PHP_OUTPUT_HANDLER_REMOVABLE) === 0) {
				if (($flags & PHP_OUTPUT_HANDLER_CLEANABLE) !== 0) {
					ob_clean();
				}

				return;
			}

			ob_end_clean();
		}
	}
}
