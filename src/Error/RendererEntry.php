<?php

declare(strict_types=1);

namespace Celema\Core\Error;

use Throwable;

final class RendererEntry
{
	private string|int|null $logLevel = null;

	/**
	 * @param list<class-string<Throwable>> $exceptions
	 */
	public function __construct(
		public readonly array $exceptions,
		public readonly Renderer $renderer,
	) {}

	public function matches(Throwable $exception): bool
	{
		foreach ($this->exceptions as $class) {
			if ($exception::class === $class || is_subclass_of($exception::class, $class)) {
				return true;
			}
		}

		return false;
	}

	/** @api */
	public function log(string|int $logLevel): void
	{
		$this->logLevel = $logLevel;
	}

	public function logLevel(): string|int|null
	{
		return $this->logLevel;
	}
}
