<?php

declare(strict_types=1);

namespace Celema\Core\Exception;

/** @api */
class HttpForbidden extends HttpError
{
	protected const int code = 403;
	protected const string message = 'Forbidden';
}
