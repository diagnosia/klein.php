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

namespace Klein\DataCollection;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;

/**
 * DataCollection
 *
 * A generic collection class to contain array-like data, specifically
 * designed to work with HTTP data (request params, session data, etc)
 *
 * Inspired by @fabpot's Symfony 2's HttpFoundation
 * @link https://github.com/symfony/HttpFoundation/blob/master/ParameterBag.php
 */
class DataCollection implements IteratorAggregate, ArrayAccess, Countable
{
    /**
     * Collection of data attributes
     *
     * @var array<array-key, mixed>
     */
    protected array $attributes = [];

    /**
     * Constructor
     *
     * @param array $attributes The data attributes of this collection
     */
    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    /**
     * Returns all of the key names in the collection
     *
     * If an optional mask array is passed, this only
     * returns the keys that match the mask
     *
     * The mask may also be passed as separate key arguments ("magical" call), e.g. keys('a', 'b')
     *
     * @param array|string|int|null $mask   The parameter mask array (or the first key of a "magical" call)
     * @param mixed $fill_with_nulls        Whether or not to fill the returned array with
     *  values to match the given mask, even if they don't exist in the collection
     *  (the second key in a "magical" call, so it can't be typed as boolean)
     */
    public function keys(array|string|int|null $mask = null, mixed $fill_with_nulls = true): array
    {
        if (null !== $mask) {
            // Support a more "magical" call
            if (!is_array($mask)) {
                $mask = func_get_args();
            }

            /*
             * Make sure that the returned array has at least the values
             * passed into the mask, since the user will expect them to exist
             */
            $keys = $fill_with_nulls ? $mask : [];

            /*
             * Remove all of the values from the keys
             * that aren't in the passed mask
             */
            return array_intersect(array_keys($this->attributes), $mask) + $keys;
        }

        return array_keys($this->attributes);
    }

    /**
     * Returns all of the attributes in the collection
     *
     * If an optional mask array is passed, this only
     * returns the keys that match the mask
     *
     * @param array|string|int|null $mask The parameter mask array (or the first key of a "magical" call)
     * @param mixed $fill_with_nulls    Whether or not to fill the returned array with
     *  values to match the given mask, even if they don't exist in the collection
     */
    public function all(array|string|int|null $mask = null, mixed $fill_with_nulls = true): array
    {
        if (null !== $mask) {
            // Support a more "magical" call
            if (!is_array($mask)) {
                $mask = func_get_args();
            }

            /*
             * Make sure that each key in the mask has at least a
             * null value, since the user will expect the key to exist
             */
            $attributes = $fill_with_nulls ? array_fill_keys($mask, value: null) : [];

            /*
             * Remove all of the keys from the attributes
             * that aren't in the passed mask
             */
            return array_intersect_key($this->attributes, array_flip($mask)) + $attributes;
        }

        return $this->attributes;
    }

    /**
     * Return an attribute of the collection
     *
     * Return a default value if the key doesn't exist
     *
     * @param string|int $key       The name of the parameter to return
     * @param mixed  $default_val   The default value of the parameter if it contains no value
     */
    public function get(string|int $key, mixed $default_val = null): mixed
    {
        return $this->attributes[$key] ?? $default_val;
    }

    /**
     * Set an attribute of the collection
     *
     * @param string|int $key   The name of the parameter to set
     * @param mixed  $value The value of the parameter to set
     */
    public function set(string|int $key, mixed $value): static
    {
        $this->attributes[$key] = $value;

        return $this;
    }

    /**
     * Replace the collection's attributes
     *
     * @param array $attributes The attributes to replace the collection's with
     */
    public function replace(array $attributes = []): static
    {
        $this->attributes = $attributes;

        return $this;
    }

    /**
     * Merge attributes with the collection's attributes
     *
     * Optionally allows a second boolean parameter to merge the attributes
     * into the collection in a "hard" manner, using the "array_replace"
     * method instead of the usual "array_merge" method
     *
     * @param array $attributes The attributes to merge into the collection
     * @param boolean $hard     Whether or not to make the merge "hard"
     */
    public function merge(array $attributes = [], bool $hard = false): static
    {
        // Don't waste our time with an "array_merge" call if the array is empty
        if ([] !== $attributes) {
            // Hard merge?
            $this->attributes = $hard
                ? array_replace($this->attributes, $attributes)
                : array_merge($this->attributes, $attributes);
        }

        return $this;
    }

