<?php

declare(strict_types=1);

namespace Celema\Core\Tests\Fixtures;

use Psr\Log\AbstractLogger;
use Stringable;

final class RecordingLogger extends AbstractLogger
{
	/** @var list<array{level: mixed, message: string, context: array}> */
	public array $records = [];

	public function log(mixed $level, string|Stringable $message, array $context = []): void
	{
		$this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
	}

	/** @return list<string> */
	public function messages(): array
	{
		return array_map(static fn(array $record): string => $record['message'], $this->records);
	}
}
