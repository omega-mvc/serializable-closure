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

namespace Tests\Unit\Support;

use Omega\SerializableClosure\Support\ClosureScope;
use stdClass;
use Tests\TestCase;

final class ClosureScopeTest extends TestCase
{
    public function testAFreshScopeStartsWithZeroedCounters(): void
    {
        $scope = new ClosureScope();

        $this->assertSame(0, $scope->serializations);
        $this->assertSame(0, $scope->toSerialize);
    }

    public function testBeginAndFinishKeepTheCountersBalanced(): void
    {
        $scope = new ClosureScope();
        ++$scope->toSerialize;

        $scope->beginSerialization();
        $this->assertSame(1, $scope->serializations);

        $this->assertTrue($scope->finishSerialization());
        $this->assertSame(0, $scope->serializations);
        $this->assertSame(0, $scope->toSerialize);
    }

    public function testFinishReportsFalseWhileSerializationsArePending(): void
    {
        $scope = new ClosureScope();
        ++$scope->toSerialize;
        ++$scope->toSerialize;

        $scope->beginSerialization();
        $this->assertFalse($scope->finishSerialization());
        $this->assertSame(0, $scope->serializations);
        $this->assertSame(1, $scope->toSerialize);
    }

    public function testTheScopeBehavesAsAnObjectStorage(): void
    {
        $scope  = new ClosureScope();
        $first  = new stdClass();
        $second = new stdClass();

        $scope[$first] = 'a';
        $scope[$second] = 'b';

        $this->assertSame(2, $scope->count());
        $this->assertSame('a', $scope[$first]);
        $this->assertTrue(isset($scope[$second]));
    }
}
