<?php

/**
 * Part of Omega - Serializable Closure Package.
 * php version 8.3
 *
 * @link        https://omega-mvc.github.io
 * @author      Adriano Giovannini <agisoftt@gmail.com>
 * @copyright   Copyright (c) 2024 - 2025 Adriano Giovannini
 * @license     https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version     1.0.0
 */

declare(strict_types=1);

namespace Omega\SerializableClosure\Support;

defined('T_NAME_QUALIFIED')           || define('T_NAME_QUALIFIED', -4);
defined('T_NAME_FULLY_QUALIFIED')     || define('T_NAME_FULLY_QUALIFIED', -5);
defined('T_FN')                       || define('T_FN', -6);
defined('T_NULLSAFE_OBJECT_OPERATOR') || define('T_NULLSAFE_OBJECT_OPERATOR', -7);

use Closure;
use ReflectionException;
use ReflectionFunction;

use function array_intersect_key;
use function array_keys;
use function array_map;
use function array_merge;
use function array_shift;
use function count;
use function date;
use function defined;
use function dirname;
use function end;
use function explode;
use function file_get_contents;
use function function_exists;
use function implode;
use function in_array;
use function is_array;
use function is_null;
use function is_string;
use function sprintf;
use function str_contains;
use function str_replace;
use function strtolower;
use function time;
use function token_get_all;
use function trim;
use function var_export;

/**
 * Reflection closure class.
 *
 * The `ReflectionClosure` class extends `ReflectionFunction` and provides additional functionality
 * for analyzing closures. It includes methods to check if a closure is "static" or a "short closure"
 * and retrieves the closure's code. The class also extracts information about files, classes, functions,
 * constants, and structures used within the closure.
 *
 * @category    Omega
 * @package     SerializableClosure
 * @subpackage  Support
 * @link        https://omega-mvc.github.io
 * @author      Adriano Giovannini <agisoftt@gmail.com>
 * @copyright   Copyright (c) 2024 - 2025 Adriano Giovannini
 * @license     https://www.gnu.org/licenses/gpl-3.0-standalone.html     GPL V3.0+
 * @version     1.0.0
 */
class ReflectionClosure extends ReflectionFunction
{
    /**
     * The code of the closure.
     *
     * @var string|null Holds the code of the closure or null.
     */
    protected ?string $code = null;

    /**
     * The token extracted from the closure's code.
     *
     * @var array|null Holds the token extracted from the closure's code or null.
     */
    protected ?array $tokens = null;

    /**
     * Hashed name of the closure.
     *
     * @var string|null Holds the hashed name of the closure or null.
     */
    protected ?string $hashedName = null;

    /**
     * Array of variables used in the closure.
     *
     * @var array|null Holds an array of variables used in the closure or null.
     */
    protected ?array $useVariables = null;

    /**
     * Indicates whether the closure is static or not.
     *
     * @var bool Indicates whether the closure is static or not .
     */
    protected bool $isStaticClosure;

    /**
     * Indicates whether the closure requires scope.
     *
     * @var bool Indicates whether the closure requires scope.
     */
    protected bool $isScopeRequired;

    /**
     * Indicates whether the closure requires binding.
     *
     * @var bool Indicates whether the closure requires binding.
     */
    protected bool $isBindingRequired = false;

    /**
     * Indicates whether the closure is a short closure or not.
     *
     * @var bool Indicates whether the closure is a short closure or not.
     */
    protected bool $isShortClosure;

    /**
     * Related information array.
     *
     * @var array Holds an array of related information.
     */
    protected static array $files = [];

    /**
     * Class related information array.
     *
     * @var array Holds an array of class-related information.
     */
    protected static array $classes = [];

    /**
     * Functions related information array.
     *
     * @var array Holds an array of functions-related information.
     */
    protected static array $functions = [];

    /**
     * Constants related information array.
     *
     * @var array Holds an array of constants-related information.
     */
    protected static array $constants = [];

    /**
     * Structures related information array.
     *
     * @var array Holds an array of structures-related information.
     */
    protected static array $structures = [];

    /**
     * Creates a new reflection closure instance.
     *
     * @param Closure     $closure Holds the current reflection closure instance.
     * @param string|null $code    Holds the code of the closure.
     * @return void
     * @throws ReflectionException
     */
    public function __construct(Closure $closure, ?string $code = null)
    {
        parent::__construct($closure);
    }

    /**
     * Checks if the closure is "static".
     *
     * @return bool Return true if the closure is static, false if not.
     */
    public function isStatic(): bool
    {
        if ($this->isStaticClosure === null) {
            $this->isStaticClosure = strtolower(substr($this->getCode(), 0, 6)) === 'static';
        }

        return $this->isStaticClosure;
    }

