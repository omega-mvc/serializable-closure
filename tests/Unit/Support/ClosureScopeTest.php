<?php

/**
 * Part of Omega - Serializable Closure Package.
 * php version 8.4
 *
 * @link        https://omega-mvc.github.io
 * @author      Adriano Giovannini <agisoftt@gmail.com>
 * @copyright   Copyright (c) 2024 - 2025 Adriano Giovannini
 * @license     https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version     1.0.0
 */

declare(strict_types=1);

use Omega\SerializableClosure\Support\ClosureScope;

test('a fresh scope starts with zeroed counters', function () {
    $scope = new ClosureScope();

    expect($scope->serializations)->toBe(0)
        ->and($scope->toSerialize)->toBe(0);
});

test('begin and finish keep the counters balanced', function () {
    $scope = new ClosureScope();
    ++$scope->toSerialize;

    $scope->beginSerialization();
    expect($scope->serializations)->toBe(1);

    expect($scope->finishSerialization())->toBeTrue()
        ->and($scope->serializations)->toBe(0)
        ->and($scope->toSerialize)->toBe(0);
});

test('finish reports false while serializations are pending', function () {
    $scope = new ClosureScope();
    ++$scope->toSerialize;
    ++$scope->toSerialize;

    $scope->beginSerialization();
    expect($scope->finishSerialization())->toBeFalse()
        ->and($scope->serializations)->toBe(0)
        ->and($scope->toSerialize)->toBe(1);
});

test('the scope behaves as an object storage', function () {
    $scope  = new ClosureScope();
    $first  = new stdClass();
    $second = new stdClass();

    $scope[$first] = 'a';
    $scope[$second] = 'b';

    expect($scope->count())->toBe(2)
        ->and($scope[$first])->toBe('a')
        ->and(isset($scope[$second]))->toBeTrue();
});
