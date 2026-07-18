<?php

declare(strict_types=1);

namespace Celema\Core\Exception;

/** @api */
class HttpConflict extends HttpError
{
	protected const int code = 409;
	protected const string message = 'Conflict';
}
