<?php

declare(strict_types=1);

namespace Celema\Core\Tests;

use Celema\Core\App;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ExampleAppTest extends TestCase
{
	public function testWorkbenchRendersWithMiddlewareHeaders(): void
	{
		[$response, $output] = $this->runExample($this->request());

		$this->assertStringContainsString('Runtime Workbench', $output);
		$this->assertStringContainsString('/assets/app.css?v=', $output);
		$this->assertSame('middleware', $response->getHeaderLine('X-Core-Example'));
		$this->assertNotSame('', $response->getHeaderLine('Server-Timing'));
	}

	public function testRequestProbeReportsRequestAndMiddleware(): void
	{
		$request = $this->request(
			server: ['REQUEST_URI' => '/api/request?source=test'],
			headers: ['X-Requested-With' => 'XMLHttpRequest'],
			get: ['source' => 'test'],
		);
		[$response, $output] = $this->runExample($request);
		$data = json_decode($output, true, flags: JSON_THROW_ON_ERROR);

		$this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
		$this->assertSame('GET', $data['method']);
		$this->assertSame(['source' => 'test'], $data['query']);
		$this->assertTrue($data['xhr']);
		$this->assertTrue($data['middleware']);
	}

	/** @return array{ResponseInterface, string} */
	private function runExample(ServerRequestInterface $request): array
	{
		/** @var App $app */
		$app = require __DIR__ . '/../app/bootstrap.php';
		ob_start();

		try {
			$response = $app->run($request);
			$output = (string) ob_get_contents();
		} finally {
			ob_end_clean();
		}

		$this->assertInstanceOf(ResponseInterface::class, $response);

		return [$response, $output];
	}
}
