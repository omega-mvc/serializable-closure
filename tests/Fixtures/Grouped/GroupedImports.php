<?php

/**
 * Part of Omega - Serializable Closure Package.
 * php version 8.4
 *
 * Fixture file for exercising fetchItems() scanning at file level.
 * Contains grouped use statements, multiple structure types, and
 * file-level new/invoke constructs.
 *
 * @link        https://omega-mvc.github.io
 */

declare(strict_types=1);

namespace Tests\Fixtures\Grouped;

use ArrayObject;
use Closure;
use Tests\Fixtures\AttributeHost;
use Tests\Fixtures\MethodHost;
use Tests\Fixtures\UserDefinedFixture;
use Tests\Fixtures\Colorable;
use Tests\Fixtures\Wearable;
use Tests\Fixtures\ParentFixture;
use DateTimeImmutable as DT;
use DateTimeInterface as DTI;
use function array_keys as ak;
use function count as cnt;
use function strlen as slen;
use function array_values as av;
use const PHP_EOL as EOL;
use const PHP_INT_SIZE as INTSIZE;
use const PHP_FLOAT_DIG as FLOATDIG;

use Tests\Fixtures\AttributeHost as AH;
use Tests\Fixtures\MethodHost as MH;

use function Tests\Fixtures\tokenizerNamedFunction as tnf;
use function strtolower as sl;

use const PHP_VERSION as PHPVER;
use const PHP_MAJOR_VERSION as MAJVER;