    /**
     * Checks if the closure is a "short closure".
     *
     * @return bool Return true if the closure is short closure, false if not.
     */
    public function isShortClosure(): bool
    {
        if ($this->isShortClosure === null) {
            $code = $this->getCode();

            if ($this->isStatic()) {
                $code = substr($code, 6);
            }

            $this->isShortClosure = strtolower(substr(trim($code), 0, 2)) === 'fn';
        }

        return $this->isShortClosure;
    }

    /**
     * Get the closure's code.
     *
     * @return string Return the current closure code.
     */
    public function getCode(): string
    {
        if ($this->code !== null) {
            return $this->code;
        }

        $fileName = $this->getFileName();
        $line     = $this->getStartLine() - 1;

        $className = null;

        if (null !== $className = $this->getClosureScopeClass()) {
            $className = '\\' . trim($className->getName(), '\\');
        }

        $builtin_types  = self::getBuiltinTypes();
        $class_keywords = ['self', 'static', 'parent'];

        $ns  = $this->getClosureNamespaceName();
        $nsf = $ns == '' ? '' : ($ns[0] == '\\' ? $ns : '\\' . $ns);

        $_file      = var_export($fileName, true);
        $_dir       = var_export(dirname($fileName), true);
        $_namespace = var_export($ns, true);
        $_class     = var_export(trim($className ?: '', '\\'), true);
        $_function  = $ns . ($ns == '' ? '' : '\\') . '{closure}';
        $_method    = ($className == '' ? '' : trim($className, '\\') . '::') . $_function;
        $_function  = var_export($_function, true);
        $_method    = var_export($_method, true);
        $_trait     = null;

        $tokens               = $this->getTokens();
        $state                = $lastState = 'start';
        $inside_structure     = false;
        $isFirstClassCallable = false;
        $isShortClosure       = false;

        $inside_structure_mark = 0;
        $open                  = 0;
        $code                  = '';
        $id_start              = $id_start_ci = $id_name = $context = '';
        $classes               = $functions = $constants = null;
        $use                   = [];
        $lineAdd               = 0;
        $isUsingScope          = false;
        $isUsingThisObject     = false;

        for ($i = 0, $l = count($tokens); $i < $l; ++$i) {
            $token = $tokens[$i];

            switch ($state) {
                case 'start':
                    if ($token[0] === T_FUNCTION || $token[0] === T_STATIC) {
                        $code .= $token[1];

                        $state = $token[0] === T_FUNCTION ? 'function' : 'static';
                    } elseif ($token[0] === T_FN) {
                        $isShortClosure = true;
                        $code .= $token[1];
                        $state = 'closure_args';
                    } elseif ($token[0] === T_PUBLIC || $token[0] === T_PROTECTED || $token[0] === T_PRIVATE) {
                        $code                 = '';
                        $isFirstClassCallable = true;
                    }

                    break;
                case 'static':
                    if ($token[0] === T_WHITESPACE || $token[0] === T_COMMENT || $token[0] === T_FUNCTION) {
                        $code .= $token[1];
                        if ($token[0] === T_FUNCTION) {
                            $state = 'function';
                        }
                    } elseif ($token[0] === T_FN) {
                        $isShortClosure = true;
                        $code .= $token[1];
                        $state = 'closure_args';
                    } else {
                        $code  = '';
                        $state = 'start';
                    }

                    break;
                case 'function':
                    switch ($token[0]) {
                        case T_STRING:
                            if ($isFirstClassCallable) {
                                $state = 'closure_args';

                                break;
                            }

                            $code  = '';
                            $state = 'named_function';

                            break;
                        case '(':
                            $code .= '(';
                            $state = 'closure_args';

                            break;
                        default:
                            $code .= is_array($token) ? $token[1] : $token;
                    }

                    break;
                case 'named_function':
                    if ($token[0] === T_FUNCTION || $token[0] === T_STATIC) {
                        $code  = $token[1];
                        $state = $token[0] === T_FUNCTION ? 'function' : 'static';
                    } elseif ($token[0] === T_FN) {
                        $isShortClosure = true;
                        $code .= $token[1];
                        $state = 'closure_args';
                    }

                    break;
                case 'closure_args':
                    switch ($token[0]) {
                        case T_NAME_QUALIFIED:
                            [$id_start, $id_start_ci, $id_name] = $this->parseNameQualified($token[1]);
                            $context                            = 'args';
                            $state                              = 'id_name';
                            $lastState                          = 'closure_args';

                            break;
                        case T_NS_SEPARATOR:
                        case T_STRING:
                            $id_start    = $token[1];
                            $id_start_ci = strtolower($id_start);
                            $id_name     = '';
                            $context     = 'args';
                            $state       = 'id_name';
                            $lastState   = 'closure_args';

                            break;
                        case T_USE:
                            $code .= $token[1];
                            $state = 'use';

                            break;
                        case T_DOUBLE_ARROW:
                            $code .= $token[1];
                            if ($isShortClosure) {
                                $state = 'closure';
                            }

                            break;
                        case ':':
                            $code .= ':';
                            $state = 'return';

                            break;
                        case '{':
                            $code .= '{';
                            $state = 'closure';
                            ++$open;

                            break;
                        default:
                            $code .= is_array($token) ? $token[1] : $token;
                    }

                    break;
                case 'use':
                    switch ($token[0]) {
                        case T_VARIABLE:
                            $use[] = substr($token[1], 1);
                            $code .= $token[1];

                            break;
                        case '{':
                            $code .= '{';
                            $state = 'closure';
                            ++$open;

                            break;
                        case ':':
                            $code .= ':';
                            $state = 'return';

                            break;
                        default:
                            $code .= is_array($token) ? $token[1] : $token;

                            break;
                    }

                    break;
                case 'return':
                    switch ($token[0]) {
                        case T_WHITESPACE:
                        case T_COMMENT:
                        case T_DOC_COMMENT:
                            $code .= $token[1];

                            break;
                        case T_NS_SEPARATOR:
                        case T_STRING:
                            $id_start    = $token[1];
                            $id_start_ci = strtolower($id_start);
                            $id_name     = '';
                            $context     = 'return_type';
                            $state       = 'id_name';
                            $lastState   = 'return';

                            break 2;
                        case T_NAME_QUALIFIED:
                            [$id_start, $id_start_ci, $id_name] = $this->parseNameQualified($token[1]);
                            $context                            = 'return_type';
                            $state                              = 'id_name';
                            $lastState                          = 'return';

                            break 2;
                        case T_DOUBLE_ARROW:
                            $code .= $token[1];
                            if ($isShortClosure) {
                                $state = 'closure';
                            }

                            break;
                        case '{':
                            $code .= '{';
                            $state = 'closure';
                            ++$open;

                            break;
                        default:
                            $code .= is_array($token) ? $token[1] : $token;

                            break;
                    }

                    break;
                case 'closure':
                    switch ($token[0]) {
                        case T_CURLY_OPEN:
                        case T_DOLLAR_OPEN_CURLY_BRACES:
                        case '{':
                            $code .= is_array($token) ? $token[1] : $token;
                            ++$open;

                            break;
                        case '}':
                            $code .= '}';
                            if (--$open === 0 && !$isShortClosure) {
                                break 3;
                            } elseif ($inside_structure) {
                                $inside_structure = !($open === $inside_structure_mark);
                            }

                            break;
                        case '(':
                        case '[':
                            $code .= $token[0];
                            if ($isShortClosure) {
                                ++$open;
                            }

                            break;
                        case ')':
                        case ']':
                            if ($isShortClosure) {
                                if ($open === 0) {
                                    break 3;
                                }
                                --$open;
                            }
                            $code .= $token[0];

                            break;
                        case ',':
                        case ';':
                            if ($isShortClosure && $open === 0) {
                                break 3;
                            }
                            $code .= $token[0];

                            break;
                        case T_LINE:
                            $code .= $token[2] - $line + $lineAdd;

                            break;
                        case T_FILE:
                            $code .= $_file;

                            break;
                        case T_DIR:
                            $code .= $_dir;

                            break;
                        case T_NS_C:
                            $code .= $_namespace;

                            break;
                        case T_CLASS_C:
                            $code .= $inside_structure ? $token[1] : $_class;

                            break;
                        case T_FUNC_C:
                            $code .= $inside_structure ? $token[1] : $_function;

                            break;
                        case T_METHOD_C:
                            $code .= $inside_structure ? $token[1] : $_method;

                            break;
                        case T_COMMENT:
                            if (str_starts_with($token[1], '#trackme')) {
                                $timestamp = time();
                                $code .= '/**' . PHP_EOL;
                                $code .= '* Date      : ' . date(DATE_W3C, $timestamp) . PHP_EOL;
                                $code .= '* Timestamp : ' . $timestamp . PHP_EOL;
                                $code .= '* Line      : ' . ($line + 1) . PHP_EOL;
                                $code .= '* File      : ' . $_file . PHP_EOL . '*/' . PHP_EOL;
                                $lineAdd += 5;
                            } else {
                                $code .= $token[1];
                            }

                            break;
                        case T_VARIABLE:
                            if ($token[1] == '$this' && !$inside_structure) {
                                $isUsingThisObject = true;
                            }
                            $code .= $token[1];

                            break;
                        case T_STATIC:
                        case T_NS_SEPARATOR:
                        case T_STRING:
                            $id_start    = $token[1];
                            $id_start_ci = strtolower($id_start);
                            $id_name     = '';
                            $context     = 'root';
                            $state       = 'id_name';
                            $lastState   = 'closure';

                            break 2;
                        case T_NAME_QUALIFIED:
                            [$id_start, $id_start_ci, $id_name] = $this->parseNameQualified($token[1]);
                            $context                            = 'root';
                            $state                              = 'id_name';
                            $lastState                          = 'closure';

                            break 2;
                        case T_NEW:
                            $code .= $token[1];
                            $context   = 'new';
                            $state     = 'id_start';
                            $lastState = 'closure';

                            break 2;
                        case T_USE:
                            $code .= $token[1];
                            $context   = 'use';
                            $state     = 'id_start';
                            $lastState = 'closure';

                            break;
                        case T_INSTANCEOF:
                        case T_INSTEADOF:
                            $code .= $token[1];
                            $context   = 'instanceof';
                            $state     = 'id_start';
                            $lastState = 'closure';

                            break;
                        case T_OBJECT_OPERATOR:
                        case T_NULLSAFE_OBJECT_OPERATOR:
                        case T_DOUBLE_COLON:
                            $code .= $token[1];
                            $lastState = 'closure';
                            $state     = 'ignore_next';

                            break;
                        case T_FUNCTION:
                            $code .= $token[1];
                            $state = 'closure_args';
                            if (!$inside_structure) {
                                $inside_structure      = true;
                                $inside_structure_mark = $open;
                            }

                            break;
                        case T_TRAIT_C:
                            if ($_trait === null) {
                                $startLine  = $this->getStartLine();
                                $endLine    = $this->getEndLine();
                                $structures = $this->getStructures();

                                $_trait = '';

                                foreach ($structures as &$struct) {
                                    if (
                                        $struct['type'] === 'trait'
                                        && $struct['start'] <= $startLine
                                        && $struct['end'] >= $endLine
                                    ) {
                                        $_trait = ($ns == '' ? '' : $ns . '\\') . $struct['name'];

                                        break;
                                    }
                                }

                                $_trait = var_export($_trait, true);
                            }

                            $code .= $_trait;

                            break;
                        default:
                            $code .= is_array($token) ? $token[1] : $token;
                    }

                    break;
                case 'ignore_next':
                    switch ($token[0]) {
                        case T_WHITESPACE:
                        case T_COMMENT:
                        case T_DOC_COMMENT:
                            $code .= $token[1];

                            break;
                        case T_CLASS:
                        case T_NEW:
                        case T_STATIC:
                        case T_VARIABLE:
                        case T_STRING:
                        case T_CLASS_C:
                        case T_FILE:
                        case T_DIR:
                        case T_METHOD_C:
                        case T_FUNC_C:
                        case T_FUNCTION:
                        case T_INSTANCEOF:
                        case T_LINE:
                        case T_NS_C:
                        case T_TRAIT_C:
                        case T_USE:
                            $code .= $token[1];
                            $state = $lastState;

                            break;
                        default:
                            $state = $lastState;
                            --$i;
                    }

                    break;
                case 'id_start':
                    switch ($token[0]) {
                        case T_WHITESPACE:
                        case T_COMMENT:
                        case T_DOC_COMMENT:
                            $code .= $token[1];

                            break;
                        case T_NS_SEPARATOR:
                        case T_NAME_FULLY_QUALIFIED:
                        case T_STRING:
                        case T_STATIC:
                            $id_start    = $token[1];
                            $id_start_ci = strtolower($id_start);
                            $id_name     = '';
                            $state       = 'id_name';

                            break 2;
                        case T_NAME_QUALIFIED:
                            [$id_start, $id_start_ci, $id_name] = $this->parseNameQualified($token[1]);
                            $state                              = 'id_name';

                            break 2;
                        case T_VARIABLE:
                            $code .= $token[1];
                            $state = $lastState;

                            break;
                        case T_CLASS:
                            $code .= $token[1];
                            $state = 'anonymous';

                            break;
                        default:
                            $i--; //reprocess last
                            $state = 'id_name';
                    }

                    break;
                case 'id_name':
                    switch ($token[0]) {
                        case $token[0] === ':' && $context !== 'instanceof':
                            if ($lastState === 'closure' && $context === 'root') {
                                $state = 'closure';
                                $code .= $id_start . $token;
                            }

                            break;
                        case T_NAME_QUALIFIED:
                        case T_NS_SEPARATOR:
                        case T_STRING:
                        case T_WHITESPACE:
                        case T_COMMENT:
                        case T_DOC_COMMENT:
                            $id_name .= $token[1];

                            break;
                        case '(':
                            if ($isShortClosure) {
                                ++$open;
                            }
                            if ($context === 'new' || str_contains($id_name, '\\')) {
                                if ($id_start_ci === 'self' || $id_start_ci === 'static') {
                                    if (!$inside_structure) {
                                        $isUsingScope = true;
                                    }
                                } elseif ($id_start !== '\\' && !in_array($id_start_ci, $class_keywords)) {
                                    if ($classes === null) {
                                        $classes = $this->getClasses();
                                    }
                                    if (isset($classes[$id_start_ci])) {
                                        $id_start = $classes[$id_start_ci];
                                    }
                                    if ($id_start[0] !== '\\') {
                                        $id_start = $nsf . '\\' . $id_start;
                                    }
                                }
                            } else {
                                if ($id_start !== '\\') {
                                    if ($functions === null) {
                                        $functions = $this->getFunctions();
                                    }
                                    if (isset($functions[$id_start_ci])) {
                                        $id_start = $functions[$id_start_ci];
                                    } elseif ($nsf !== '\\' && function_exists($nsf . '\\' . $id_start)) {
                                        $id_start = $nsf . '\\' . $id_start;
                                        // Cache it to functions array
                                        $functions[$id_start_ci] = $id_start;
                                    }
                                }
                            }
                            $code .= $id_start . $id_name . '(';
                            $state = $lastState;

                            break;
                        case T_VARIABLE:
                        case T_DOUBLE_COLON:
                            if ($id_start !== '\\') {
                                if ($id_start_ci === 'self' || $id_start_ci === 'parent') {
                                    if (!$inside_structure) {
                                        $isUsingScope = true;
                                    }
                                } elseif ($id_start_ci === 'static') {
                                    if (!$inside_structure) {
                                        $isUsingScope = $token[0] === T_DOUBLE_COLON;
                                    }
                                } elseif (!(PHP_MAJOR_VERSION >= 7 && in_array($id_start_ci, $builtin_types))) {
                                    if ($classes === null) {
                                        $classes = $this->getClasses();
                                    }
                                    if (isset($classes[$id_start_ci])) {
                                        $id_start = $classes[$id_start_ci];
                                    }
                                    if ($id_start[0] !== '\\') {
                                        $id_start = $nsf . '\\' . $id_start;
                                    }
                                }
                            }

                            $code .= $id_start . $id_name . $token[1];
                            $state = $token[0] === T_DOUBLE_COLON ? 'ignore_next' : $lastState;

                            break;
                        default:
                            if ($id_start !== '\\' && !defined($id_start)) {
                                if ($constants === null) {
                                    $constants = $this->getConstants();
                                }
                                if (isset($constants[$id_start])) {
                                    $id_start = $constants[$id_start];
                                } elseif ($context === 'new') {
                                    if (in_array($id_start_ci, $class_keywords)) {
                                        if (!$inside_structure) {
                                            $isUsingScope = true;
                                        }
                                    } else {
                                        if ($classes === null) {
                                            $classes = $this->getClasses();
                                        }
                                        if (isset($classes[$id_start_ci])) {
                                            $id_start = $classes[$id_start_ci];
                                        }
                                        if ($id_start[0] !== '\\') {
                                            $id_start = $nsf . '\\' . $id_start;
                                        }
                                    }
                                } elseif (
                                    $context === 'use'
                                    || $context === 'instanceof'
                                    || $context === 'args'
                                    || $context === 'return_type'
                                    || $context === 'extends'
                                    || $context === 'root'
                                ) {
                                    if (in_array($id_start_ci, $class_keywords)) {
                                        if (!$inside_structure && !$id_start_ci === 'static') {
                                            $isUsingScope = true;
                                        }
                                    } elseif (!(PHP_MAJOR_VERSION >= 7 && in_array($id_start_ci, $builtin_types))) {
                                        if ($classes === null) {
                                            $classes = $this->getClasses();
                                        }
                                        if (isset($classes[$id_start_ci])) {
                                            $id_start = $classes[$id_start_ci];
                                        }
                                        if ($id_start[0] !== '\\') {
                                            $id_start = $nsf . '\\' . $id_start;
                                        }
                                    }
                                }
                            }
                            $code .= $id_start . $id_name;
                            $state = $lastState;
                            --$i; //reprocess last token
                    }

                    break;
                case 'anonymous':
                    switch ($token[0]) {
                        case T_NAME_QUALIFIED:
                            [$id_start, $id_start_ci, $id_name] = $this->parseNameQualified($token[1]);
                            $state                              = 'id_name';
                            $lastState                          = 'anonymous';

                            break 2;
                        case T_NS_SEPARATOR:
                        case T_STRING:
                            $id_start    = $token[1];
                            $id_start_ci = strtolower($id_start);
                            $id_name     = '';
                            $state       = 'id_name';
                            $context     = 'extends';
                            $lastState   = 'anonymous';

                            break;
                        case '{':
                            $state = 'closure';
                            if (!$inside_structure) {
                                $inside_structure      = true;
                                $inside_structure_mark = $open;
                            }
                            --$i;

                            break;
                        default:
                            $code .= is_array($token) ? $token[1] : $token;
                    }

                    break;
            }
        }

        if ($isShortClosure) {
            $this->useVariables = $this->getStaticVariables();
        } else {
            $this->useVariables = empty($use)
                ? $use
                : array_intersect_key($this->getStaticVariables(), array_flip($use));
        }

        $this->isShortClosure    = $isShortClosure;
        $this->isBindingRequired = $isUsingThisObject;
        $this->isScopeRequired   = $isUsingScope;

        if (PHP_VERSION_ID >= 80100) {
            $attributesCode = array_map(function ($attribute) {
                $arguments = $attribute->getArguments();

                $name      = $attribute->getName();
                $arguments = implode(', ', array_map(function ($argument, $key) {
                    $argument = sprintf(
                        "'%s'",
                        str_replace("'", "\\'", $argument)
                    );

                    if (is_string($key)) {
                        $argument = sprintf('%s: %s', $key, $argument);
                    }

                    return $argument;
                }, $arguments, array_keys($arguments)));

                return "#[$name($arguments)]";
            }, $this->getAttributes());

            if (!empty($attributesCode)) {
                $code = implode("\n", array_merge($attributesCode, [$code]));
            }
        }

        $this->code = $code;

        return $this->code;
    }

