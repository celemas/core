<?php

declare(strict_types=1);

namespace Celema\Core\Factory;

use Celema\Core\Exception\RuntimeException;

/** @api */
final class Discovery
{
	/** @var array<class-string<Factory>, non-empty-array<int, non-empty-string>> */
	private const CANDIDATES = [
		Nyholm::class => [
			'Nyholm\\Psr7\\Factory\\Psr17Factory',
			'Nyholm\\Psr7Server\\ServerRequestCreator',
		],
		Guzzle::class => [
			'GuzzleHttp\\Psr7\\HttpFactory',
		],
		Laminas::class => [
			'Laminas\\Diactoros\\RequestFactory',
		],
	];

	public static function create(): Factory
	{
		foreach (self::CANDIDATES as $factoryClass => $probeClasses) {
			foreach ($probeClasses as $probeClass) {
				if (!class_exists($probeClass)) {
					continue 2;
				}
			}

			return new $factoryClass();
		}

		throw new RuntimeException(
			'No supported PSR-7 implementation found. Install one of: '
			. 'nyholm/psr7 with nyholm/psr7-server, guzzlehttp/psr7, or laminas/laminas-diactoros',
		);
	}
}
