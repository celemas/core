<?php

declare(strict_types=1);

namespace Celema\Core\Emitter;

use Psr\Http\Message\ResponseInterface as Response;

/** @api */
interface Emitter
{
	public function emit(Response $response, bool $withoutBody = false): bool;
}