    /**
     * Get PHP native built in types.
     *
     * @return array Return an array of native PHO built in types.
     */
    protected static function getBuiltinTypes(): array
    {
        // PHP 8.1
        if (PHP_VERSION_ID >= 80100) {
            return [
                'array',
                'callable',
                'string',
                'int',
                'bool',
                'float',
                'iterable',
                'void',
                'object',
                'mixed',
                'false',
                'null',
                'never',
            ];
        }

        // PHP 8
        if (PHP_MAJOR_VERSION === 8) {
            return [
                'array',
                'callable',
                'string',
                'int',
                'bool',
                'float',
                'iterable',
                'void',
                'object',
                'mixed',
                'false',
                'null',
            ];
        }

        // PHP 7
        return match (PHP_MINOR_VERSION) {
            0       => ['array', 'callable', 'string', 'int', 'bool', 'float'],
            1       => ['array', 'callable', 'string', 'int', 'bool', 'float', 'iterable', 'void'],
            default => ['array', 'callable', 'string', 'int', 'bool', 'float', 'iterable', 'void', 'object'],
        };
    }

    /**
     * Gets the use variables by the closure.
     *
     * @return array Return an array of use variables by the closure.
     */
    public function getUseVariables(): array
    {
        if ($this->useVariables !== null) {
            return $this->useVariables;
        }

        $tokens = $this->getTokens();
        $use    = [];
        $state  = 'start';

        foreach ($tokens as &$token) {
            $is_array = is_array($token);

            switch ($state) {
                case 'start':
                    if ($is_array && $token[0] === T_USE) {
                        $state = 'use';
                    }

                    break;
                case 'use':
                    if ($is_array) {
                        if ($token[0] === T_VARIABLE) {
                            $use[] = substr($token[1], 1);
                        }
                    } elseif ($token == ')') {
                        break 2;
                    }

                    break;
            }
        }

        $this->useVariables = empty($use) ? $use : array_intersect_key($this->getStaticVariables(), array_flip($use));

        return $this->useVariables;
    }

