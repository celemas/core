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
	 * because the request wrote output into a buffer it did not open, the
	 * status is set to 500 when possible and that output becomes the body.
	 */
	public static function emit(Emitter $emitter, Response $response, int $bufferLevel): void
	{
		while (ob_get_level() > $bufferLevel) {
			ob_end_clean();
		}

		try {
			$emitter->emit($response);
		} catch (Throwable) {
			if (!headers_sent()) {
				http_response_code($response->getStatusCode());
			}
		}
	}
}
