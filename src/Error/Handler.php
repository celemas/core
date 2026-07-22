<?php

declare(strict_types=1);

namespace Celema\Core\Error;

use Celema\Core\Exception\HttpError;
use Celema\Core\Exception\HttpMethodNotAllowed;
use Celema\Core\Exception\HttpNotFound;
use Celema\Router\Exception\MethodNotAllowedException;
use Celema\Router\Exception\NotFoundException;
use Celema\Server\Console as ServerConsole;
use ErrorException;
use Override;
use Psr\Http\Message\ResponseFactoryInterface as ResponseFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Log\LoggerInterface as Logger;
use Throwable;

/** @api */
class Handler implements Middleware
{
	protected ?Logger $logger = null;
	protected ?DebugHandler $debugHandler = null;

	/** @var list<RendererEntry> */
	protected array $renderers = [];

	protected ?RendererEntry $defaultRenderer = null;

	public function __construct(
		protected readonly ResponseFactory $responseFactory,
		protected readonly bool $debug = false,
	) {}

	public function debugHandler(DebugHandler $debugHandler): void
	{
		$this->debugHandler = $debugHandler;
	}

	public function logger(?Logger $logger = null): void
	{
		$this->logger = $logger;
	}

	#[Override]
	public function process(Request $request, RequestHandler $handler): Response
	{
		set_error_handler([$this, 'handleError'], E_ALL);

		try {
			return $handler->handle($request);
		} catch (Throwable $e) {
			return $this->response($e, $request);
		} finally {
			restore_error_handler();
		}
	}

	/**
	 * @param class-string<Throwable>|list<class-string<Throwable>>|null $exceptions
	 */
	public function renderer(Renderer $renderer, string|array|null $exceptions = null): RendererEntry
	{
		if ($exceptions === null) {
			$entry = new RendererEntry([], $renderer);
			$this->defaultRenderer = $entry;

			return $entry;
		}

		$classes = (array) $exceptions;
		$entry = new RendererEntry($classes, $renderer);
		$this->renderers[] = $entry;

		return $entry;
	}

	public function handleError(
		int $level,
		string $message,
		string $file = '',
		int $line = 0,
	): bool {
		if (($level & error_reporting()) !== 0) {
			throw new ErrorException($message, 0, $level, $file, $line);
		}

		return false;
	}

	public function response(Throwable $exception, Request $request): Response
	{
		$exception = $this->normalize($exception, $request);
		$renderer = null;
		$logLevel = null;

		foreach ($this->renderers as $entry) {
			if (!$entry->matches($exception)) {
				continue;
			}

			$renderer = $entry->renderer;
			$logLevel = $entry->logLevel();
			break;
		}

		if ($logLevel !== null) {
			$this->log($logLevel, $exception);
		}

		if ($renderer) {
			$this->recordServerException($exception);

			return $renderer->render(
				$exception,
				$this->responseFactory,
				$request,
				$this->debug,
			);
		}

		if ($this->debug) {
			if ($this->debugHandler) {
				$this->recordServerException($exception);

				return $this->debugHandler->handle($exception, $this->responseFactory);
			}

			throw $exception;
		}

		if ($this->defaultRenderer) {
			$logLevel = $this->defaultRenderer->logLevel();

			if ($logLevel !== null) {
				$this->log($logLevel, $exception);
			} else {
				$this->logUnmatched($exception);
			}

			$this->recordServerException($exception);

			return $this->defaultRenderer->renderer->render(
				$exception,
				$this->responseFactory,
				$request,
				$this->debug,
			);
		}

		$this->logUnmatched($exception);
		$this->recordServerException($exception);

		return $this->fallback($exception);
	}

	protected function normalize(Throwable $exception, Request $request): Throwable
	{
		if ($exception instanceof NotFoundException) {
			return new HttpNotFound($request, previous: $exception);
		}

		if ($exception instanceof MethodNotAllowedException) {
			return new HttpMethodNotAllowed(
				$request,
				['allowed' => $exception->allowedMethods()],
				previous: $exception,
			);
		}

		return $exception;
	}

	protected function fallback(Throwable $exception): Response
	{
		$status = $this->status($exception);
		$title = $exception instanceof HttpError
			? $exception->title()
			: '500 Internal Server Error';
		$response = $this->responseFactory
			->createResponse($status)
			->withHeader('Content-Type', 'text/html; charset=utf-8');
		$response->getBody()->write('<h1>' . $this->escape($title) . '</h1>');

		return $response;
	}

	protected function status(Throwable $exception): int
	{
		if (!$exception instanceof HttpError) {
			return 500;
		}

		$status = $exception->statusCode();

		return $status >= 400 && $status <= 599 ? $status : 500;
	}

	protected function log(string|int $logLevel, Throwable $exception): void
	{
		$this->logger?->log($logLevel, 'Matched exception', ['exception' => $exception]);
	}

	protected function recordServerException(Throwable $exception): void
	{
		// The celema/server dev server is an optional dependency; report
		// handled server errors to its request log when it is installed.
		/** @psalm-suppress UndefinedClass */
		if ($this->status($exception) >= 500 && class_exists(ServerConsole::class)) {
			ServerConsole::recordException($exception, trace: $this->debug);
		}
	}

	protected function logUnmatched(Throwable $exception): void
	{
		$this->logger?->alert('Unmatched exception', ['exception' => $exception]);
	}

	private function escape(string $value): string
	{
		return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}
}
