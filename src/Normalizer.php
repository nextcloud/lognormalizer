<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2016-2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2015 Olivier Paroz <dev-lognormalizer@interfasys.ch>
 * SPDX-FileCopyrightText: 2014-2015 Jordi Boggiano <j.boggiano@seld.be>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\LogNormalizer;

use Throwable;
use function is_float;
use function is_scalar;

/**
 * Converts any variable to a string
 *
 * @package Nextcloud\LogNormalizer
 * @api
 */
class Normalizer {

	/**
	 * @type string
	 */
	private const SIMPLE_DATE = 'Y-m-d H:i:s';

	/**
	 * @var int
	 */
	private $maxRecursionDepth;

	/**
	 * @var int
	 */
	private $maxArrayItems;

	/**
	 * @var string
	 */
	private $dateFormat;

	/**
	 * @param int $maxRecursionDepth The maximum depth at which to go when inspecting objects
	 * @param int $maxArrayItems The maximum number of Array elements you want to show, when
	 *                           parsing an array
	 * @param null|string $dateFormat The format to apply to dates
	 */
	public function __construct(int $maxRecursionDepth = 4, int $maxArrayItems = 20, ?string $dateFormat = null) {
		$this->maxRecursionDepth = $maxRecursionDepth;
		$this->maxArrayItems = $maxArrayItems;
		if ($dateFormat !== null) {
			$this->dateFormat = $dateFormat;
		} else {
			$this->dateFormat = static::SIMPLE_DATE;
		}
	}

	/**
	 * Normalizes the variable, JSON encodes it if needed and cleans up the result
	 *
	 * @param mixed $data
	 * @return string|null
	 */
	#[\NoDiscard]
	public function format($data): ?string {
		$data = $this->normalize($data);
		return $this->convertToString($data);
	}

	/**
	 * Converts Arrays, Dates and Exceptions to a string or an Array
	 *
	 * @param mixed $data
	 * @param ?int $depth
	 *
	 * @return string|array
	 */
	public function normalize($data, ?int $depth = 0) {
		$depth = $depth ?? 0;
		$scalar = $this->normalizeScalar($data);
		if ($scalar !== null) {
			return $scalar;
		}

		if ($data instanceof \Throwable) {
			return $this->normalizeException($data, $depth);
		}

		if ($data instanceof \DateTimeInterface) {
			return $data->format($this->dateFormat);
		}

		if (is_resource($data)) {
			return '[resource] ' . substr((string)$data, 0, 40);
		}

		if (is_iterable($data)) {
			return $this->normalizeTraversableElement($data, $depth);
		}

		return '[unknown(' . gettype($data) . ')]';
	}

	/**
	 * JSON encodes data which isn't already a string and cleans up the result
	 *
	 * @todo: could maybe do a better job removing slashes
	 *
	 * @param mixed $data
	 *
	 * @return string|null
	 */
	public function convertToString($data): ?string {
		if (!is_string($data)) {
			$data = @json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
			// Removing null byte and double slashes from object properties
			$data = str_replace(['\\u0000', '\\\\'], ['', '\\'], $data);
		}

		return $data;
	}

	/**
	 * Returns various, filtered, scalar elements
	 *
	 * We're returning an array here to detect failure because null is a scalar and so is false
	 *
	 * @param mixed $data
	 *
	 * @return string|mixed|null
	 */
	private function normalizeScalar($data) {
		if ($data === null) {
			return null;
		}

		if (is_float($data)) {
			return $this->normalizeFloat($data);
		}

		if (is_scalar($data)) {
			return $data;
		}

		return null;
	}

	/**
	 * Normalizes infinite and trigonometric floats
	 *
	 * @param float $data
	 *
	 * @return string|float
	 */
	private function normalizeFloat(float $data) {
		if (is_infinite($data)) {
			$postfix = 'INF';
			if ($data < 0) {
				$postfix = '-' . $postfix;
			}
			return $postfix;
		}

		if (is_nan($data)) {
			return 'NaN';
		}

		return $data;
	}

	/**
	 * Converts each element of a traversable variable to String
	 *
	 * @param mixed $data
	 * @param int $depth
	 *
	 * @return array
	 */
	private function normalizeTraversableElement($data, int $depth): array {
		$maxObjectRecursion = $this->maxRecursionDepth;
		$maxArrayItems = $this->maxArrayItems;
		$count = 1;
		$normalized = [];
		$nextDepth = $depth + 1;
		foreach ($data as $key => $value) {
			if ($count >= $maxArrayItems) {
				$normalized['...']
					= 'Over ' . $maxArrayItems . ' items, aborting normalization';
				break;
			}
			$count++;
			if ($depth < $maxObjectRecursion) {
				$normalized[$key] = $this->normalize($value, $nextDepth);
			}
		}

		return $normalized;
	}

	/**
	 * Converts an Exception to a string array
	 *
	 * @param Throwable $exception
	 *
	 * @return mixed[]
	 */
	private function normalizeException(Throwable $exception, int $depth): array {
		$data = [
			'class' => get_class($exception),
			'message' => $exception->getMessage(),
			'code' => $exception->getCode(),
			'file' => $exception->getFile() . ':' . $exception->getLine(),
		];
		$trace = $exception->getTraceAsString();
		$data['trace'] = $trace;

		$previous = $exception->getPrevious();
		if ($previous) {
			if ($depth < $this->maxRecursionDepth) {
				$data['previous'] = $this->normalizeException($previous, $depth + 1);
			} else {
				$data['previous'] = '[…]';
			}
		}

		return $data;
	}
}
