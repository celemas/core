<?php

declare(strict_types=1);

namespace Celema\Core;

use Laminas\HttpHandlerRunner\Emitter\EmitterInterface;
use Laminas\HttpHandlerRunner\Emitter\EmitterStack;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use Laminas\HttpHandlerRunner\Emitter\SapiStreamEmitter;
use Override;
use Psr\Http\Message\ResponseInterface;

final class Emitter implements EmitterInterface
{
	protected EmitterStack $stack;

	public function __construct(int $maxBufferLength = 8192)
	{
		$sapiStreamEmitter = new SapiStreamEmitter($maxBufferLength);
		$conditionalEmitter = new class($sapiStreamEmitter) implements EmitterInterface {
			private EmitterInterface $emitter;

			public function __construct(EmitterInterface $emitter)
			{
				$this->emitter = $emitter;
			}

			#[Override]
			public function emit(ResponseInterface $response): bool
			{
				if (!$response->hasHeader('Content-Disposition') && !$response->hasHeader('Content-Range')) {
					return false;
				}

				return $this->emitter->emit($response);
			}
		};

		$this->stack = new EmitterStack();
		$this->stack->push(new SapiEmitter());
		$this->stack->push($conditionalEmitter);
	}

	#[Override]
	public function emit(ResponseInterface $response): bool
	{
		return $this->stack->emit($response);
	}
}
