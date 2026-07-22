<?php

declare(strict_types=1);

namespace Celema\Core\Tests\Fixtures;

use Psr\Http\Message\StreamInterface;
use RuntimeException;

final class TestStream implements StreamInterface
{
	private int $position = 0;

	public function __construct(
		private readonly string $content,
		private readonly bool $seekable = true,
		private readonly bool $readable = true,
	) {}

	public function __toString(): string
	{
		return $this->content;
	}

	public function close(): void {}

	public function detach()
	{
		return null;
	}

	public function getSize(): ?int
	{
		return strlen($this->content);
	}

	public function tell(): int
	{
		return $this->position;
	}

	public function eof(): bool
	{
		return $this->position >= strlen($this->content);
	}

	public function isSeekable(): bool
	{
		return $this->seekable;
	}

	public function seek(int $offset, int $whence = SEEK_SET): void
	{
		$this->position = $offset;
	}

	public function rewind(): void
	{
		$this->position = 0;
	}

	public function isWritable(): bool
	{
		return false;
	}

	public function write(string $string): int
	{
		throw new RuntimeException('Stream is not writable');
	}

	public function isReadable(): bool
	{
		return $this->readable;
	}

	public function read(int $length): string
	{
		$chunk = substr($this->content, $this->position, $length);
		$this->position += strlen($chunk);

		return $chunk;
	}

	public function getContents(): string
	{
		$contents = substr($this->content, $this->position);
		$this->position = strlen($this->content);

		return $contents;
	}

	public function getMetadata(?string $key = null)
	{
		return null;
	}
}
