<?php

declare(strict_types=1);

namespace Celema\Core\Example;

use Celema\Core\Factory\Factory;
use Celema\Core\Request;
use Celema\Core\Response;
use Celema\Router\Router;
use RuntimeException;

/** @api */
final readonly class DemoController
{
	public function __construct(
		private Factory $factory,
		private Router $router,
	) {}

	public function home(Request $request): Response
	{
		$name = $this->string($request->param('name', 'Celema'), 'Celema');
		$name = $name !== '' ? $name : 'Celema';
		$css = $this->escape($this->router->asset('assets', 'app.css', bust: true));
		$js = $this->escape($this->router->asset('assets', 'app.js', bust: true));
		$hello = $this->escape($this->router->url('demo.hello', ['name' => $name]));
		$api = $this->escape($this->router->url('demo.request', query: ['source' => 'link']));
		$xhr = $this->escape($this->router->url('demo.request', query: ['source' => 'xhr']));
		$submit = $this->escape($this->router->url('demo.submit'));
		$redirect = $this->escape($this->router->url('demo.redirect'));
		$error = $this->escape($this->router->url('demo.error'));
		$status201 = $this->escape($this->router->url('demo.status', ['code' => 201]));
		$status418 = $this->escape($this->router->url('demo.status', ['code' => 418]));
		$status503 = $this->escape($this->router->url('demo.status', ['code' => 503]));
		$method = $this->escape($request->method());
		$target = $this->escape($request->target());
		$notice = $request->param('from', '') === 'redirect'
			? '<p class="notice">Redirect completed. The 302 should be visible in the server log.</p>'
			: '';

		return $this->response()->html(<<<HTML
			<!doctype html>
			<html lang="en">
			<head>
				<meta charset="utf-8">
				<meta name="viewport" content="width=device-width, initial-scale=1">
				<title>Celema Core — Runtime Workbench</title>
				<link rel="stylesheet" href="{$css}">
				<script src="{$js}" defer></script>
			</head>
			<body>
				<div class="frame">
					<header class="masthead">
						<div class="identity">
							<p class="eyebrow">Celema / Core / development fixture</p>
							<h1>Runtime<br><em>workbench</em></h1>
							<p class="lede">A compact signal board for routing, HTTP helpers, middleware,
								autowiring, error handling, and both development servers.</p>
						</div>
						<dl class="telemetry" aria-label="Current request">
							<div><dt>Runtime</dt><dd>PHP {$this->escape(PHP_VERSION)}</dd></div>
							<div><dt>Method</dt><dd>{$method}</dd></div>
							<div><dt>Target</dt><dd>{$target}</dd></div>
							<div class="signal"><dt>State</dt><dd><span></span> live</dd></div>
						</dl>
					</header>

					{$notice}

					<main>
						<section class="ledger" aria-labelledby="routes-title">
							<div class="section-heading">
								<p>01 / Route ledger</p>
								<h2 id="routes-title">Watch the request log react.</h2>
							</div>
							<ol>
								<li><a href="{$hello}"><span class="status ok">200</span><span class="verb">GET</span><strong>Typed route parameter</strong><code>/hello/{$this->escape($name)}</code></a></li>
								<li><a href="{$api}"><span class="status ok">200</span><span class="verb">GET</span><strong>Request as JSON</strong><code>/api/request</code></a></li>
								<li><a href="{$redirect}"><span class="status move">302</span><span class="verb">GET</span><strong>Response redirect</strong><code>/redirect</code></a></li>
								<li><a href="/missing"><span class="status warn">404</span><span class="verb">GET</span><strong>Missing route</strong><code>/missing</code></a></li>
								<li><a href="{$submit}"><span class="status warn">405</span><span class="verb">GET</span><strong>Wrong method</strong><code>/submit</code></a></li>
								<li><a href="{$error}"><span class="status fail">500</span><span class="verb">GET</span><strong>Handled exception</strong><code>/error</code></a></li>
							</ol>
						</section>

						<section class="probes" aria-labelledby="probes-title">
							<div class="section-heading">
								<p>02 / Input station</p>
								<h2 id="probes-title">Send something through Core.</h2>
							</div>
							<div class="probe-grid">
								<form class="probe form-probe" action="{$submit}" method="post">
									<p class="probe-number">A</p>
									<h3>Parsed form body</h3>
									<label for="message">Message</label>
									<div class="field-row">
										<input id="message" name="message" value="Hello from the workbench">
										<button type="submit">POST</button>
									</div>
								</form>

								<div class="probe xhr-probe">
									<p class="probe-number">B</p>
									<h3>XHR request marker</h3>
									<p>Fetch JSON with <code>X-Requested-With</code> and inspect the cyan label.</p>
									<button type="button" data-xhr="{$xhr}">Run fetch probe</button>
									<pre id="xhr-output" aria-live="polite">Awaiting signal…</pre>
								</div>
							</div>
						</section>

						<nav class="status-switch" aria-label="Response status probes">
							<span>Manual status</span>
							<a href="{$status201}">201 / created</a>
							<a href="{$status418}">418 / client error</a>
							<a href="{$status503}">503 / unavailable</a>
						</nav>
					</main>

					<footer>
						<p>Middleware adds <code>Server-Timing</code> and <code>X-Core-Example</code>.</p>
						<p>Default port <strong>1973</strong></p>
					</footer>
				</div>
			</body>
			</html>
			HTML);
	}

	public function hello(string $name): Response
	{
		$css = $this->escape($this->router->asset('assets', 'app.css', bust: true));
		$home = $this->escape($this->router->url('demo.home'));
		$name = $this->escape($name);

		return $this->response()->html(<<<HTML
			<!doctype html>
			<html lang="en">
			<head>
				<meta charset="utf-8">
				<meta name="viewport" content="width=device-width, initial-scale=1">
				<title>Hello {$name} — Celema Core</title>
				<link rel="stylesheet" href="{$css}">
			</head>
			<body class="single-page">
				<main class="single">
					<p class="eyebrow">Typed route parameter resolved</p>
					<h1>Hello, <em>{$name}</em>.</h1>
					<p>The controller and its <code>Factory</code> and <code>Router</code> dependencies were autowired.</p>
					<a class="back" href="{$home}">← Return to workbench</a>
				</main>
			</body>
			</html>
			HTML);
	}

	public function request(Request $request): Response
	{
		return $this->response()->json([
			'ok' => true,
			'method' => $request->method(),
			'target' => $request->target(),
			'query' => $request->params(),
			'xhr' => strtolower($request->header('X-Requested-With')) === 'xmlhttprequest',
			'middleware' => is_int($request->get('example.started', null)),
			'php' => PHP_VERSION,
		]);
	}

	public function submit(Request $request): Response
	{
		$message = $this->string($request->field('message', ''));

		return $this->response()->json([
			'ok' => true,
			'method' => $request->method(),
			'message' => $message,
		]);
	}

	public function redirect(): Response
	{
		return $this->response()->redirect($this->router->url(
			'demo.home',
			query: ['from' => 'redirect'],
		));
	}

	public function status(int $code): Response
	{
		return $this->response()->text("Intentional {$code} response from the Core workbench.\n", $code);
	}

	public function error(): never
	{
		throw new RuntimeException('Intentional failure from the Core example app');
	}

	private function response(): Response
	{
		return Response::create($this->factory);
	}

	private function escape(string $value): string
	{
		return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}

	private function string(mixed $value, string $default = ''): string
	{
		return is_string($value) ? $value : $default;
	}
}
