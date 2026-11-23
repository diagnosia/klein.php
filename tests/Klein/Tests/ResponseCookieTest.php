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
use Klein\ResponseCookie;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * ResponseCookieTest
 */
class ResponseCookieTest extends AbstractKleinTestCase
{
    /*
     * Data Providers and Methods
     */

    /**
     * Sample data provider
     */
    public static function sampleDataProvider(): array
    {
        // Populate our sample data
        $default_sample_data = [
            'name' => '',
            'value' => '',
            'expire' => 0,
            'path' => '',
            'domain' => '',
            'secure' => false,
            'http_only' => false,
        ];

        $sample_data = [
            'name' => 'Trevor',
            'value' => 'is a programmer',
            'expire' => 3600,
            'path' => '/',
            'domain' => 'example.com',
            'secure' => false,
            'http_only' => false,
        ];

        $sample_data_other = [
            'name' => 'Chris',
            'value' => 'is a boss',
            'expire' => 60,
            'path' => '/app/',
            'domain' => 'github.com',
            'secure' => true,
            'http_only' => true,
        ];

        return [
            [$default_sample_data, $sample_data, $sample_data_other],
        ];
    }

    /*
     * Tests
     */

    #[DataProvider('sampleDataProvider')]
    public function testNameGetSet($defaults, $sample_data, $sample_data_other)
    {
        $response_cookie = new ResponseCookie($sample_data['name']);

        static::assertSame($sample_data['name'], $response_cookie->getName());
        static::assertIsString($response_cookie->getName());

        $response_cookie->setName($sample_data_other['name']);

        static::assertSame($sample_data_other['name'], $response_cookie->getName());
        static::assertIsString($response_cookie->getName());
    }

    #[DataProvider('sampleDataProvider')]
    public function testValueGetSet($defaults, $sample_data, $sample_data_other)
    {
        $response_cookie = new ResponseCookie($defaults['name'], $sample_data['value']);

        static::assertSame($sample_data['value'], $response_cookie->getValue());
        static::assertIsString($response_cookie->getValue());

        $response_cookie->setValue($sample_data_other['value']);

        static::assertSame($sample_data_other['value'], $response_cookie->getValue());
        static::assertIsString($response_cookie->getValue());
    }

    #[DataProvider('sampleDataProvider')]
    public function testExpireGetSet($defaults, $sample_data, $sample_data_other)
    {
        $response_cookie = new ResponseCookie($defaults['name'], null, $sample_data['expire']);

        static::assertSame($sample_data['expire'], $response_cookie->getExpire());
        static::assertIsInt($response_cookie->getExpire());

        $response_cookie->setExpire($sample_data_other['expire']);

        static::assertSame($sample_data_other['expire'], $response_cookie->getExpire());
        static::assertIsInt($response_cookie->getExpire());
    }

    #[DataProvider('sampleDataProvider')]
    public function testPathGetSet($defaults, $sample_data, $sample_data_other)
    {
        $response_cookie = new ResponseCookie($defaults['name'], null, null, $sample_data['path']);

        static::assertSame($sample_data['path'], $response_cookie->getPath());
        static::assertIsString($response_cookie->getPath());

        $response_cookie->setPath($sample_data_other['path']);

        static::assertSame($sample_data_other['path'], $response_cookie->getPath());
        static::assertIsString($response_cookie->getPath());
    }

    #[DataProvider('sampleDataProvider')]
    public function testDomainGetSet($defaults, $sample_data, $sample_data_other)
    {
        $response_cookie = new ResponseCookie($defaults['name'], null, null, null, $sample_data['domain']);

        static::assertSame($sample_data['domain'], $response_cookie->getDomain());
        static::assertIsString($response_cookie->getDomain());

        $response_cookie->setDomain($sample_data_other['domain']);

        static::assertSame($sample_data_other['domain'], $response_cookie->getDomain());
        static::assertIsString($response_cookie->getDomain());
    }

    #[DataProvider('sampleDataProvider')]
    public function testSecureGetSet($defaults, $sample_data, $sample_data_other)
    {
        $response_cookie = new ResponseCookie($defaults['name'], null, null, null, null, $sample_data['secure']);

        static::assertSame($sample_data['secure'], $response_cookie->getSecure());
        static::assertIsBool($response_cookie->getSecure());

        $response_cookie->setSecure($sample_data_other['secure']);

        static::assertSame($sample_data_other['secure'], $response_cookie->getSecure());
        static::assertIsBool($response_cookie->getSecure());
    }

    #[DataProvider('sampleDataProvider')]
    public function testHttpOnlyGetSet($defaults, $sample_data, $sample_data_other)
    {
        $response_cookie = new ResponseCookie(
            $defaults['name'],
            null,
            null,
            null,
            null,
            false,
            $sample_data['http_only'],
        );

        static::assertSame($sample_data['http_only'], $response_cookie->getHttpOnly());
        static::assertIsBool($response_cookie->getHttpOnly());

        $response_cookie->setHttpOnly($sample_data_other['http_only']);

        static::assertSame($sample_data_other['http_only'], $response_cookie->getHttpOnly());
        static::assertIsBool($response_cookie->getHttpOnly());
    }

    public function testNonStringNameAndValueAreCast()
    {
        $response_cookie = new ResponseCookie(123, 5);

        static::assertSame('123', $response_cookie->getName());
        static::assertSame('5', $response_cookie->getValue());

        $response_cookie->setValue(1.5);
        static::assertSame('1.5', $response_cookie->getValue());

        $response_cookie->setValue(false);
        static::assertSame('', $response_cookie->getValue());

        $response_cookie->setValue(null);
        static::assertNull($response_cookie->getValue());
    }

    public function testNumericStringExpireIsCast()
    {
        $response_cookie = new ResponseCookie('name', null, '3600');

        static::assertSame(3600, $response_cookie->getExpire());

        $response_cookie->setExpire('60');
        static::assertSame(60, $response_cookie->getExpire());
    }

    public function testNonNumericStringExpireThrows()
    {
        $this->expectException(InvalidArgumentException::class);

        new ResponseCookie('name', null, 'tomorrow');
    }

    public function testSameSiteGetSet()
    {
        $response_cookie = new ResponseCookie('name');

        static::assertNull($response_cookie->getSameSite());

        // Normalized to the canonical spelling
        static::assertSame('Strict', $response_cookie->setSameSite('strict')->getSameSite());
        static::assertSame('Lax', $response_cookie->setSameSite('LAX')->getSameSite());
        static::assertSame(
            'None',
            new ResponseCookie('name', null, null, null, null, true, false, 'None')->getSameSite(),
        );
        static::assertNull($response_cookie->setSameSite(null)->getSameSite());
    }

    public function testInvalidSameSiteThrows()
    {
        $this->expectException(InvalidArgumentException::class);

        new ResponseCookie('name', null, null, null, null, false, false, 'sometimes');
    }
}
