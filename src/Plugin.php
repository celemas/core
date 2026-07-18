<?php

declare(strict_types=1);

namespace Celema\Core;

interface Plugin
{
	public function load(App $app): void;
}
