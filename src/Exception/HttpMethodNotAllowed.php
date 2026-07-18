<?php

declare(strict_types=1);

namespace Celema\Core\Exception;

/** @api */
class HttpMethodNotAllowed extends HttpError
{
	protected const int code = 405;
	protected const string message = 'Method Not Allowed';
}
