<?php

declare(strict_types=1);

namespace Celema\Core\Tests;

use Celema\Core\App;
use Celema\Router\Group;
use Celema\Router\Route;

final class AppRoutingTest extends TestCase
{
	public function testStaticRouteHelper(): void
	{
		$app = App::create();
		$app->staticRoute('/static', "{$this->root}/public/static", 'static');
		$app->staticRoute('/unnamedstatic', "{$this->root}/public/static");

		$this->assertSame('/static/test.json', $app->router()->asset('static', 'test.json'));
		$this->assertSame('/unnamedstatic/test.json', $app->router()->asset(
			'/unnamedstatic',
			'test.json',
		));
	}

	public function testAppAddRouteAndGroupHelpers(): void
	{
		$app = $this->app();
		$route = new Route('/albums', [Fixtures\TestController::class, 'textView'], 'albums');
		$app->addRoute($route);
		$app->group(
			'/albums',
			static function (Group $group): void {
				$ctrl = Fixtures\TestController::class;
				$group->get('/{name}', [$ctrl, 'textView'], 'name');
			},
			'albums:',
		);

		$this->assertSame('/albums', $app->router()->url('albums'));
		$this->assertSame('/albums/symbolic', $app->router()->url('albums:name', [
			'name' => 'symbolic',
		]));
	}

	public function testAppAnyHelper(): void
	{
		$app = $this->app();
		$app->any('/albums', [Fixtures\TestController::class, 'textView'], 'albums');

		$this->assertSame('/albums', $app->router()->url('albums'));
	}

	public function testAppMapHelper(): void
	{
		$app = $this->app();
		$route = $app->map(
			['GET', 'POST'],
			'/login',
			[Fixtures\TestController::class, 'textView'],
			'login',
		);

		$this->assertSame(['GET', 'POST'], $route->methods());
		$this->assertSame('/login', $app->router()->url('login'));
	}

	public function testAppGetHelper(): void
	{
		$app = $this->app();
		$app->get('/albums', [Fixtures\TestController::class, 'textView'], 'albums');

		$this->assertSame('/albums', $app->router()->url('albums'));
	}

	public function testAppPostHelper(): void
	{
		$app = $this->app();
		$app->post('/albums', [Fixtures\TestController::class, 'textView'], 'albums');

		$this->assertSame('/albums', $app->router()->url('albums'));
	}

	public function testAppPutHelper(): void
	{
		$app = $this->app();
		$app->put('/albums', [Fixtures\TestController::class, 'textView'], 'albums');

		$this->assertSame('/albums', $app->router()->url('albums'));
	}

	public function testAppPatchHelper(): void
	{
		$app = $this->app();
		$app->patch('/albums', [Fixtures\TestController::class, 'textView'], 'albums');

		$this->assertSame('/albums', $app->router()->url('albums'));
	}

	public function testAppDeleteHelper(): void
	{
		$app = $this->app();
		$app->delete('/albums', [Fixtures\TestController::class, 'textView'], 'albums');

		$this->assertSame('/albums', $app->router()->url('albums'));
	}

	public function testAppHeadHelper(): void
	{
		$app = $this->app();
		$app->head('/albums', [Fixtures\TestController::class, 'textView'], 'albums');

		$this->assertSame('/albums', $app->router()->url('albums'));
	}

	public function testAppOptionsHelper(): void
	{
		$app = $this->app();
		$app->options('/albums', [Fixtures\TestController::class, 'textView'], 'albums');

		$this->assertSame('/albums', $app->router()->url('albums'));
	}

	public function testAppGroupHelper(): void
	{
		$app = $this->app();
		$app->group(
			'/albums',
			static function (Group $group): void {
				$ctrl = Fixtures\TestController::class;
				$group->get('/{name}', [$ctrl, 'textView'], 'name');
			},
			'albums:',
		);

		$this->assertSame('/albums/symbolic', $app->router()->url('albums:name', [
			'name' => 'symbolic',
		]));
	}
}
