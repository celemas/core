<?php

declare(strict_types=1);

namespace Celema\Core\Tests;

use Celema\Core\Emitter;
use Celema\Core\Response;

final class EmitterTest extends TestCase
{
	public function testSapiEmitter(): void
	{
		$response = Response::create($this->factory())->json([1, 2, 3])->unwrap();

		$emitter = new Emitter();
		ob_start();
		$emitter->emit($response);
		$output = ob_get_contents();
		ob_end_clean();

		$this->assertSame('[1,2,3]', $output);
	}

	public function testSapiStreamEmitter(): void
	{
		$file = "{$this->root}/public/static/image.gif";
		$response = Response::create($this->factory())->download($file)->unwrap();

		$emitter = new Emitter();
		ob_start();
		$emitter->emit($response);
		$output = ob_get_contents();
		ob_end_clean();

		$this->assertSame(true, str_starts_with($output, 'GIF87a'));
	}
}
