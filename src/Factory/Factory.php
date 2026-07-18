<?php

declare(strict_types=1);

namespace Celema\Core\Factory;

use Psr\Http\Message\RequestFactoryInterface as RequestFactory;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseFactoryInterface as ResponseFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestFactoryInterface as ServerRequestFactory;
use Psr\Http\Message\ServerRequestInterface as ServerRequest;
use Psr\Http\Message\StreamFactoryInterface as StreamFactory;
use Psr\Http\Message\StreamInterface as Stream;
use Psr\Http\Message\UploadedFileFactoryInterface as UploadedFileFactory;
use Psr\Http\Message\UploadedFileInterface as UploadedFile;
use Psr\Http\Message\UriFactoryInterface as UriFactory;
use Psr\Http\Message\UriInterface as Uri;

/** @api */
interface Factory
{
	public function serverRequest(): ServerRequest;

	public function request(string $method, Uri|string $uri): Request;

	public function response(int $code = 200, string $reasonPhrase = ''): Response;

	public function stream(string $content = ''): Stream;

	public function streamFromFile(string $filename, string $mode = 'r'): Stream;

	public function streamFromResource(mixed $resource): Stream;

	public function uploadedFile(
		Stream $stream,
		?int $size = null,
		int $error = \UPLOAD_ERR_OK,
		?string $clientFilename = null,
		?string $clientMediaType = null,
	): UploadedFile;

	public function uri(string $uri = ''): Uri;

	public function requestFactory(): RequestFactory;

	public function responseFactory(): ResponseFactory;

	public function serverRequestFactory(): ServerRequestFactory;

	public function streamFactory(): StreamFactory;

	public function uploadedFileFactory(): UploadedFileFactory;

	public function uriFactory(): UriFactory;
}
