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

namespace Klein\Tests;

use InvalidArgumentException;
use Klein\HttpStatus;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * HttpStatusTest
 */
class HttpStatusTest extends AbstractKleinTestCase
{
    public function testStaticMessageFromCode()
    {
        // Set our test data
        $code = 404;
        $message = 'Not Found'; // HTTP 1.1 404 status message

        static::assertSame($message, HttpStatus::getMessageFromCode($code));
    }

    public function testManualEntryViaConstructor()
    {
        // Set our manual test data
        $code = 599; // Valid, but without a registered message
        $message = 'The devil\'s mark';

        $http_status = new HttpStatus($code, $message);

        static::assertSame($code, $http_status->getCode());
        static::assertSame($message, $http_status->getMessage());
    }

    public function testManualEntryViaSetters()
    {
        // Set our manual test data
        $constructor_code = 123;
        $code = 599; // Valid, but without a registered message
        $message = 'The devil\'s mark';

        $http_status = new HttpStatus($constructor_code);
        $http_status->setCode($code);
        $http_status->setMessage($message);

        static::assertNotSame($constructor_code, $http_status->getCode());
        static::assertSame($code, $http_status->getCode());
        static::assertSame($message, $http_status->getMessage());
    }

    public function testAutomaticMessage()
    {
        $code = 201;
        $expected_message = 'Created';

        $http_status = new HttpStatus($code);

        static::assertSame($code, $http_status->getCode());
        static::assertSame($expected_message, $http_status->getMessage());
    }

    public function testStringOutput()
    {
        // Set our manual test data
        $code = 404;
        $expected_string = '404 Not Found';

        // Create and echo our status
        $http_status = new HttpStatus($code);
        echo $http_status;

        $this->expectOutputString($expected_string);

        static::assertSame($expected_string, $http_status->getFormattedString());
    }

    public static function modernStatusCodeProvider(): array
    {
        return [
            [103, 'Early Hints'],
            [308, 'Permanent Redirect'],
            [422, 'Unprocessable Content'],
            [425, 'Too Early'],
            [428, 'Precondition Required'],
            [429, 'Too Many Requests'],
            [431, 'Request Header Fields Too Large'],
            [451, 'Unavailable For Legal Reasons'],
            [511, 'Network Authentication Required'],
        ];
    }

    #[DataProvider('modernStatusCodeProvider')]
    public function testModernStatusCodesHaveMessages(int $code, string $message)
    {
        static::assertSame($message, HttpStatus::getMessageFromCode($code));
        // Used to be just e.g. "429", without a reason phrase
        static::assertSame($code . ' ' . $message, (string) new HttpStatus($code));
    }

    public static function invalidStatusCodeProvider(): array
    {
        return [
            'zero' => [0],
            'negative' => [-200],
            'below range' => [99],
            'above range' => [600],
            'four digits' => [1000],
            'non-numeric string' => ['abc'],
        ];
    }

    #[DataProvider('invalidStatusCodeProvider')]
    public function testInvalidCodeThrows(mixed $code)
    {
        $this->expectException(InvalidArgumentException::class);

        new HttpStatus($code);
    }

    public function testInvalidCodeViaSetterThrows()
    {
        $http_status = new HttpStatus(200);

        try {
            $http_status->setCode(999);
            static::fail('Expected an InvalidArgumentException');
        } catch (InvalidArgumentException) {
            // The previous code is kept
            static::assertSame(200, $http_status->getCode());
        }
    }

    public function testIsValidCode()
    {
        static::assertTrue(HttpStatus::isValidCode(100));
        static::assertTrue(HttpStatus::isValidCode(599));
        static::assertFalse(HttpStatus::isValidCode(99));
        static::assertFalse(HttpStatus::isValidCode(600));
    }
}
