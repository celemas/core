<?php

declare(strict_types=1);

namespace Celema\Core\Tests\Fixtures;

use Psr\Log\AbstractLogger;
use RuntimeException;
use Stringable;

/** A logger whose backend fails, like one writing to an unwritable file. */
final class FailingLogger extends AbstractLogger
{
	public function log(mixed $level, string|Stringable $message, array $context = []): void
	{
		throw new RuntimeException('log not writable');
	}
}
