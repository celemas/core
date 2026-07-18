<?php

declare(strict_types=1);

namespace Celema\Core\Tests;

use Celema\Core\Exception\OutOfBoundsException;
use Celema\Core\Request;

final class RequestFormPostGetTest extends TestCase
{
	public function testParam(): void
	{
		$request = new Request($this->request(get: [
			'chuck' => 'schuldiner',
			'born' => '1967',
		]));

		$this->assertSame('schuldiner', $request->param('chuck'));
		$this->assertSame('1967', $request->param('born'));
	}

	public function testParamDefault(): void
	{
		$request = new Request($this->request());

		$this->assertSame('the default', $request->param('doesnotexist', 'the default'));
	}

	public function testParamFailing(): void
	{
		$this->throws(OutOfBoundsException::class, 'Query string');

		$request = new Request($this->request());

		$this->assertSame(null, $request->param('doesnotexist'));
	}

	public function testParams(): void
	{
		$request = new Request($this->request(
			get: ['chuck' => 'schuldiner', 'born' => '1967'],
		));
		$params = $request->params();

		$this->assertSame(2, count($params));
		$this->assertSame('1967', $params['born']);
		$this->assertSame('schuldiner', $params['chuck']);
	}

	public function testField(): void
	{
		$request = new Request($this->request(
			server: [
				'HTTP_CONTENT_TYPE' => 'application/x-www-form-urlencoded',
				'REQUEST_METHOD' => 'POST',
			],
			post: ['chuck' => 'schuldiner', 'born' => '1967'],
		));

		$this->assertSame('schuldiner', $request->field('chuck'));
		$this->assertSame('1967', $request->field('born'));
	}

	public function testFieldDefaultPostIsNull(): void
	{
		$request = new Request($this->request());

		$this->assertSame('the default', $request->field('doesnotexist', 'the default'));
	}

	public function testFieldDefaultPostIsArray(): void
	{
		$request = new Request($this->request(
			server: [
				'HTTP_CONTENT_TYPE' => 'application/x-www-form-urlencoded',
				'REQUEST_METHOD' => 'POST',
			],
			post: ['chuck' => 'schuldiner'],
		));

		$this->assertSame('the default', $request->field('doesnotexist', 'the default'));
	}

	public function testFieldFailing(): void
	{
		$this->throws(OutOfBoundsException::class, 'Form field');

		$request = new Request($this->request(server: [
			'HTTP_CONTENT_TYPE' => 'application/x-www-form-urlencoded',
			'REQUEST_METHOD' => 'POST',
		]));

		$request->field('doesnotexist');
	}

	public function testForm(): void
	{
		$request = new Request($this->request(
			server: [
				'HTTP_CONTENT_TYPE' => 'application/x-www-form-urlencoded',
				'REQUEST_METHOD' => 'POST',
			],
			post: [
				'first_band' => 'Mantas',
				'chuck' => 'schuldiner',
			],
		));

		$this->assertSame(['first_band' => 'Mantas', 'chuck' => 'schuldiner'], $request->form());
	}
}
