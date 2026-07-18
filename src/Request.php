<?php

declare(strict_types=1);

namespace Celema\Core;

use Celema\Core\Exception\OutOfBoundsException;
use Celema\Core\Exception\RuntimeException;
use Celema\Router\RequestWrapper;
use Override;
use Psr\Http\Message\ServerRequestInterface as PsrServerRequest;
use Psr\Http\Message\StreamInterface as PsrStream;
use Psr\Http\Message\UploadedFileInterface as PsrUploadedFile;
use Psr\Http\Message\UriInterface as PsrUri;

/** @api */
class Request implements RequestWrapper
{
	public function __construct(
		protected PsrServerRequest $psrRequest,
	) {}

	#[Override]
	public function unwrap(): PsrServerRequest
	{
		return $this->psrRequest;
	}

	public function wrap(PsrServerRequest $request): static
	{
		$this->psrRequest = $request;

		return $this;
	}

	public function params(): array
	{
		return $this->psrRequest->getQueryParams();
	}

	public function param(string $key, mixed $default = null): mixed
	{
		$params = $this->psrRequest->getQueryParams();
		$error = 'Query string variable not found';

		return $this->returnOrFail($params, $key, $default, $error, func_num_args());
	}

	public function form(): ?array
	{
		$body = $this->psrRequest->getParsedBody();
		assert($body === null || is_array($body), 'Parsed form body must be null or an array');

		return $body;
	}

	public function field(string $key, mixed $default = null): mixed
	{
		$body = $this->psrRequest->getParsedBody();
		assert($body === null || is_array($body), 'Parsed form body must be null or an array');
		$error = 'Form field not found';

		return $this->returnOrFail($body, $key, $default, $error, func_num_args());
	}

	public function cookies(): array
	{
		return $this->psrRequest->getCookieParams();
	}

	public function cookie(string $key, mixed $default = null): mixed
	{
		$params = $this->psrRequest->getCookieParams();
		$error = 'Cookie not found';

		return $this->returnOrFail($params, $key, $default, $error, func_num_args());
	}

	public function serverParams(): array
	{
		return $this->psrRequest->getServerParams();
	}

	public function server(string $key, mixed $default = null): mixed
	{
		$params = $this->psrRequest->getServerParams();
		$error = 'Server parameter not found';

		return $this->returnOrFail($params, $key, $default, $error, func_num_args());
	}

	public function header(string $name): string
	{
		return $this->psrRequest->getHeaderLine($name);
	}

	public function headerArray(string $header): array
	{
		return $this->psrRequest->getHeader($header);
	}

	public function headers(bool $firstOnly = false): array
	{
		$headers = $this->psrRequest->getHeaders();

		if ($firstOnly) {
			return array_combine(
				array_keys($headers),
				array_map(static fn(array $val): string => $val[0], $headers),
			);
		}

		return $headers;
	}

	public function setHeader(string $header, string $value): static
	{
		$this->psrRequest = $this->psrRequest->withHeader($header, $value);

		return $this;
	}

	public function addHeader(string $header, string $value): static
	{
		$this->psrRequest = $this->psrRequest->withAddedHeader($header, $value);

		return $this;
	}

	public function removeHeader(string $header): static
	{
		$this->psrRequest = $this->psrRequest->withoutHeader($header);

		return $this;
	}

	public function hasHeader(string $header): bool
	{
		return $this->psrRequest->hasHeader($header);
	}

	public function attributes(): array
	{
		return $this->psrRequest->getAttributes();
	}

	public function set(string $attribute, mixed $value): static
	{
		$this->psrRequest = $this->psrRequest->withAttribute($attribute, $value);

		return $this;
	}

	public function get(string $key, mixed $default = null): mixed
	{
		$params = $this->psrRequest->getAttributes();
		$error = 'Request attribute not found';

		return $this->returnOrFail($params, $key, $default, $error, func_num_args());
	}

	public function uri(): PsrUri
	{
		return $this->psrRequest->getUri();
	}

	public function origin(): string
	{
		$uri = $this->psrRequest->getUri();
		$scheme = $uri->getScheme();
		$origin = $scheme ? $scheme . ':' : '';
		$authority = $uri->getAuthority();
		$origin .= $authority ? '//' . $authority : '';

		return $origin;
	}

