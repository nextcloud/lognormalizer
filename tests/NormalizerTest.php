<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2016-2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2015 Olivier Paroz <dev-lognormalizer@interfasys.ch>
 * SPDX-FileCopyrightText: 2014-2015 Jordi Boggiano <j.boggiano@seld.be>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\LogNormalizer;

use Exception;
use PHPUnit\Framework\TestCase;
use TypeError;
use function get_class;

class NormalizerTest extends TestCase {
	protected Normalizer $normalizer;

	#[\Override]
	protected function setUp(): void {
		$this->normalizer = new Normalizer();
	}

	/**
	 * Using format() directly to make sure it doesn't modify strings
	 */
	public function testString(): void {
		$data = "Don't underestimate the power of the string [+*%&]";
		$formatted = $this->normalizer->format($data);

		self::assertEquals("Don't underestimate the power of the string [+*%&]", $formatted);
	}

	public function testEnumString(): void {
		$data = TestEnumString::BAR;
		$formatted = $this->normalizer->format($data);

		self::assertEquals('bar', $formatted);
	}

	public function testBoolean(): void {
		$data = true;
		$normalized = $this->normalizer->normalize($data);

		self::assertTrue($normalized);

		$formatted = $this->normalizer->convertToString($normalized);

		self::assertEquals('true', $formatted);
	}

	public function testEnumInt(): void {
		$data = TestEnum::BAR;
		$formatted = $this->normalizer->format($data);

		self::assertEquals(2, $formatted);
	}

	public function testFloat(): void {
		$data = 3.14413;
		$normalized = $this->normalizer->normalize($data);
		self::assertIsFloat($normalized);

		$formatted = $this->normalizer->convertToString($normalized);

		self::assertIsString($formatted);
		self::assertEquals(3.14413, $formatted);
	}

	public function testInfinity(): void {
		$data = [
			'inf' => INF,
			'-inf' => -INF,
		];
		$normalized = $this->normalizer->normalize($data);

		self::assertEquals($data, $normalized);

		$formatted = $this->normalizer->convertToString($normalized);

		self::assertEquals('{"inf":"INF","-inf":"-INF"}', $formatted);
	}

	public function testNan(): void {
		$data = acos(4);
		$normalized = $this->normalizer->normalize($data);

		self::assertEquals('NaN', $normalized);
	}

	public function testLongArray(): void {
		$keys = range(0, 25);
		$data = array_fill_keys($keys, 'normalizer');

		$normalizer = new Normalizer(4, 20);
		$normalized = $normalizer->normalize($data);

		$expectedResult = array_slice($data, 0, 19);
		$expectedResult['...'] = 'Over 20 items, aborting normalization';

		self::assertEquals($expectedResult, $normalized);
	}

	public function testArrayWithMixed(): void {
		$data = [
			'foo' => 123,
			'baz' => [
				'quz',
				true
			]
		];

		$normalized = $this->normalizer->normalize($data);

		$expectedResult = [
			'foo' => 123,
			'baz' => [
				'quz',
				true
			]
		];
		self::assertEquals($expectedResult, $normalized);

		$formatted = $this->normalizer->convertToString($normalized);
		$expectedString = '{"foo":123,"baz":["quz",true]}';

		self::assertEquals($expectedString, $formatted);
	}

	public function testDate(): void {
		$normalizer = new Normalizer(2, 20, 'Y-m-d');
		$data = new \DateTime();
		$normalized = $normalizer->normalize($data);

		self::assertEquals(date('Y-m-d'), $normalized);
	}

	public function testDateImmutable(): void {
		$normalizer = new Normalizer(2, 20, 'Y-m-d');
		$data = new \DateTimeImmutable();
		$normalized = $normalizer->normalize($data);

		self::assertEquals(date('Y-m-d'), $normalized);
	}

	public function testResource(): void {
		$data = fopen('php://memory', 'rb');
		$resourceId = (int)$data;
		$normalized = $this->normalizer->normalize($data);

		self::assertEquals('[resource] Resource id #' . $resourceId, $normalized);
	}

