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

use TypeError;

/**
 * Route
 *
 * Class to represent a route definition
 */
class Route
{
    /**
     * Properties
     */

    /**
     * The callback method to execute when the route is matched
     *
     * Any valid "callable" type is allowed
     *
     * @link http://php.net/manual/en/language.types.callable.php
     */
    protected mixed $callback;

    /**
     * The URL path to match
     *
     * Allows for regular expression matching and/or basic string matching
     *
     * Examples:
     * - '/posts'
     * - '/posts/[:post_slug]'
     * - '/posts/[i:id]'
     */
    protected string|null $path;

    /**
     * The HTTP method to match
     *
     * May either be represented as a string or an array containing multiple methods to match
     *
     * Examples:
     * - 'POST'
     * - array('GET', 'POST')
     */
    protected string|array|null $method;

    /**
     * Whether or not to count this route as a match when counting total matches
     */
    protected bool $count_match;

    /**
     * The name of the route
     *
     * Mostly used for reverse routing
     */
    protected string|null $name;

    /**
     * Methods
     */

    /**
     * Constructor
     */
    public function __construct(
        callable $callback,
        string|null $path = null,
        string|array|null $method = null,
        bool $count_match = true,
        string|null $name = null,
    ) {
        // Initialize some properties (use our setters so we can validate param types)
        $this->setCallback($callback);
        $this->setPath($path);
        $this->setMethod($method);
        $this->setCountMatch($count_match);
        $this->setName($name);
    }

    /**
     * Get the callback
     */
    public function getCallback(): callable
    {
        return $this->callback;
    }

    /**
     * Set the callback
     *
     * @throws TypeError If the callback isn't a callable
     */
    public function setCallback(callable $callback): static
    {
        $this->callback = $callback;

        return $this;
    }

    /**
     * Get the path
     */
    public function getPath(): string|null
    {
        return $this->path;
    }

    /**
     * Set the path
     */
    public function setPath(string|null $path): static
    {
        $this->path = $path;

        return $this;
    }

    /**
     * Get the method
     */
    public function getMethod(): string|array|null
    {
        return $this->method;
    }

    /**
     * Set the method
     *
     * @throws TypeError If a non-string or non-array type is passed
     */
    public function setMethod(string|array|null $method): static
    {
        // Explicit type check to prevent type coercion
        if ($method !== null && !is_array($method) && !is_string($method)) {
            throw new TypeError('Expected an array or string. Got a '. gettype($method));
        }

        $this->method = $method;

        return $this;
    }

    /**
     * Get the count_match
     */
    public function getCountMatch(): bool
    {
        return $this->count_match;
    }

    /**
     * Set the count_match
     */
    public function setCountMatch(bool $count_match): static
    {
        $this->count_match = $count_match;

        return $this;
    }

    /**
     * Get the name
     */
    public function getName(): string|null
    {
        return $this->name;
    }

    /**
     * Set the name
     */
    public function setName(string|null $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Magic "__invoke" method
     *
     * Allows the ability to arbitrarily call this instance like a function
     *
     * @param mixed $args Generic arguments, magically accepted
     * @return mixed
     */
    public function __invoke($args = null)
    {
        $args = func_get_args();

        return call_user_func_array($this->callback, $args);
    }
}
