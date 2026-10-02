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

	public function testNothingIsChangedOnceHeadersWereSent(): void
	{
		SapiState::$headersSent = true;
		$emitter = new RecordingEmitter(failures: 1);

		Fallback::emit($emitter, $this->factory()->response(500), ob_get_level());

		$this->assertSame([], SapiState::$statusCodes);
	}
}