    /**
     * Checks if binding is required.
     *
     * @return bool Return true if bindings is required, false if not.
     */
    public function isBindingRequired(): bool
    {
        if ($this->isBindingRequired === null) {
            $this->getCode();
        }

        return $this->isBindingRequired;
    }

    /**
     * Checks if access to the scope is required.
     *
     * @return bool Return true if the access to the scope is required, false if not.
     */
    public function isScopeRequired(): bool
    {
        if ($this->isScopeRequired === null) {
            $this->getCode();
        }

        return $this->isScopeRequired;
    }

    /**
     * The hash of the current file name.
     *
     * @return string Return the ash for the current file name.
     */
    protected function getHashedFileName(): string
    {
        if ($this->hashedName === null) {
            $this->hashedName = sha1($this->getFileName());
        }

        return $this->hashedName;
    }

    /**
     * Get the file tokens.
     *
     * @return array Return an array of file tokens.
     */
    protected function getFileTokens(): array
    {
        $key = $this->getHashedFileName();

        if (! isset(static::$files[$key])) {
            static::$files[$key] = token_get_all(file_get_contents($this->getFileName()));
        }

        return static::$files[$key];
    }

    /**
     * Get the tokens.
     *
     * @return array Return an array of the tokens.
     */
    protected function getTokens(): array
    {
        if ($this->tokens === null) {
            $tokens    = $this->getFileTokens();
            $startLine = $this->getStartLine();
            $endLine   = $this->getEndLine();
            $results   = [];
            $start     = false;

            foreach ($tokens as &$token) {
                if (! is_array($token)) {
                    if ($start) {
                        $results[] = $token;
                    }

                    continue;
                }

                $line = $token[2];

                if ($line <= $endLine) {
                    if ($line >= $startLine) {
                        $start     = true;
                        $results[] = $token;
                    }

                    continue;
                }

                break;
            }

            $this->tokens = $results;
        }

        return $this->tokens;
    }

