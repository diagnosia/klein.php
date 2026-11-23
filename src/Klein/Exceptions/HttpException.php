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

namespace Klein\Exceptions;

use InvalidArgumentException;
use Klein\HttpStatus;
use RuntimeException;

/**
 * HttpException
 *
 * An HTTP error exception
 */
class HttpException extends RuntimeException implements HttpExceptionInterface
{
    /**
     * Methods
     */

    /**
     * Create an HTTP exception from nothing but an HTTP code
     *
     * Checked here, so e.g. abort(999) fails in the route callback (and goes through the error
     * callbacks) instead of later, while the HTTP error callbacks set the response code
     *
     * @param int $code
     * @throws InvalidArgumentException If the code isn't a valid HTTP status code
     * @return static
     */
    public static function createFromCode(int $code): static
    {
        if (!HttpStatus::isValidCode($code)) {
            throw new InvalidArgumentException('Expected an HTTP status code between 100 and 599. Got: ' . $code);
        }

        return new static('', $code);
    }
}
