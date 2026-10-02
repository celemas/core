<?php

declare(strict_types=1);

namespace Celema\Core\Tests;

use Celema\Core\Exception\OutOfBoundsException;
use Celema\Core\Request;

final class RequestAttributesTest extends TestCase
{
	public function testGetDefault(): void
	{
		$request = new Request($this->request());

		$this->assertSame('the default', $request->get('doesnotexist', 'the default'));
	}

	public function testGetFailing(): void
	{
		$this->throws(OutOfBoundsException::class, 'Request attribute');

		$request = new Request($this->request());

		$this->assertSame(null, $request->get('doesnotexist'));
	}

	public function testAttributes(): void
	{
		$request = new Request($this->request()->withAttribute('one', 1));
		$changed = $request->with('two', '2');

		$this->assertSame(2, count($changed->attributes()));
		$this->assertSame(1, $changed->get('one'));
		$this->assertSame('2', $changed->get('two'));
		$this->assertSame(1, count($request->attributes()));
	}
}
