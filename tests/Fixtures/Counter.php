<?php

declare(strict_types=1);

namespace Celema\Core\Tests\Fixtures;

use Celema\Container\Resettable;

final class Counter implements Resettable
{
	public int $count = 0;
	public int $resets = 0;

	public function reset(): void
	{
		$this->resets++;
	}
}
