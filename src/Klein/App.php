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

use AllowDynamicProperties;
use BadMethodCallException;
use Klein\Exceptions\DuplicateServiceException;
use Klein\Exceptions\UnknownServiceException;

/**
 * App
 */
#[AllowDynamicProperties]
class App
{
    /**
     * The array of app services
     *
     * @var array<string, callable(): mixed>
     */
    protected array $services = [];

    /**
     * Magic "__get" method
     *
     * Allows the ability to arbitrarily request a service from this instance
     * while treating it as an instance property
     *
     * This checks the lazy service register and automatically calls the registered
     * service method
     *
     * @param string $name              The name of the service
     * @throws UnknownServiceException  If a non-registered service is attempted to fetched
     */
    public function __get(string $name): mixed
    {
        if (($this->services[$name] ?? null) === null) {
            throw new UnknownServiceException('Unknown service ' . $name);
        }
        $service = $this->services[$name];

        return $service();
    }

    /**
     * Magic "__call" method
     *
     * Allows the ability to arbitrarily call a property as a callable method
     * Allow callbacks to be assigned as properties and called like normal methods
     *
     * @param string $method          The callable method to execute
     * @param array $args               The argument array to pass to our callback
     * @throws BadMethodCallException   If a non-registered method is attempted to be called
     */
    public function __call(string $method, array $args): mixed
    {
        if (!is_callable($this->services[$method] ?? null)) {
            throw new BadMethodCallException('Unknown method ' . $method . '()');
        }

        return call_user_func_array($this->services[$method], $args);
    }

    /**
     * Register a lazy service
     *
     * @param string $name                  The name of the service
     * @param callable $closure             The callable function to execute when requesting our service
     * @throws DuplicateServiceException    If an attempt is made to register two services with the same name
     */
    public function register(string $name, callable $closure): void
    {
        if (($this->services[$name] ?? null) !== null) {
            throw new DuplicateServiceException('A service is already registered under ' . $name);
        }

        $this->services[$name] = static function () use ($closure): mixed {
            static $instance;
            if (null === $instance) {
                $instance = $closure();
            }

            return $instance;
        };
    }
}
