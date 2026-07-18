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
		$request->set('two', '2');

		$this->assertSame(2, count($request->attributes()));
		$this->assertSame(1, $request->get('one'));
		$this->assertSame('2', $request->get('two'));
	}
}
