<?php

declare(strict_types=1);

use Celema\Core\App;
use Celema\Core\Error\Handler;
use Celema\Core\Example\DemoController;
use Celema\Core\Example\TimingMiddleware;
use Celema\Router\Group;

$app = App::create();
$app->errorHandler(new Handler($app->factory()->responseFactory()));
$app->middleware(new TimingMiddleware());
$app->staticRoute('/assets', __DIR__ . '/public/assets', 'assets');

$app->group(
	'',
	static function (Group $demo): void {
		$demo->controller(DemoController::class);
		$demo->get('/', 'home', 'home');
		$demo->get('/hello/{name}', 'hello', 'hello');
		$demo->get('/api/request', 'request', 'request');
		$demo->post('/submit', 'submit', 'submit');
		$demo->get('/redirect', 'redirect', 'redirect');
		$demo->get('/status/{code:[2-5]\d{2}}', 'status', 'status');
		$demo->get('/error', 'error', 'error');
	},
	'demo.',
);

return $app;
