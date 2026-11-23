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

use Klein\DataCollection\HeaderDataCollection;
use Klein\Tests\AbstractKleinTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * HeaderDataCollectionTest
 */
class HeaderDataCollectionTest extends AbstractKleinTestCase
{
    /**
     * Non existent key in the sample data
     */
    protected static string $nonexistent_key = 'non-standard-header';

    /*
     * Data Providers and Methods
     */

    /**
     * Quickly makes sure that no sample data arrays
     * have any keys that match the "nonexistent_key"
     */
    protected static function prepareSampleData(array &$sample_data): void
    {
        if (($sample_data[static::$nonexistent_key] ?? null) !== null) {
            unset($sample_data[static::$nonexistent_key]);
        }

        foreach ($sample_data as &$data) {
            if (!is_array($data)) {
                continue;
            }

            static::prepareSampleData($data);
        }

        reset($sample_data);
    }

    /**
     * Sample data provider
     */
    public static function sampleDataProvider(): array
    {
        // Populate our sample data
        $sample_data = [
            'HOST' => 'localhost:8000',
            'CONNECTION' => 'keep-alive',
            'CONTENT_LENGTH' => '137',
            'USER_AGENT' =>
                'Mozilla/5.0 (Windows NT 6.2; WOW64) AppleWebKit/537.31'
                    . ' (KHTML, like Gecko) Chrome/26.0.1410.64 Safari/537.31',
            'CACHE_CONTROL' => 'no-cache',
            'ORIGIN' => 'chrome-extension://fdmmgilgnpjigdojojpjoooidkmcomcm',
            'AUTHORIZATION' => 'Basic MTIzOjQ1Ng==',
            'CONTENT_TYPE' => 'multipart/form-data; boundary=----WebKitFormBoundaryDhtDHBYppyHdrZe7',
            'ACCEPT' => '*/*',
            'ACCEPT_ENCODING' => 'gzip,deflate,sdch',
            'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
            'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
        ];

        static::prepareSampleData($sample_data);

        $data_collection = new HeaderDataCollection($sample_data);

        return [
            [$sample_data, $data_collection],
        ];
    }

    /*
     * Tests
     */

    #[DataProvider('sampleDataProvider')]
    public function testConstructorCorrectlyFormatted($sample_data, $data_collection)
    {
        static::assertNotSame($sample_data, $data_collection->all());
        static::assertArrayNotHasKey('HOST', $data_collection->all());
        static::assertContains('localhost:8000', $data_collection->all());
    }

    public function testGetSetNormalization()
    {
        $data_collection = new HeaderDataCollection();

        static::assertIsInt($data_collection->getNormalization());

        $data_collection->setNormalization(HeaderDataCollection::NORMALIZE_TRIM & HeaderDataCollection::NORMALIZE_CASE);

        static::assertSame(
            HeaderDataCollection::NORMALIZE_TRIM & HeaderDataCollection::NORMALIZE_CASE,
            $data_collection->getNormalization(),
        );
    }

    #[DataProvider('sampleDataProvider')]
    public function testGet($sample_data, $data_collection)
    {
        $default = 'WOOT!';

        static::assertSame($sample_data['USER_AGENT'], $data_collection->get('user-agent'));
        static::assertSame($default, $data_collection->get(static::$nonexistent_key, $default));
        static::assertNull($data_collection->get(static::$nonexistent_key));
    }

    public function testSet()
    {
        // Test data
        $data = [
            'DOG_NAME' => 'cooper',
        ];

        // Create our collection with NO data
        $data_collection = new HeaderDataCollection();

        // Set our data from our test data
        $data_collection->set(key($data), current($data));

        // Make sure the set worked, but the key is different
        static::assertSame(current($data), $data_collection->get(key($data)));
        static::assertArrayNotHasKey(key($data), $data_collection->all());
    }

    #[DataProvider('sampleDataProvider')]
    public function testExists($sample_data, $data_collection)
    {
        // Make sure the set worked, but the key is different
        static::assertTrue($data_collection->exists('HOST'));
        static::assertFalse($data_collection->exists(static::$nonexistent_key));
        static::assertArrayNotHasKey('HOST', $data_collection->all());
    }

    #[DataProvider('sampleDataProvider')]
    public function testRemove($sample_data, $data_collection)
    {
        static::assertTrue($data_collection->exists('HOST'));
        static::assertArrayNotHasKey('HOST', $data_collection->all());

        $data_collection->remove('HOST');

        static::assertFalse($data_collection->exists('HOST'));
    }

    public function testNormalizeKeyDelimiters()
    {
        // Test data
        $header = 'Access_Control Allow-Origin';

        $canonicalized_key = HeaderDataCollection::normalizeKeyDelimiters($header);

        static::assertNotSame($header, $canonicalized_key);

        static::assertSame('Access-Control-Allow-Origin', $canonicalized_key);
    }

    public function testCanonicalizeKey()
    {
        // Test data
        $header = 'content-TYPE';

        $canonicalized_key = HeaderDataCollection::canonicalizeKey($header);

        static::assertNotSame($header, $canonicalized_key);

        static::assertSame('Content-Type', $canonicalized_key);
    }

    public function testNameNormalizing()
    {
        // Test data
        $header = 'content_TYPE';

        // Ignore our deprecation error
        $old_error_val = error_reporting();
        error_reporting(E_ALL ^ E_USER_DEPRECATED);

        $normalized_key = HeaderDataCollection::normalizeKeyDelimiters(strtolower($header));
        $normalized_key_without_canonicalization = HeaderDataCollection::normalizeKeyDelimiters($header);

        error_reporting($old_error_val);

        static::assertNotSame($header, $normalized_key);

        static::assertSame('content-type', $normalized_key);
        static::assertSame('content-TYPE', $normalized_key_without_canonicalization);
    }
}