	public function target(): string
	{
		return $this->psrRequest->getRequestTarget();
	}

	public function method(): string
	{
		return strtoupper($this->psrRequest->getMethod());
	}

	public function isMethod(string $method): bool
	{
		return strtoupper($method) === $this->method();
	}

	public function body(): PsrStream
	{
		return $this->psrRequest->getBody();
	}

	public function json(
		int $flags = JSON_OBJECT_AS_ARRAY,
	): mixed {
		$body = (string) $this->psrRequest->getBody();

		return json_decode(
			$body,
			true,
			512, // PHP default value
			$flags,
		);
	}

	/**
	 * Returns always a list of uploaded files, even if there is
	 * only one file.
	 *
	 * Psalm does not support multi file uploads yet and complains
	 * about type issues. We need to suppres some of these errors.
	 *
	 * @no-named-arguments
	 *
	 * @param list<string>|string ...$keys
	 *
	 * @throws OutOfBoundsException RuntimeException
	 */
	public function files(array|string ...$keys): array
	{
		$files = $this->psrRequest->getUploadedFiles();
		$keys = $this->validateKeys($keys);

		if (count($keys) === 0) {
			return $files;
		}

		// Walk into the uploaded files structure
		foreach ($keys as $key) {
			if (is_array($files) && array_key_exists($key, $files)) {
				/**
				 * @psalm-suppress MixedAssignment
				 *
				 * Psalm does not support recursive types like:
				 *     T = array<string, string|T>
				 */
				$files = $files[$key];
			} else {
				throw new OutOfBoundsException('Invalid files key ' . $this->formatKeys($keys));
			}
		}

		// Check if it is a single file upload.
		// A multifile upload would already produce an array
		if ($files instanceof PsrUploadedFile) {
			return [$files];
		}

		assert(is_array($files), 'Uploaded files selection must resolve to an array');

		return $files;
	}

	/**
	 * Psalm does not support multi file uploads yet and complains
	 * about type issues. We need to suppres some of the errors.
	 *
	 * @no-named-arguments
	 *
	 * @param list<non-empty-string>|string ...$keys
	 *
	 * @throws OutOfBoundsException RuntimeException
	 */
	public function file(array|string ...$keys): PsrUploadedFile
	{
		$keys = $this->validateKeys($keys);

		if (count($keys) === 0) {
			throw new RuntimeException('No file key given');
		}

		$files = $this->psrRequest->getUploadedFiles();
		$i = 0;

		foreach ($keys as $key) {
			if (isset($files[$key])) {
				/** @var array|PsrUploadedFile $files */
				$files = $files[$key];
				$i++;

				if ($files instanceof PsrUploadedFile) {
					if ($i < count($keys)) {
						throw new OutOfBoundsException(
							'Invalid file key (too deep) ' . $this->formatKeys($keys),
						);
					}

					return $files;
				}
			} else {
				throw new OutOfBoundsException('Invalid file key ' . $this->formatKeys($keys));
			}
		}

		throw new RuntimeException('Multiple files available at key ' . $this->formatKeys($keys));
	}

	private function returnOrFail(
		?array $array,
		string $key,
		mixed $default,
		string $error,
		int $numArgs,
	): mixed {
		if (($array === null || !array_key_exists($key, $array)) && $numArgs > 1) {
			return $default;
		}

		assert($array !== null, 'Input array must not be null when no default value is provided');

		if (array_key_exists($key, $array)) {
			return $array[$key];
		}

		throw new OutOfBoundsException("{$error}: '{$key}'");
	}

	/** @param non-empty-list<string> $keys */
	private function formatKeys(array $keys): string
	{
		return implode('', array_map(
			static fn($key) => "['" . $key . "']",
			$keys,
		));
	}

	/**
	 * @param list<list<string>|string> $keys
	 *
	 * @return list<string>
	 */
	private function validateKeys(array $keys): array
	{
		if (isset($keys[0]) && is_array($keys[0])) {
			if (count($keys) > 1) {
				throw new RuntimeException('Either provide a single array or plain string arguments');
			}
			$keys = $keys[0];
		}

		/** @var list<string> $keys */
		return $keys;
	}
}
