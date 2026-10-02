<?php

declare(strict_types=1);

namespace Celema\Core\Tests;

use Celema\Core\Emitter\Fallback;
use Celema\Core\Tests\Fixtures\RecordingEmitter;
use Celema\Core\Tests\Fixtures\SapiState;

final class EmitterFallbackTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		SapiState::reset();
	}

	protected function tearDown(): void
	{
		SapiState::reset();

		parent::tearDown();
	}

	public function testOutputTheRequestLeftInPlaceBecomesThe500Body(): void
	{
		$emitter = new RecordingEmitter(failures: 1);

		Fallback::emit($emitter, $this->factory()->response(500), ob_get_level());

		$this->assertSame([], $emitter->responses);
		$this->assertSame([500], SapiState::$statusCodes);
	}

	public function testBufferThatCannotBeRemovedIsCleanedAndGetsTheResponse(): void
	{
		$level = ob_get_level();
		ob_start();
		echo 'partial';
		SapiState::$bufferFlags = PHP_OUTPUT_HANDLER_CLEANABLE;
		$emitter = new RecordingEmitter();
		$response = $this->factory()->response(500);

		Fallback::emit($emitter, $response, $level);
		$openBuffers = ob_get_level() - $level;
		$output = ob_get_clean();

		$this->assertSame(1, $openBuffers);
		$this->assertSame('', $output);
		$this->assertSame([$response], $emitter->responses);
	}

	public function testOutputOfABufferThatCannotBeCleanedBecomesThe500Body(): void
	{
		$level = ob_get_level();
		ob_start();
		echo 'partial';
		SapiState::$bufferFlags = 0;
		$emitter = new RecordingEmitter(failures: 1);

		Fallback::emit($emitter, $this->factory()->response(500), $level);
		$output = ob_get_clean();

		$this->assertSame('partial', $output);
		$this->assertSame([500], SapiState::$statusCodes);
	}

	public function testNothingIsChangedOnceHeadersWereSent(): void
	{
		SapiState::$headersSent = true;
		$emitter = new RecordingEmitter(failures: 1);

		Fallback::emit($emitter, $this->factory()->response(500), ob_get_level());

		$this->assertSame([], SapiState::$statusCodes);
	}
}
