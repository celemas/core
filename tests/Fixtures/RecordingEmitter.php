<?php

declare(strict_types=1);

namespace Celema\Core\Tests\Fixtures;

use Celema\Core\Emitter\Emitter;
use Closure;
use Psr\Http\Message\ResponseInterface as Response;
use RuntimeException;

final class RecordingEmitter implements Emitter
{
	/** @var list<Response> */
	public array $responses = [];

	/** @param int $failures Number of emit() calls that throw before emitting works. */
	public function __construct(
		private int $failures = 0,
		private readonly ?Closure $onEmit = null,
	) {}

	public function emit(Response $response, bool $withoutBody = false): bool
	{
		if ($this->failures > 0) {
			$this->failures--;

			throw new RuntimeException('Output already present in the output buffer');
		}

		$this->responses[] = $response;

		if ($this->onEmit !== null) {
			($this->onEmit)($response);
		}

		return true;
	}

	/** @return list<string> */
	public function bodies(): array
	{
		return array_map(static fn(Response $response): string => (string) $response->getBody(), $this->responses);
	}
}
