<?php

declare(strict_types=1);

namespace Celema\Core\Tests;

use Celema\Core\Emitter\Sapi;
use Celema\Core\Exception\RuntimeException;
use Celema\Core\Tests\Fixtures\SapiState;
use Celema\Core\Tests\Fixtures\TestStream;
use PHPUnit\Framework\Attributes\TestWith;
use Psr\Http\Message\ResponseInterface as Response;

final class EmitterSapiTest extends TestCase
{
	protected function setUp(): void
	{
		SapiState::reset();
	}

	private function emit(
		Response $response,
		bool $withoutBody = false,
		int $maxBufferLength = 8192,
	): string {
		$emitter = new Sapi($maxBufferLength);
		ob_start();
		$emitter->emit($response, $withoutBody);

		return (string) ob_get_clean();
	}

	public function testStatusLineAndHeaders(): void
	{
		$response = $this
			->response()
			->withHeader('content-type', 'text/plain')
			->withBody(new TestStream('hello'));

		$output = $this->emit($response);

		$this->assertSame('hello', $output);
		$this->assertSame(['Content-Type: text/plain', true, 200], SapiState::$headers[0]);
		$this->assertSame(['HTTP/1.1 200 OK', true, 200], SapiState::$headers[1]);
	}

	public function testStatusLineWithoutReasonPhrase(): void
	{
		$this->emit($this->response()->withStatus(599));

		$this->assertSame(['HTTP/1.1 599'], SapiState::headerLines());
	}

	public function testMultiValueHeaders(): void
	{
		$response = $this
			->response()
			->withAddedHeader('Vary', 'Accept')
			->withAddedHeader('Vary', 'Accept-Encoding')
			->withAddedHeader('Set-Cookie', 'a=1')
			->withAddedHeader('Set-Cookie', 'b=2');

		$this->emit($response);

		$this->assertSame(['Vary: Accept', true, 200], SapiState::$headers[0]);
		$this->assertSame(['Vary: Accept-Encoding', false, 200], SapiState::$headers[1]);
		$this->assertSame(['Set-Cookie: a=1', false, 200], SapiState::$headers[2]);
		$this->assertSame(['Set-Cookie: b=2', false, 200], SapiState::$headers[3]);
	}

	public function testWithoutBodyKeepsHeaders(): void
	{
		$response = $this
			->response()
			->withHeader('Content-Length', '5')
			->withBody(new TestStream('hello'));

		$output = $this->emit($response, withoutBody: true);

		$this->assertSame('', $output);
		$this->assertSame(
			['Content-Length: 5', 'HTTP/1.1 200 OK'],
			SapiState::headerLines(),
		);
	}

	#[TestWith([100])]
	#[TestWith([204])]
	#[TestWith([304])]
	public function testBodylessStatus(int $statusCode): void
	{
		$response = $this
			->response()
			->withStatus($statusCode)
			->withBody(new TestStream('hidden'));

		$this->assertSame('', $this->emit($response));
	}

	public function testSeekableBodyIsRewound(): void
	{
		$body = new TestStream('hello');
		$body->read(3);

		$output = $this->emit($this->response()->withBody($body), maxBufferLength: 2);

		$this->assertSame('hello', $output);
	}

	public function testNonSeekableBodyIsNotRewound(): void
	{
		$body = new TestStream('hello', seekable: false);
		$body->read(3);

		$this->assertSame('lo', $this->emit($this->response()->withBody($body)));
	}

	public function testNonReadableBodyIsCast(): void
	{
		$body = new TestStream('hello', readable: false);

		$this->assertSame('hello', $this->emit($this->response()->withBody($body)));
	}

	#[TestWith([2])]
	#[TestWith([3])]
	#[TestWith([8192])]
	public function testContentRange(int $maxBufferLength): void
	{
		$response = $this
			->response()
			->withHeader('Content-Range', 'bytes 2-5/10')
			->withBody(new TestStream('0123456789'));

		$output = $this->emit($response, maxBufferLength: $maxBufferLength);

		$this->assertSame('2345', $output);
	}

	public function testContentRangeWithUnknownLength(): void
	{
		$response = $this
			->response()
			->withHeader('Content-Range', 'bytes 0-1/*')
			->withBody(new TestStream('0123456789'));

		$this->assertSame('01', $this->emit($response));
	}

	public function testContentRangeWithNonSeekableBody(): void
	{
		$response = $this
			->response()
			->withHeader('Content-Range', 'bytes 2-5/10')
			->withBody(new TestStream('0123456789', seekable: false));

		$this->assertSame('0123', $this->emit($response, maxBufferLength: 3));
	}

	public function testContentRangeWithNonReadableBody(): void
	{
		$response = $this
			->response()
			->withHeader('Content-Range', 'bytes 2-5/10')
			->withBody(new TestStream('0123456789', readable: false));

		$this->assertSame('2345', $this->emit($response));
	}

	public function testContentRangeWithNonReadableNonSeekableBody(): void
	{
		$response = $this
			->response()
			->withHeader('Content-Range', 'bytes 2-5/10')
			->withBody(new TestStream('0123456789', seekable: false, readable: false));

		$this->assertSame('2345', $this->emit($response));
	}

	#[TestWith(['bytes 5-2/10'])]
	#[TestWith(['items 0-1/10'])]
	#[TestWith(['bytes 0-1'])]
	public function testInvalidContentRangeEmitsWholeBody(string $contentRange): void
	{
		$response = $this
			->response()
			->withHeader('Content-Range', $contentRange)
			->withBody(new TestStream('0123456789'));

		$this->assertSame('0123456789', $this->emit($response));
	}

	public function testHeadersSentThrows(): void
	{
		SapiState::$headersSent = true;

		$this->throws(RuntimeException::class, 'Headers already sent in output.php on line 17');

		new Sapi()->emit($this->response());
	}

	public function testPreviousOutputThrows(): void
	{
		ob_start();
		echo 'previous output';
		$thrown = null;

		try {
			new Sapi()->emit($this->response());
		} catch (RuntimeException $exception) {
			$thrown = $exception;
		} finally {
			ob_end_clean();
		}

		$this->assertInstanceOf(RuntimeException::class, $thrown);
		$this->assertSame('Output already present in the output buffer', $thrown->getMessage());
	}
}
