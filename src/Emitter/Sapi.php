<?php

declare(strict_types=1);

namespace Celema\Core\Emitter;

use Celema\Core\Exception\RuntimeException;
use Override;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\StreamInterface as Stream;

/** @api */
final class Sapi implements Emitter
{
	public function __construct(
		private readonly int $maxBufferLength = 8192,
	) {}

	#[Override]
	public function emit(Response $response, bool $withoutBody = false): bool
	{
		$this->assertNoPreviousOutput();
		$this->emitHeaders($response);
		$this->emitStatusLine($response);
		flush();

		if ($withoutBody || $this->isBodyless($response->getStatusCode())) {
			return true;
		}

		$range = $this->contentRange($response->getHeaderLine('Content-Range'));

		if ($range === null) {
			$this->emitBody($response->getBody());
		} else {
			$this->emitBodyRange($response->getBody(), $range[0], $range[1]);
		}

		return true;
	}

	private function assertNoPreviousOutput(): void
	{
		if (headers_sent($file, $line)) {
			throw new RuntimeException("Headers already sent in {$file} on line {$line}");
		}

		if (ob_get_level() > 0 && ob_get_length() > 0) {
			throw new RuntimeException('Output already present in the output buffer');
		}
	}

	/**
	 * Emitted before the status line so PHP cannot override the status
	 * code with one derived from a header like Location.
	 */
	private function emitHeaders(Response $response): void
	{
		$statusCode = $response->getStatusCode();

		foreach ($response->getHeaders() as $name => $values) {
			$name = ucwords((string) $name, '-');
			$replace = $name !== 'Set-Cookie';

			foreach ($values as $value) {
				header("{$name}: {$value}", $replace, $statusCode);
				$replace = false;
			}
		}
	}

	private function emitStatusLine(Response $response): void
	{
		$statusCode = $response->getStatusCode();
		$reasonPhrase = $response->getReasonPhrase();

		header(
			sprintf(
				'HTTP/%s %d%s',
				$response->getProtocolVersion(),
				$statusCode,
				$reasonPhrase === '' ? '' : " {$reasonPhrase}",
			),
			true,
			$statusCode,
		);
	}

	/**
	 * A 1xx, 204, or 304 response must not include a body (RFC 9110).
	 */
	private function isBodyless(int $statusCode): bool
	{
		return $statusCode < 200 || $statusCode === 204 || $statusCode === 304;
	}

	/**
	 * Returns [first, last] byte positions from a valid bytes Content-Range
	 * header, or null when the whole body should be emitted.
	 *
	 * @return array{0: int, 1: int}|null
	 */
	private function contentRange(string $header): ?array
	{
		if (preg_match('/^bytes (?P<first>\d+)-(?P<last>\d+)\/(?:\d+|\*)$/', $header, $matches) !== 1) {
			return null;
		}

		$first = (int) $matches['first'];
		$last = (int) $matches['last'];

		return $first <= $last ? [$first, $last] : null;
	}

	private function emitBody(Stream $body): void
	{
		if ($body->isSeekable()) {
			$body->rewind();
		}

		if (!$body->isReadable()) {
			echo (string) $body;

			return;
		}

		while (!$body->eof()) {
			echo $body->read($this->maxBufferLength);
		}
	}

	private function emitBodyRange(Stream $body, int $first, int $last): void
	{
		$length = $last - $first + 1;

		if ($body->isSeekable()) {
			$body->seek($first);
			$first = 0;
		}

		if (!$body->isReadable()) {
			echo substr($body->getContents(), $first, $length);

			return;
		}

		$remaining = $length;

		while ($remaining >= $this->maxBufferLength && !$body->eof()) {
			$contents = $body->read($this->maxBufferLength);
			$remaining -= strlen($contents);
			echo $contents;
		}

		if ($remaining > 0 && !$body->eof()) {
			echo $body->read($remaining);
		}
	}
}
