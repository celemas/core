<?php

declare(strict_types=1);

namespace Celema\Core\Tests;

use Celema\Core\Response;

final class ResponseHeaderTest extends TestCase
{
	public function testInitWithHeader(): void
	{
		$response = new Response($this->response());
		$response->header('header-value', 'value');

		$this->assertSame(true, $response->hasHeader('Header-Value'));

		$headers = $response->headers();
		$this->assertSame('value', $headers['header-value'][0]);
	}

	public function testGetHeader(): void
	{
		$response = new Response($this->response(), $this->factory()->streamFactory());
		$response = $response->header('header-value', 'value');

		$this->assertSame('value', $response->getHeader('Header-Value')[0]);
	}

	public function testRemoveHeader(): void
	{
		$response = new Response($this->response(), $this->factory()->streamFactory());
		$response->header('header-value', 'value');

		$this->assertSame(true, $response->hasHeader('Header-Value'));

		$response = $response->removeHeader('header-value');

		$this->assertSame(false, $response->hasHeader('Header-Value'));
	}

	public function testRedirectTemporary(): void
	{
		$response = new Response($this->response(), $this->factory()->streamFactory());
		$response->redirect('/chuck');

		$this->assertSame(302, $response->getStatusCode());
		$this->assertSame('/chuck', $response->getHeader('Location')[0]);
	}

	public function testRedirectPermanent(): void
	{
		$response = new Response($this->response(), $this->factory()->streamFactory());
		$response->redirect('/chuck', 301);

		$this->assertSame(301, $response->getStatusCode());
		$this->assertSame('/chuck', $response->getHeader('Location')[0]);
	}
}
