<?php

declare(strict_types=1);

/**
 * Klein (klein.php) - A fast & flexible router for PHP
 *
 * @author      Chris O'Hara <cohara87@gmail.com>
 * @author      Trevor Suarez (Rican7) (contributor and v2 refactorer)
 * @copyright   (c) Chris O'Hara
 * @link        https://github.com/klein/klein.php
 * @license     MIT
 */

namespace Klein;

use BadMethodCallException;
use Klein\Exceptions\ValidationException;

class Validator
{
    /**
     * The available validator methods
     *
     * @var array<string, callable>
     */
    public static array $methods = [];

    /**
     * The string to validate
     */
    protected null|string $str;

    /**
     * The type of the given value, if it couldn't be validated as a string (e.g. an array)
     *
     * Every check, negated ones included, fails for such a value
     */
    protected null|string $invalid_type = null;

    /**
     * The custom exception message to throw on validation failure
     */
    protected null|string|bool $err;

    /**
     * Flag for whether the default validation methods have been added or not
     */
    protected static bool $default_added = false;

    /**
     * Sets up the validator chain with the string and optional error message
     *
     * @param mixed $str   The string to validate, other scalars are cast to string
     * @param string|bool|null $err The optional custom exception message to throw on validation failure
     */
    public function __construct(mixed $str, null|string|bool $err = null)
    {
        $this->err = $err;
        $this->str = null;

        if (null !== $str && is_scalar($str)) {
            $this->str = (string) $str;
        }

        if (null !== $str && !is_scalar($str)) {
            // e.g. "?id[]=1" gives an array, which can't pass any string validation
            $this->invalid_type = get_debug_type($str);
        }

        if (!static::$default_added) {
            static::addDefault();
        }
    }

    /**
     * Adds default validators on first use
     */
    public static function addDefault(): void
    {
        static::addDefaultTypeValidators();
        static::addDefaultFilterValidators();
        static::addDefaultCharacterValidators();

        static::$default_added = true;
    }

    /**
     * Adds the default validators for empty values, lengths and number types
     */
    protected static function addDefaultTypeValidators(): void
    {
        static::$methods['null'] = static fn(null|string $str): bool => $str === null || $str === '';
        static::$methods['len'] = static function (null|string $str, int $min, null|int $max = null): bool {
            if ($str === null) {
                return false;
            }

            $len = strlen($str);
            return null === $max ? $len === $min : $len >= $min && $len <= $max;
        };
        static::$methods['int'] = static function (null|string $str): bool {
            if ($str === null) {
                return false;
            }

            return (string) $str === (string) (int) $str;
        };
        static::$methods['float'] = static function (null|string $str): bool {
            if ($str === null) {
                return false;
            }

            return (string) $str === (string) (float) $str;
        };
    }

    /**
     * Adds the default validators based on "filter_var" (email, URL, IP)
     */
    protected static function addDefaultFilterValidators(): void
    {
        static::$methods['email'] = static function (null|string $str): bool {
            if ($str === null) {
                return false;
            }

            return filter_var($str, FILTER_VALIDATE_EMAIL) !== false;
        };
        static::$methods['url'] = static function (null|string $str): bool {
            if ($str === null) {
                return false;
            }

            return filter_var($str, FILTER_VALIDATE_URL) !== false;
        };
        static::$methods['ip'] = static function (null|string $str): bool {
            if ($str === null) {
                return false;
            }

            return filter_var($str, FILTER_VALIDATE_IP) !== false;
        };
        static::$methods['remoteip'] = static function (null|string $str): bool {
            if ($str === null) {
                return false;
            }

            return filter_var($str, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        };
    }

    /**
     * Adds the default validators for characters, substrings and patterns
     */
    protected static function addDefaultCharacterValidators(): void
    {
        static::$methods['alnum'] = static function (null|string $str): bool {
            if ($str === null) {
                return false;
            }

            return ctype_alnum($str);
        };
        static::$methods['alpha'] = static function (null|string $str): bool {
            if ($str === null) {
                return false;
            }

            return ctype_alpha($str);
        };
        static::$methods['contains'] = static function (null|string $str, string $needle): bool {
            if ($str === null) {
                return false;
            }

            return str_contains($str, $needle);
        };
        static::$methods['regex'] = static function (null|string $str, string $pattern): bool {
            if ($str === null) {
                return false;
            }

            return preg_match($pattern, $str) === 1;
        };
        static::$methods['chars'] = static function (null|string $str, string $chars): bool {
            if ($str === null) {
                return false;
            }

            return preg_match("/^[{$chars}]++$/i", $str) === 1;
        };
    }

    /**
     * Add a custom validator to our list of validation methods
     *
     * @param string $method        The name of the validator method
     * @param callable $callback    The callback to perform on validation
     */
    public static function addValidator(string $method, callable $callback): void
    {
        static::$methods[strtolower($method)] = $callback;
    }

    /**
     * Magic "__call" method
     *
     * Allows the ability to arbitrarily call a validator with an optional prefix
     * of "is" or "not" by simply calling an instance property like a callback
     *
     * @param string $method            The callable method to execute
     * @param array $args               The argument array to pass to our callback
     * @throws BadMethodCallException   If an attempt was made to call a validator modifier that doesn't exist
     * @throws ValidationException      If the validation check returns false
     */
    public function __call(string $method, array $args): Validator|bool
    {
        $reverse = false;
        $validator = $method;
        $method_substr = substr($method, offset: 0, length: 2);

        if ($method_substr === 'is') { // is<$validator>()
            $validator = substr($method, offset: 2);
        }

        if ($method_substr === 'no') { // not<$validator>()
            $validator = substr($method, offset: 3);
            $reverse = true;
        }

        $validator = strtolower($validator);

        if (!$validator || (static::$methods[$validator] ?? null) === null) {
            throw new BadMethodCallException('Unknown method ' . $method . '()');
        }

        $validator = static::$methods[$validator];
        array_unshift($args, $this->str);

        $result = null === $this->invalid_type && (bool) ($validator(...$args) ^ $reverse);

        if ($this->err === false) {
            return $result;
        }

        if ($result === false) {
            throw new ValidationException(
                is_string($this->err)
                    ? $this->err
                    : 'Validation failed for '
                    . $method
                    . '() with value: '
                    . ($this->invalid_type ?? var_export($this->str, return: true)),
            );
        }

        return $this;
    }
}
