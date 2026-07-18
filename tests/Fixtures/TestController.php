<?php

declare(strict_types=1);

namespace Celema\Core\Tests\Fixtures;

use Celema\Core\Factory\Nyholm;
use Celema\Core\Response;

class TestController
{
	public function textView(): Response
	{
		return Response::create(new Nyholm())->body('text');
	}
}
