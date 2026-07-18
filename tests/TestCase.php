<?php

declare(strict_types=1);

namespace Celema\Core\Tests;

use Celema\Container\Container;
use Celema\Core\App;
use Celema\Core\Factory\Factory;
use Celema\Core\Factory\Nyholm;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Psr\Http\Message\ResponseInterface as PsrResponse;
use Psr\Http\Message\ServerRequestInterface as PsrServerRequest;

class TestCase extends BaseTestCase
{
	public string $root = __DIR__ . '/Fixtures';

	public function throws(string $exception, ?string $message = null): void
	{
		$this->expectException($exception);

		if ($message) {
			$this->expectExceptionMessage($message);
		}
	}

	public function app(): App
	{
		return App::create();
	}

	public function factory(): Factory
	{
		return new Nyholm();
	}

	public function container(): Container
	{
		return new Container();
	}

	public function response(): PsrResponse
	{
		return $this->factory()->response();
	}

	public function request(
		array $server = [],
		array $headers = [],
		array $cookie = [],
		array $post = [],
		array $get = [],
		?array $files = null,
		mixed $body = null,
	): PsrServerRequest {
		$headers = array_merge([
			'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
			'Accept-Encoding' => 'deflate, gzip;q=1.0, *;q=0.5',
			'Accept-Language' => 'en-US,en;q=0.7,de;q=0.3',
			'Connection' => 'keep-alive',
			'Host' => 'www.example.com',
			'Referer' => 'https://previous.example.com',
			'User-Agent' =>
				'Mozilla/5.0 (Windows; U; Windows NT 6.0; en-GB; rv:1.9.2.6) '
					. 'Gecko/20100625 Firefox/3.6.6 ( .NET CLR 3.5.30729)',
		], $headers);

		$server = array_merge([
			'DOCUMENT_ROOT' => '/var/www/',
			'GATEWAY_INTERFACE' => 'CGI/1.1',
			'HTTPS' => '1',
			'HTTP_ACCEPT' => $headers['Accept'],
			'HTTP_ACCEPT_ENCODING' => $headers['Accept-Encoding'],
			'HTTP_ACCEPT_LANGUAGE' => $headers['Accept-Language'],
			'HTTP_CONNECTION' => $headers['Connection'],
			'HTTP_HOST' => $headers['Host'],
			'HTTP_REFERER' => $headers['Referer'],
			'HTTP_USER_AGENT' => $headers['User-Agent'],
			'PHP_SELF' => '/index.php',
			'REMOTE_ADDR' => '217.254.27.52',
			'REMOTE_PORT' => '73231',
			'REQUEST_METHOD' => 'GET',
			'REQUEST_SCHEME' => 'http',
			'REQUEST_TIME' => '1696692392',
			'REQUEST_URI' => '/',
			'SCRIPT_FILENAME' => '/var/www/albums/index.php',
			'SCRIPT_NAME' => '/albums/index.php',
			'SERVER_ADDR' => '173.230.13.213',
			'SERVER_ADMIN' => 'admin@example.com',
			'SERVER_NAME' => 'www.example.com',
			'SERVER_PORT' => '80',
			'SERVER_PROTOCOL' => 'HTTP/1.1',
			'SERVER_SOFTWARE' => 'nginx/1.22.1',
		], $server);

		$files = array_merge($files ?: $this->getFile());

		$factory = new Psr17Factory();

		$creator = new \Nyholm\Psr7Server\ServerRequestCreator(
			$factory, // ServerRequestFactory
			$factory, // UriFactory
			$factory, // UploadedFileFactory
			$factory, // StreamFactory
		);

		return $creator->fromArrays($server, $headers, $cookie, $get, $post, $files, $body);
	}

	public function getFile(): array
	{
		return [
			'myfile' => [
				'error' => UPLOAD_ERR_OK,
				'name' => '../malic/chuck-test-file.php',
				'size' => 123,
				'tmp_name' => __FILE__,
				'type' => 'text/plain',
			],
			'failingfile' => [
				'error' => UPLOAD_ERR_PARTIAL,
				'name' => 'chuck-failing-test-file.php',
				'size' => 123,
				'tmp_name' => '',
				'type' => 'text/plain',
			],
			'nested' => [
				'myfile' => [
					'error' => UPLOAD_ERR_OK,
					'name' => '../malic/chuck-test-file.php',
					'size' => 123,
					'tmp_name' => __FILE__,
					'type' => 'text/plain',
				],
			],
		];
	}

	public function getFiles(): array
	{
		return [
			'myfile' => [
				'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_PARTIAL],
				'name' => ['test.php', 'test2.php'],
				'size' => [123, 234],
				'tmp_name' => [__FILE__, __FILE__],
				'type' => ['text/plain', 'text/plain'],
			],
			'nested' => [
				'myfile' => [
					'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_PARTIAL],
					'name' => ['test.php', 'test2.php'],
					'size' => [123, 234],
					'tmp_name' => [__FILE__, __FILE__],
					'type' => ['text/plain', 'text/plain'],
				],
			],
		];
	}
}
