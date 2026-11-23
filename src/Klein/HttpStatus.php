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
 * HttpStatus
 *
 * HTTP status code and message translator
 */
class HttpStatus
{
    /**
     * The HTTP status code
     *
     * @var int
     */
    protected $code;

    /**
     * The HTTP status message
     *
     * @var string
     */
    protected $message;

    /**
     * HTTP 1.1 status messages based on code
     *
     * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html
     * @var array
     */
    protected static $http_messages = [
        // Informational 1xx
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        103 => 'Early Hints',

        // Successful 2xx
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',
        208 => 'Already Reported',
        226 => 'IM Used',

        // Redirection 3xx
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => '(Unused)',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',

        // Client Error 4xx
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        421 => 'Misdirected Request',
        422 => 'Unprocessable Content',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Too Early',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        451 => 'Unavailable For Legal Reasons',

        // Server Error 5xx
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        511 => 'Network Authentication Required',
    ];

    /**
     * Constructor
     *
     * @param int $code The HTTP code
     * @param string $message (optional) HTTP message for the corresponding code
     */
    public function __construct($code, $message = null)
    {
        $this->setCode($code);

        if (null === $message) {
            $message = static::getMessageFromCode($code);
        }

        $this->message = $message;
    }

    /**
     * Get the HTTP status code
     *
     * @return int
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * Get the HTTP status message
     *
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * Set the HTTP status code
     *
     * @param int $code
     * @throws InvalidArgumentException If the code isn't a valid HTTP status code (100-599, RFC 9110)
     * @return HttpStatus
     */
    public function setCode($code)
    {
        $code = (int) $code;

        if (!static::isValidCode($code)) {
            throw new InvalidArgumentException('Expected an HTTP status code between 100 and 599. Got: ' . $code);
        }

        $this->code = $code;
        return $this;
    }

    /**
     * Checks if a code is a valid HTTP status code
     *
     * RFC 9110 defines the range 100-599, anything else breaks the status line
     *
     * @param int $code
     * @return boolean
     */
    public static function isValidCode($code)
    {
        return $code >= 100 && $code <= 599;
    }

    /**
     * Set the HTTP status message
     *
     * @param string $message
     * @return HttpStatus
     */
    public function setMessage($message)
    {
        $this->message = (string) $message;
        return $this;
    }

    /**
     * Get a string representation of our HTTP status
     *
     * @return string
     */
    public function getFormattedString()
    {
        $string = (string) $this->code;

        if (null !== $this->message) {
            $string = $string . ' ' . $this->message;
        }

        return $string;
    }

    /**
     * Magic "__toString" method
     *
     * Allows the ability to arbitrarily use an instance of this class as a string
     * This method will be automatically called, returning a string representation
     * of this instance
     *
     * @return string
     */
    public function __toString()
    {
        return $this->getFormattedString();
    }

    /**
     * Get our HTTP 1.1 message from our passed code
     *
     * Returns null if no corresponding message was
     * found for the passed in code
     *
     * @param int $int
     * @return string|null
     */
    public static function getMessageFromCode($int)
    {
        return static::$http_messages[$int] ?? null;
    }
}