    /**
     * See if an attribute exists in the collection
     *
     * @param string|int $key   The name of the parameter
     */
    public function exists(string|int $key): bool
    {
        // Don't use "isset", since it returns false for null values
        return array_key_exists($key, $this->attributes);
    }

    /**
     * Remove an attribute from the collection
     *
     * @param string|int $key   The name of the parameter
     */
    public function remove(string|int $key): void
    {
        unset($this->attributes[$key]);
    }

    /**
     * Clear the collection's contents
     *
     * Semantic alias of a no-argument `$this->replace` call
     */
    public function clear(): static
    {
        return $this->replace();
    }

    /**
     * Check if the collection is empty
     */
    public function isEmpty(): bool
    {
        return [] === $this->attributes;
    }

    /**
     * A quick convenience method to get an empty clone of the
     * collection. Great for dependency injection. :)
     */
    public function cloneEmpty(): static
    {
        $clone = clone $this;
        $clone->clear();

        return $clone;
    }

    /**
     * Magic "__get" method
     *
     * Allows the ability to arbitrarily request an attribute from
     * this instance while treating it as an instance property
     *
     * @see get()
     * @param string $key   The name of the parameter to return
     */
    public function __get(string $key): mixed
    {
        return $this->get($key);
    }

    /**
     * Magic "__set" method
     *
     * Allows the ability to arbitrarily set an attribute from
     * this instance while treating it as an instance property
     *
     * @see set()
     * @param string $key   The name of the parameter to set
     * @param mixed  $value The value of the parameter to set
     */
    public function __set(string $key, mixed $value): void
    {
        $this->set($key, $value);
    }

    /**
     * Magic "__isset" method
     *
     * Allows the ability to arbitrarily check the existence of an attribute
     * from this instance while treating it as an instance property
     *
     * @see exists()
     * @param string $key   The name of the parameter
     */
    public function __isset(string $key): bool
    {
        return $this->exists($key);
    }

    /**
     * Magic "__unset" method
     *
     * Allows the ability to arbitrarily remove an attribute from
     * this instance while treating it as an instance property
     *
     * @see remove()
     * @param string $key   The name of the parameter
     */
    public function __unset(string $key): void
    {
        $this->remove($key);
    }

    /*
     * Interface required method implementations
     */

    /**
     * Get the aggregate iterator
     *
     * IteratorAggregate interface required method
     *
     * @see \IteratorAggregate::getIterator()
     * @return ArrayIterator<array-key, mixed>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->attributes);
    }

    /**
     * Get an attribute via array syntax
     *
     * Allows the access of attributes of this instance while treating it like an array
     *
     * @see \ArrayAccess::offsetGet()
     * @see get()
     * @param mixed $key    The name (string|int) of the parameter to return, mixed as required by ArrayAccess
     */
    public function offsetGet(mixed $key): mixed
    {
        return $this->get($key);
    }

    /**
     * Set an attribute via array syntax
     *
     * Allows the access of attributes of this instance while treating it like an array
     *
     * @see \ArrayAccess::offsetSet()
     * @see set()
     * Appending ($collection[] = $value) adds the value under the next integer key
     *
     * @param mixed $key    The name (string|int, null to append) of the parameter, mixed as required by ArrayAccess
     * @param mixed  $value         The value of the parameter to set
     */
    public function offsetSet(mixed $key, mixed $value): void
    {
        if (null === $key) {
            $this->attributes[] = $value;

            return;
        }

        $this->set($key, $value);
    }

    /**
     * Check existence an attribute via array syntax
     *
     * Allows the access of attributes of this instance while treating it like an array
     *
     * @see \ArrayAccess::offsetExists()
     * @see exists()
     * @param mixed $key    The name (string|int) of the parameter, mixed as required by ArrayAccess
     */
    public function offsetExists(mixed $key): bool
    {
        return $this->exists($key);
    }

    /**
     * Remove an attribute via array syntax
     *
     * Allows the access of attributes of this instance while treating it like an array
     *
     * @see \ArrayAccess::offsetUnset()
     * @see remove()
     * @param mixed $key    The name (string|int) of the parameter, mixed as required by ArrayAccess
     */
    public function offsetUnset(mixed $key): void
    {
        $this->remove($key);
    }

    /**
     * Count the attributes via a simple "count" call
     *
     * Allows the use of the "count" function (or any internal counters)
     * to simply count the number of attributes in the collection.
     *
     * @see \Countable::count()
     */
    public function count(): int
    {
        return count($this->attributes);
    }
}
