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

namespace Klein\Tests\DataCollection;

use Klein\DataCollection\ResponseCookieDataCollection;
use Klein\ResponseCookie;
use Klein\Tests\AbstractKleinTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * ResponseCookieDataCollectionTest
 */
class ResponseCookieDataCollectionTest extends AbstractKleinTestCase
{
    /*
     * Data Providers and Methods
     */

    /**
     * Sample data provider
     */
    public static function sampleDataProvider(): array
    {
        $sample_cookie = new ResponseCookie('Trevor', 'is a programmer', 3600, '/', 'example.com', false, false);

        $sample_other_cookie = new ResponseCookie('Chris', 'is a boss', 60, '/app/', 'github.com', true, true);

        return [
            [$sample_cookie, $sample_other_cookie],
        ];
    }

    /*
     * Tests
     */

    #[DataProvider('sampleDataProvider')]
    public function testSet($sample_cookie, $sample_other_cookie)
    {
        // Create our collection with NO data
        $data_collection = new ResponseCookieDataCollection();

        // Set our data from our test data
        $data_collection->set('first', $sample_cookie);

        static::assertSame($sample_cookie, $data_collection->get('first'));
        static::assertTrue($data_collection->get('first') instanceof ResponseCookie);
    }

    public function testSetStringConvertsToCookie()
    {
        // Create our collection with NO data
        $data_collection = new ResponseCookieDataCollection();

        // Set our data from our test data
        $data_collection->set('first', 'value');

        static::assertNotSame('value', $data_collection->get('first'));
        static::assertTrue($data_collection->get('first') instanceof ResponseCookie);
    }

    #[DataProvider('sampleDataProvider')]
    public function testConstructorRoutesThroughSet($sample_cookie, $sample_other_cookie)
    {
        $array_of_cookie_instances = [
            $sample_cookie,
            $sample_other_cookie,
            new ResponseCookie('test'),
        ];

        // Create our collection with NO data
        $data_collection = new ResponseCookieDataCollection($array_of_cookie_instances);
        static::assertSame($array_of_cookie_instances, $data_collection->all());

        foreach ($data_collection as $cookie) {
            static::assertTrue($cookie instanceof ResponseCookie);
        }
    }
}