	public function testFormatExceptions(): void {
		$e = new \LogicException('bar');
		$e2 = new \RuntimeException('foo', 0, $e);
		$data = [
			'exception' => $e2,
		];
		$normalized = $this->normalizer->normalize($data);

		self::assertTrue(isset($normalized['exception']['previous']));
		unset($normalized['exception']['previous']);

		self::assertEquals(
			[
				'exception' => [
					'class' => get_class($e2),
					'message' => $e2->getMessage(),
					'code' => $e2->getCode(),
					'file' => $e2->getFile() . ':' . $e2->getLine(),
					'trace' => $e->getTraceAsString(),
				]
			], $normalized
		);
	}

	public function testFormatExceptionWithPreviousThrowable(): void {
		$t = new TypeError('not a type error');
		$e = new Exception('an exception', 13, $t);

		$normalized = $this->normalizer->normalize([
			'exception' => $e,
		]);

		self::assertEquals(
			[
				'exception' => [
					'class' => get_class($e),
					'message' => $e->getMessage(),
					'code' => $e->getCode(),
					'file' => $e->getFile() . ':' . $e->getLine(),
					'trace' => $e->getTraceAsString(),
					'previous' => [
						'class' => 'TypeError',
						'message' => 'not a type error',
						'code' => 0,
						'file' => $t->getFile() . ':' . $t->getLine(),
						'trace' => $t->getTraceAsString(),
					]
				]
			], $normalized
		);
		self::assertTrue(isset($normalized['exception']['previous']));
	}

	public function testFormatExceptionWithPreviousTruncated(): void {
		$e = new TypeError('not a type error');
		for ($i = 1; $i <= 5; $i++) {
			$e = new Exception('an exception', $i, $e);
		}

		$normalized = $this->normalizer->normalize([
			'exception' => $e,
		]);

		self::assertEquals(
			[
				'exception' => [
					'class' => get_class($e),
					'message' => $e->getMessage(),
					'code' => 5,
					'file' => $e->getFile() . ':' . $e->getLine(),
					'trace' => $e->getTraceAsString(),
					'previous' => [
						'class' => get_class($e),
						'message' => $e->getMessage(),
						'code' => 4,
						'file' => $e->getFile() . ':' . $e->getLine(),
						'trace' => $e->getTraceAsString(),
						'previous' => [
							'class' => get_class($e),
							'message' => $e->getMessage(),
							'code' => 3,
							'file' => $e->getFile() . ':' . $e->getLine(),
							'trace' => $e->getTraceAsString(),
							'previous' => [
								'class' => get_class($e),
								'message' => $e->getMessage(),
								'code' => 2,
								'file' => $e->getFile() . ':' . $e->getLine(),
								'trace' => $e->getTraceAsString(),
								'previous' => '[…]',
							],
						],
					],
				]
			],
			$normalized
		);
		self::assertTrue(isset($normalized['exception']['previous']));
	}

	public function testUnknown(): void {
		$data = fopen('php://memory', 'rb');
		fclose($data);
		$normalized = $this->normalizer->normalize($data);

		self::assertEquals('[unknown(' . gettype($data) . ')]', $normalized);
	}

	public function testFormatBrokenJSON(): void {
		$data = ['payload' => ['bad' => "\xC3\x28", 'other' => 'data']];
		$normalized = $this->normalizer->format($data);
		self::assertIsString($normalized);
		self::assertSame('{"payload":{"bad":null,"other":"data"}}', $normalized);
	}
}

class TestFooNorm {
	public $foo = 'foo';
}

enum TestEnum: int {
	case FOO = 1;
	case BAR = 2;
}

enum TestEnumString: string {
	case FOO = 'foo';
	case BAR = 'bar';
}

class TestBarNorm {
	public $foo;
	public $bar = 'bar';

	public function __construct() {
		$this->foo = new TestFooNorm();
	}
}

class TestBazNorm {
	public $foo;
	public $bar;

	public $baz = 'baz';

	public function __construct() {
		$this->foo = new TestFooNorm();
		$this->bar = new TestBarNorm();
	}
}

class TestEmbeddedObjects {
	public $foo;
	public $bar;
	public $baz;
	public $fooBar;

	public function __construct() {
		$this->foo = new TestFooNorm();
		$this->bar = new TestBarNorm();
		$this->baz = new TestBazNorm();
		$this->methodOne();
	}

	public function methodOne(): void {
		$this->fooBar = $this->foo->foo . $this->bar->bar;
	}
}
