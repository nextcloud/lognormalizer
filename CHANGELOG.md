<!--
  - SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
  - SPDX-FileCopyrightText: 2015 Olivier Paroz <dev-lognormalizer@interfasys.ch>
  - SPDX-License-Identifier: CC0-1.0
-->
# Changelog
All notable changes to this project will be documented in this file.

## 3.0.1 – 2026-03-12
* Filter invalid characters when not json encoding [#28](https://github.com/nextcloud/lognormalizer/pull/28)

## 3.0.0 – 2025-12-17
* Support for PHP 8.5 added which required to remove support for generic objects
* Support for PHP 8.0 and older has been dropped to allow supporting enums
* ⚠️ Breaking: Normalizer::format() no longer supports generic Objects [#24](https://github.com/nextcloud/lognormalizer/pull/24)
* Backed enums of integers and strings are now supported [#24](https://github.com/nextcloud/lognormalizer/pull/24)

## 2.0.0 – 2025-10-24
* ⚠️ Breaking: Normalizer::format() no longer modifies the data by reference [#16](https://github.com/nextcloud/lognormalizer/pull/16)
* Improve normalizer to also output partial json [#18](https://github.com/nextcloud/lognormalizer/pull/18)
* Add support for DateTimeInterface as data type [#10](https://github.com/nextcloud/lognormalizer/pull/10)

## 1.0.0 – 2020-12-02
* First release
