<?php

declare(strict_types=1);

namespace Celema\Core\Tests\Fixtures;

use PHPUnit\Framework\Assert;
use Psr\Log\AbstractLogger;
use Stringable;
use Throwable;

final class RecordingLogger extends AbstractLogger
{
	/** @var list<array{level: mixed, message: string, context: array}> */
	public array $records = [];

	public function log(mixed $level, string|Stringable $message, array $context = []): void
	{
		$this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
	}

	public function exception(int $record): Throwable
	{
		$exception = $this->records[$record]['context']['exception'] ?? null;
		Assert::assertInstanceOf(Throwable::class, $exception);

		return $exception;
	}

	/** @return list<string> */
	public function messages(): array
	{
		return array_map(static fn(array $record): string => $record['message'], $this->records);
	}
}
