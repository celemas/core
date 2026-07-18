<?php

declare(strict_types=1);

namespace Celema\Core\Tests;

use Celema\Core\Exception\RuntimeException;
use Celema\Core\Factory\Discovery;
use Celema\Core\Factory\Factory;
use Celema\Core\Factory\Nyholm;
use Celema\Core\Tests\Fixtures\DiscoveryGuzzle;
use Celema\Core\Tests\Fixtures\DiscoveryLaminas;
use Celema\Core\Tests\Fixtures\DiscoveryProbe;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class FactoryDiscoveryTest extends TestCase
{
	public function testDiscoveryReturnsPreferredInstalledFactory(): void
	{
		$first = Discovery::create();
		$second = Discovery::create();

		$this->assertInstanceOf(Factory::class, $first);
		$this->assertInstanceOf(Nyholm::class, $first);
		$this->assertNotSame($first, $second);
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function testDiscoverySkipsIncompleteNyholmInstall(): void
	{
		$autoloaders = $this->unregisterAutoloaders();
		$class = null;

		try {
			$this->loadDiscoveryFiles();
			$this->loadDiscoveryFixtures();

			class_alias(DiscoveryProbe::class, 'Nyholm\\Psr7\\Factory\\Psr17Factory');
			class_alias(DiscoveryProbe::class, 'GuzzleHttp\\Psr7\\HttpFactory');
			class_alias(DiscoveryGuzzle::class, 'Celema\\Core\\Factory\\Guzzle');

			$class = Discovery::create()::class;
		} finally {
			$this->registerAutoloaders($autoloaders);
		}

		$this->assertSame(DiscoveryGuzzle::class, $class);
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function testDiscoveryFallsBackToLaminasInstall(): void
	{
		$autoloaders = $this->unregisterAutoloaders();
		$class = null;

		try {
			$this->loadDiscoveryFiles();
			$this->loadDiscoveryFixtures();

			class_alias(DiscoveryProbe::class, 'Laminas\\Diactoros\\RequestFactory');
			class_alias(DiscoveryLaminas::class, 'Celema\\Core\\Factory\\Laminas');

			$class = Discovery::create()::class;
		} finally {
			$this->registerAutoloaders($autoloaders);
		}

		$this->assertSame(DiscoveryLaminas::class, $class);
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function testDiscoveryFailsWithoutSupportedFactory(): void
	{
		$autoloaders = $this->unregisterAutoloaders();
		$exceptionClass = null;
		$message = null;

		try {
			$this->loadDiscoveryFiles();

			try {
				Discovery::create();
			} catch (RuntimeException $exception) {
				$exceptionClass = $exception::class;
				$message = $exception->getMessage();
			}
		} finally {
			$this->registerAutoloaders($autoloaders);
		}

		$this->assertSame(RuntimeException::class, $exceptionClass);
		$this->assertStringContainsString('No supported PSR-7 implementation found.', (string) $message);
		$this->assertStringContainsString('nyholm/psr7 with nyholm/psr7-server', (string) $message);
	}

	private function loadDiscoveryFiles(): void
	{
		$root = dirname(__DIR__);

		require_once $root . '/src/Exception/CoreException.php';
		require_once $root . '/src/Exception/RuntimeException.php';
		require_once $root . '/src/Factory/Factory.php';
		require_once $root . '/src/Factory/AbstractFactory.php';
		require_once $root . '/src/Factory/Discovery.php';
	}

	private function loadDiscoveryFixtures(): void
	{
		$root = dirname(__DIR__);

		require_once $root . '/tests/Fixtures/DiscoveryProbe.php';
		require_once $root . '/tests/Fixtures/DiscoveryGuzzle.php';
		require_once $root . '/tests/Fixtures/DiscoveryLaminas.php';
	}

	private function unregisterAutoloaders(): array
	{
		$autoloaders = spl_autoload_functions() ?: [];

		foreach ($autoloaders as $autoload) {
			spl_autoload_unregister($autoload);
		}

		return $autoloaders;
	}

	private function registerAutoloaders(array $autoloaders): void
	{
		foreach ($autoloaders as $autoload) {
			spl_autoload_register($autoload);
		}
	}
}
