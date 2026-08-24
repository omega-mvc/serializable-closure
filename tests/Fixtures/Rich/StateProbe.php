<?php

/**
 * Part of Omega - Serializable Closure Package.
 * php version 8.4
 *
 * Fixture file for ReflectionClosure::fetchItems() state coverage. The
 * top-level statements below are intentional scanner fodder (T_NEW,
 * T_DOUBLE_COLON and T_OBJECT_OPERATOR in the start state) and are never
 * executed: the returned closure is the only exported behaviour.
 *
 * @link        https://omega-mvc.github.io
 */

declare(strict_types=1);

namespace Tests\Fixtures\Rich;

use ArrayObject;
use Tests\Fixtures\{Suit, UntypedHolder};

use function reset as rewind_first;

use const E_ALL as ALL_ERRORS;

$probeObject = new ArrayObject([]);
$probeClass = UntypedHolder::class;
$probeCall = (new ArrayObject([]))->count();
$probeStatic = Suit::class;

function rich_state_closure(): Closure
{
    $captured = new ArrayObject(['state']);

    return function () use ($captured): array {
        return [$captured->count(), rewind_first((array) $captured), ALL_ERRORS];
    };
}
