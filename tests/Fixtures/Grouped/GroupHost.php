<?php

/**
 * Part of Omega - Serializable Closure Package.
 * php version 8.4
 *
 * Fixture class providing closures that exercise fetchItems() grouped imports.
 *
 * @link        https://omega-mvc.github.io
 */

declare(strict_types=1);

namespace Tests\Fixtures\Grouped;

use ArrayObject;
use Closure;
use Tests\Fixtures\AttributeHost as AH;
use Tests\Fixtures\MethodHost as MH;
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

use function Tests\Fixtures\tokenizerNamedFunction as tnf;
use function strtolower as sl;

use const PHP_VERSION as PHPVER;
use const PHP_MAJOR_VERSION as MAJVER;

/** Interface definition — exercises the interface structure path. */
interface GroupInterface
{
    public function groupMethod(): void;
}

/** Trait definition — exercises the trait structure path. */
trait GroupTrait
{
    public string $traitProp = 'trait';
}

/** Enum definition — exercises the enum structure path. */
enum GroupSuit
{
    case Hearts;
    case Diamonds;
}

/** Class definition — exercises the class structure path. */
class GroupHost implements GroupInterface
{
    use Colorable;
    use GroupTrait;

    public function groupMethod(): void
    {
    }

    /**
     * Returns a closure that exercises all the fetchItems() import paths.
     */
    public function sink(): Closure
    {
        $local = new ArrayObject([]);

        return function () use ($local): string {
            $x = new AH();
            $y = new MH();
            $z = new UserDefinedFixture();
            $fn = tnf();
            $upper = sl('hello');
            $keys = ak((array) $local);
            $length = cnt((array) $local);
            $size = slen('test');
            $values = av([1, 2]);
            $ver = PHPVER;
            $major = MAJVER;

            return $fn . $upper . $size . $ver . $major;
        };
    }
}

/** Second class — exercises the class structure path again. */
class GroupSecondary
{
    public string $secondary = 'secondary';
}

// File-level constructs — exercises the new/invoke paths in fetchItems().
if (false) {
    $globalObj = new GroupHost();
    $globalObj->groupMethod();
}
