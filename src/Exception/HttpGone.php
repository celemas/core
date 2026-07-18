<?php

declare(strict_types=1);

namespace Celema\Core\Exception;

/** @api */
class HttpGone extends HttpError
{
	protected const int code = 410;
	protected const string message = 'Gone';
}
