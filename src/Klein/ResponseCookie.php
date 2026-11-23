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

use InvalidArgumentException;

/**
 * ResponseCookie
 *
 * Class to represent an HTTP response cookie
 */
class ResponseCookie
{
    /**
     * Class properties
     */

    /**
     * The name of the cookie
     */
    protected string $name;

    /**
     * The string "value" of the cookie
     */
    protected string|null $value;

    /**
     * The date/time that the cookie should expire
     *
     * Represented by a Unix "Timestamp"
     */
    protected int|null $expire;

    /**
     * The path on the server that the cookie will
     * be available on
     */
    protected string|null $path;

    /**
     * The domain that the cookie is available to
     */
    protected string|null $domain;

    /**
     * Whether the cookie should only be transferred
     * over an HTTPS connection or not
     */
    protected bool $secure;

    /**
     * Whether the cookie will be available through HTTP
     * only (not available to be accessed through
     * client-side scripting languages like JavaScript)
     */
    protected bool $http_only;

    /**
     * Methods
     */

    /**
     * Constructor
     *
     * @param string|int                  $name   The name of the cookie (array keys like "123" become ints)
     * @param string|int|float|bool|null  $value  The value to set the cookie with, scalars are cast to string
     * @param int|string|null             $expire The time that the cookie should expire, as a (numeric) Unix timestamp
     * @param string  $path         The path of which to restrict the cookie
     * @param string  $domain       The domain of which to restrict the cookie
     * @param bool    $secure       Flag of whether the cookie should only be sent over a HTTPS connection
     * @param bool    $http_only    Flag of whether the cookie should only be accessible over the HTTP protocol
     */
    public function __construct(
        string|int $name,
        string|int|float|bool|null $value = null,
        int|string|null $expire = null,
        string|null $path = null,
        string|null $domain = null,
        bool $secure = false,
        bool $http_only = false,
    ) {
        // Initialize our properties
        $this->setName($name);
        $this->setValue($value);
        $this->setExpire($expire);
        $this->setPath($path);
        $this->setDomain($domain);
        $this->setSecure($secure);
        $this->setHttpOnly($http_only);
    }

    /**
     * Gets the cookie's name
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Sets the cookie's name
     *
     * @return ResponseCookie
     */
    public function setName(string|int $name)
    {
        $this->name = (string) $name;

        return $this;
    }

    /**
     * Gets the cookie's value
     */
    public function getValue(): string|null
    {
        return $this->value;
    }

    /**
     * Sets the cookie's value
     */
    public function setValue(string|int|float|bool|null $value): static
    {
        $this->value = null === $value ? null : (string) $value;

        return $this;
    }

    /**
     * Gets the cookie's expire time
     */
    public function getExpire(): int|null
    {
        return $this->expire;
    }

    /**
     * Sets the cookie's expire time
     *
     * The time should be an integer (or numeric string)
     * representing a Unix timestamp
     *
     * @throws InvalidArgumentException If the time is a non-numeric string
     */
    public function setExpire(int|string|null $expire): static
    {
        if (is_string($expire)) {
            if (!is_numeric($expire)) {
                throw new InvalidArgumentException('Expected a numeric cookie expire time. Got: ' . $expire);
            }

            $expire = (int) $expire;
        }

        $this->expire = $expire;

        return $this;
    }

    /**
     * Gets the cookie's path
     */
    public function getPath(): string|null
    {
        return $this->path;
    }

    /**
     * Sets the cookie's path
     */
    public function setPath(string|null $path): static
    {
        $this->path = $path;

        return $this;
    }

    /**
     * Gets the cookie's domain
     */
    public function getDomain(): string|null
    {
        return $this->domain;
    }

    /**
     * Sets the cookie's domain
     */
    public function setDomain(string|null $domain): static
    {
        $this->domain = $domain;

        return $this;
    }

    /**
     * Gets the cookie's secure only flag
     */
    public function getSecure(): bool
    {
        return $this->secure;
    }

    /**
     * Sets the cookie's secure only flag
     */
    public function setSecure(bool $secure): static
    {
        $this->secure = $secure;

        return $this;
    }

    /**
     * Gets the cookie's HTTP only flag
     */
    public function getHttpOnly(): bool
    {
        return $this->http_only;
    }

    /**
     * Sets the cookie's HTTP only flag
     */
    public function setHttpOnly(bool $http_only): static
    {
        $this->http_only = $http_only;

        return $this;
    }
}
