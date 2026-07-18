<?php

declare(strict_types=1);

namespace Celema\Core\Tests;

use Celema\Core\Exception\OutOfBoundsException;
use Celema\Core\Exception\RuntimeException;
use Celema\Core\Request;
use Psr\Http\Message\UploadedFileInterface as PsrUploadedFile;

final class RequestFileTest extends TestCase
{
	public function testGetFileInstance(): void
	{
		$request = new Request($this->request());
		$file = $request->file('myfile');

		$this->assertInstanceOf(PsrUploadedFile::class, $file);
	}

	public function testFailCallingFileWithoutKey(): void
	{
		$this->throws(RuntimeException::class, 'No file key');

		$request = new Request($this->request());
		$request->file();
	}

	public function testGetNestedFileInstance(): void
	{
		$request = new Request($this->request());
		$file = $request->file('nested', 'myfile');

		$this->assertInstanceOf(PsrUploadedFile::class, $file);
	}

	public function testGetAllFiles(): void
	{
		$request = new Request($this->request(files: $this->getFiles()));
		$files = $request->files();

		$this->assertSame(2, count($files));
		$this->assertSame(true, isset($files['myfile']));
		$this->assertSame(true, isset($files['nested']));
	}

	public function testGetFilesInstances(): void
	{
		$request = new Request($this->request(files: $this->getFiles()));
		$files = $request->files('myfile');

		$this->assertSame(2, count($files));
		$this->assertInstanceOf(PsrUploadedFile::class, $files[0]);
		$this->assertInstanceOf(PsrUploadedFile::class, $files[1]);
	}

	public function testGetNestedFilesInstances(): void
	{
		$request = new Request($this->request(files: $this->getFiles()));
		$files = $request->files('nested', 'myfile');

		$this->assertSame(2, count($files));
		$this->assertInstanceOf(PsrUploadedFile::class, $files[0]);
		$this->assertInstanceOf(PsrUploadedFile::class, $files[1]);
	}

	public function testGetNestedFilesInstancesUsingAnArray(): void
	{
		$request = new Request($this->request(files: $this->getFiles()));
		$files = $request->files(['nested', 'myfile']);

		$this->assertSame(2, count($files));
		$this->assertInstanceOf(PsrUploadedFile::class, $files[0]);
		$this->assertInstanceOf(PsrUploadedFile::class, $files[1]);
	}

	public function testGetFilesInstancesWithOnlyOnePresent(): void
	{
		$request = new Request($this->request());
		$files = $request->files('myfile');

		$this->assertSame(1, count($files));
		$this->assertInstanceOf(PsrUploadedFile::class, $files[0]);
	}

	public function testAccessSingleFileWhenMulitpleAreAvailable(): void
	{
		$this->throws(RuntimeException::class, 'Multiple files');

		$request = new Request($this->request(files: $this->getFiles()));
		$request->file('myfile');
	}

	public function testFileInstanceNotAvailable(): void
	{
		$this->throws(OutOfBoundsException::class, "Invalid file key ['does-not-exist']");

		$request = new Request($this->request());
		$request->file('does-not-exist');
	}

	public function testFileInstanceNotAvailableTooMuchKeys(): void
	{
		$this->throws(
			OutOfBoundsException::class,
			"Invalid file key (too deep) ['nested']['myfile']['toomuch']",
		);

		$request = new Request($this->request());
		$request->file('nested', 'myfile', 'toomuch');
	}

	public function testAccessFileUsingMulitpleArrays(): void
	{
		$this->throws(RuntimeException::class, 'Either provide');

		$request = new Request($this->request());
		$request->files([], []);
	}

	public function testNestedFileInstanceNotAvailable(): void
	{
		$this->throws(OutOfBoundsException::class, "Invalid file key ['does-not-exist']['really']");

		$request = new Request($this->request());
		$request->file('does-not-exist', 'really');
	}

	public function testFileInstancesAreNotAvailable(): void
	{
		$this->throws(OutOfBoundsException::class, "Invalid files key ['does-not-exist']");

		$request = new Request($this->request());
		$request->files('does-not-exist');
	}

	public function testNestedFileInstancesAreNotAvailable(): void
	{
		$this->throws(OutOfBoundsException::class, "Invalid files key ['does-not-exist']['really']");

		$request = new Request($this->request());
		$request->files('does-not-exist', 'really');
	}
}