    /**
     * Get the classes.
     *
     * @return array Return a n array of classes.
     */
    protected function getClasses(): array
    {
        $key = $this->getHashedFileName();

        if (! isset(static::$classes[$key])) {
            $this->fetchItems();
        }

        return static::$classes[$key];
    }

    /**
     * Get the functions.
     *
     * @return array Return an array of the functions.
     */
    protected function getFunctions(): array
    {
        $key = $this->getHashedFileName();

        if (! isset(static::$functions[$key])) {
            $this->fetchItems();
        }

        return static::$functions[$key];
    }

    /**
     * Gets the constants.
     *
     * @return array Return an array of the constants.
     */
    protected function getConstants(): array
    {
        $key = $this->getHashedFileName();

        if (! isset(static::$constants[$key])) {
            $this->fetchItems();
        }

        return static::$constants[$key];
    }

    /**
     * Get the structures.
     *
     * @return array Return an array of the structures.
     */
    protected function getStructures(): array
    {
        $key = $this->getHashedFileName();

        if (! isset(static::$structures[$key])) {
            $this->fetchItems();
        }

        return static::$structures[$key];
    }

    /**
     * Fetch the items.
     *
     * @return void
     */
    protected function fetchItems(): void
    {
        $key = $this->getHashedFileName();

        $classes    = [];
        $functions  = [];
        $constants  = [];
        $structures = [];
        $tokens     = $this->getFileTokens();

        $open      = 0;
        $state     = 'start';
        $lastState = '';
        $prefix    = '';
        $name      = '';
        $alias     = '';
        $isFunc    = $isConst = false;

        $startLine    = $endLine = 0;
        $structType   = $structName = '';
        $structIgnore = false;

        foreach ($tokens as $token) {
            switch ($state) {
                case 'start':
                    switch ($token[0]) {
                        case T_CLASS:
                        case T_INTERFACE:
                        case T_TRAIT:
                            $state      = 'before_structure';
                            $startLine  = $token[2];
                            $structType = $token[0] == T_CLASS
                                ? 'class'
                                : ( $token[0] == T_INTERFACE ? 'interface' : 'trait' );

                            break;
                        case T_USE:
                            $state  = 'use';
                            $prefix = $name = $alias = '';
                            $isFunc = $isConst = false;

                            break;
                        case T_FUNCTION:
                            $state        = 'structure';
                            $structIgnore = true;

                            break;
                        case T_NEW:
                            $state = 'new';

                            break;
                        case T_OBJECT_OPERATOR:
                        case T_DOUBLE_COLON:
                            $state = 'invoke';

                            break;
                    }

                    break;
                case 'use':
                    switch ($token[0]) {
                        case T_FUNCTION:
                            $isFunc = true;

                            break;
                        case T_CONST:
                            $isConst = true;

                            break;
                        case T_NS_SEPARATOR:
                            $name .= $token[1];

                            break;
                        case T_STRING:
                            $name .= $token[1];
                            $alias = $token[1];

                            break;
                        case T_NAME_QUALIFIED:
                            $name .= $token[1];
                            $pieces = explode('\\', $token[1]);
                            $alias  = end($pieces);

                            break;
                        case T_AS:
                            $lastState = 'use';
                            $state     = 'alias';

                            break;
                        case '{':
                            $prefix = $name;
                            $name   = $alias = '';
                            $state  = 'use-group';

                            break;
                        case ',':
                        case ';':
                            if ($name === '' || $name[0] !== '\\') {
                                $name = '\\' . $name;
                            }

                            if ($alias !== '') {
                                if ($isFunc) {
                                    $functions[strtolower($alias)] = $name;
                                } elseif ($isConst) {
                                    $constants[$alias] = $name;
                                } else {
                                    $classes[strtolower($alias)] = $name;
                                }
                            }
                            $name  = $alias = '';
                            $state = $token === ';' ? 'start' : 'use';

                            break;
                    }

                    break;
                case 'use-group':
                    switch ($token[0]) {
                        case T_NS_SEPARATOR:
                            $name .= $token[1];

                            break;
                        case T_NAME_QUALIFIED:
                            $name .= $token[1];
                            $pieces = explode('\\', $token[1]);
                            $alias  = end($pieces);

                            break;
                        case T_STRING:
                            $name .= $token[1];
                            $alias = $token[1];

                            break;
                        case T_AS:
                            $lastState = 'use-group';
                            $state     = 'alias';

                            break;
                        case ',':
                        case '}':
                            if ($prefix === '' || $prefix[0] !== '\\') {
                                $prefix = '\\' . $prefix;
                            }

                            if ($alias !== '') {
                                if ($isFunc) {
                                    $functions[strtolower($alias)] = $prefix . $name;
                                } elseif ($isConst) {
                                    $constants[$alias] = $prefix . $name;
                                } else {
                                    $classes[strtolower($alias)] = $prefix . $name;
                                }
                            }
                            $name  = $alias = '';
                            $state = $token === '}' ? 'use' : 'use-group';

                            break;
                    }

                    break;
                case 'alias':
                    if ($token[0] === T_STRING) {
                        $alias = $token[1];
                        $state = $lastState;
                    }

                    break;
                case 'new':
                    switch ($token[0]) {
                        case T_WHITESPACE:
                        case T_COMMENT:
                        case T_DOC_COMMENT:
                            break 2;
                        case T_CLASS:
                            $state        = 'structure';
                            $structIgnore = true;

                            break;
                        default:
                            $state = 'start';
                    }

                    break;
                case 'invoke':
                    switch ($token[0]) {
                        case T_WHITESPACE:
                        case T_COMMENT:
                        case T_DOC_COMMENT:
                            break 2;
                        default:
                            $state = 'start';
                    }

                    break;
                case 'before_structure':
                    if ($token[0] == T_STRING) {
                        $structName = $token[1];
                        $state      = 'structure';
                    }

                    break;
                case 'structure':
                    switch ($token[0]) {
                        case '{':
                        case T_CURLY_OPEN:
                        case T_DOLLAR_OPEN_CURLY_BRACES:
                            $open++;

                            break;
                        case '}':
                            if (--$open == 0) {
                                if (!$structIgnore) {
                                    $structures[] = [
                                        'type'  => $structType,
                                        'name'  => $structName,
                                        'start' => $startLine,
                                        'end'   => $endLine,
                                    ];
                                }
                                $structIgnore = false;
                                $state        = 'start';
                            }

                            break;
                        default:
                            if (is_array($token)) {
                                $endLine = $token[2];
                            }
                    }

                    break;
            }
        }

        static::$classes[$key]    = $classes;
        static::$functions[$key]  = $functions;
        static::$constants[$key]  = $constants;
        static::$structures[$key] = $structures;
    }

    /**
     * Returns the namespace associated to the closure.
     *
     * @return string Return the namespace associated to the closure.
     */
    protected function getClosureNamespaceName(): string
    {
        $ns = $this->getNamespaceName();

        // First class callable...
        if ($this->getName() !== '{closure}' && empty($ns) && ! is_null($this->getClosureScopeClass())) {
            $ns = $this->getClosureScopeClass()->getNamespaceName();
        }

        return $ns;
    }

    /**
     * Parse the given token.
     *
     * @param string $token Holds the tokens to parse.
     * @return array Return an array of parsed tokens.
     */
    protected function parseNameQualified(string $token): array
    {
        $pieces      = explode('\\', $token);
        $id_start    = array_shift($pieces);
        $id_start_ci = strtolower($id_start);
        $id_name     = '\\' . implode('\\', $pieces);

        return [ $id_start, $id_start_ci, $id_name ];
    }
}
