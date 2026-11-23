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

use Klein\DataCollection\DataCollection;
use Klein\Tests\AbstractKleinTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;

/**
 * DataCollectionTest
 */
class DataCollectionTest extends AbstractKleinTestCase
{
    /**
     * Non existent key in the sample data
     */
    protected static string $nonexistent_key = 'key-name-doesnt-exist';

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

    public static function sampleDataProvider(): array
    {
        // Populate our sample data
        $sample_data = [
            'id' => 1337,
            'name' => [
                'first' => 'Trevor',
                'last' => 'Suarez',
            ],
            'float' => 13.37,
            'thing' => new stdClass(),
        ];

        static::prepareSampleData($sample_data);

        $data_collection = new DataCollection($sample_data);

        return [
            [$sample_data, $data_collection],
        ];
    }

    /**
     * Totally different sample data provider
     */
    public function totallyDifferentSampleDataProvider(): array
    {
        // Populate our sample data
        $totally_different_sample_data = [
            '_why' => 'the lucky stiff',
            'php' => 'has become beautiful',
            'yay' => 'life is very good. :)',
        ];

        $this->prepareSampleData($totally_different_sample_data);

        return [
            [$totally_different_sample_data],
        ];
    }

    /*
     * Tests
     */

    #[DataProvider('sampleDataProvider')]
    public function testKeys($sample_data, $data_collection)
    {
        // Test basic data similarity
        static::assertSame(array_keys($sample_data), $data_collection->keys());

        // Create mask
        $mask = ['float', static::$nonexistent_key];

        static::assertContains($mask[0], $data_collection->keys($mask));
        static::assertContains($mask[1], $data_collection->keys($mask));
        static::assertNotContains(key($sample_data), $data_collection->keys($mask));

        // Test more "magical" way of inputting mask
        static::assertContains($mask[0], $data_collection->keys($mask[0], $mask[1]));
        static::assertContains($mask[1], $data_collection->keys($mask[0], $mask[1]));
        static::assertNotContains(key($sample_data), $data_collection->keys($mask[0], $mask[1]));

        // Test not filling will nulls
        static::assertContains($mask[0], $data_collection->keys($mask, false));
        static::assertNotContains($mask[1], $data_collection->keys($mask, false));
    }

    #[DataProvider('sampleDataProvider')]
    public function testAll($sample_data, $data_collection)
    {
        // Test basic data similarity
        static::assertSame($sample_data, $data_collection->all());

        // Create mask
        $mask = ['float', static::$nonexistent_key];

        static::assertArrayHasKey($mask[0], $data_collection->all($mask));
        static::assertArrayHasKey($mask[1], $data_collection->all($mask));
        static::assertArrayNotHasKey('id', $data_collection->all($mask));
        static::assertArrayNotHasKey('name', $data_collection->all($mask));

        // Test more "magical" way of inputting mask
        static::assertArrayHasKey($mask[0], $data_collection->all($mask[0], $mask[1]));
        static::assertArrayHasKey($mask[1], $data_collection->all($mask[0], $mask[1]));
        static::assertArrayNotHasKey('id', $data_collection->all($mask[0], $mask[1]));
        static::assertArrayNotHasKey('name', $data_collection->all($mask[0], $mask[1]));

        // Test not filling will nulls
        static::assertArrayHasKey($mask[0], $data_collection->all($mask, false));
        static::assertArrayNotHasKey($mask[1], $data_collection->all($mask, false));
    }

    #[DataProvider('sampleDataProvider')]
    public function testGet($sample_data, $data_collection)
    {
        $default = 'WOOT!';

        static::assertSame($sample_data['id'], $data_collection->get('id'));
        static::assertSame($default, $data_collection->get(static::$nonexistent_key, $default));
        static::assertNull($data_collection->get(static::$nonexistent_key));
    }

    public function testSet()
    {
        // Test data
        $data = [
            'dog' => 'cooper',
        ];

        // Create our collection with NO data
        $data_collection = new DataCollection();

        // Make sure its first empty
        static::assertSame([], $data_collection->all());

        // Set our data from our test data
        $return_val = $data_collection->set(key($data), current($data));

        // Make sure the set worked
        static::assertSame(current($data), $data_collection->get(key($data)));

        // Make sure it returned the instance during "set"
        static::assertEquals($return_val, $data_collection);
        static::assertSame($return_val, $data_collection);
    }

    #[DataProvider('sampleDataProvider')]
    public function testReplace($sample_data, $data_collection)
    {
        $totally_different_sample_data = current(current($this->totallyDifferentSampleDataProvider()));

        $data_collection->replace($totally_different_sample_data);

        static::assertNotSame($sample_data, $totally_different_sample_data);
        static::assertNotSame($sample_data, $data_collection->all());
        static::assertSame($totally_different_sample_data, $data_collection->all());
    }

    #[DataProvider('sampleDataProvider')]
    public function testMerge($sample_data, $data_collection)
    {
        $totally_different_sample_data = current(current($this->totallyDifferentSampleDataProvider()));

        $merged_data = array_merge($sample_data, $totally_different_sample_data);

        $data_collection->merge($totally_different_sample_data);

        static::assertNotSame($sample_data, $totally_different_sample_data);
        static::assertNotSame($sample_data, $data_collection->all());
        static::assertNotSame($totally_different_sample_data, $data_collection->all());
        static::assertSame($merged_data, $data_collection->all());
    }

    #[DataProvider('sampleDataProvider')]
    public function testMergeHard($sample_data, $data_collection)
    {
        $totally_different_sample_data = current(current($this->totallyDifferentSampleDataProvider()));

        $replaced_data = array_replace($sample_data, $totally_different_sample_data);

        $data_collection->merge($totally_different_sample_data, true);

        static::assertNotSame($sample_data, $totally_different_sample_data);
        static::assertNotSame($sample_data, $data_collection->all());
        static::assertNotSame($totally_different_sample_data, $data_collection->all());
        static::assertSame($replaced_data, $data_collection->all());
    }

    #[DataProvider('sampleDataProvider')]
    public function testExists($sample_data, $data_collection)
    {
        static::assertTrue($data_collection->exists('id'));
        static::assertFalse($data_collection->exists(static::$nonexistent_key));
    }

    #[DataProvider('sampleDataProvider')]
    public function testRemove($sample_data, $data_collection)
    {
        static::assertTrue($data_collection->exists('id'));

        $data_collection->remove('id');

        static::assertFalse($data_collection->exists('id'));
    }

    #[DataProvider('sampleDataProvider')]
    public function testClear($sample_data, $data_collection)
    {
        $original_data = $data_collection->all();

        $data_collection->clear();

        static::assertNotSame($original_data, $data_collection->all());
        static::assertSame([], $data_collection->all());
    }

    #[DataProvider('sampleDataProvider')]
    public function testMagicGet($sample_data, $data_collection)
    {
        static::assertSame($sample_data['float'], $data_collection->float);
        static::assertNull($data_collection->{static::$nonexistent_key});
    }

    public function testMagicSet()
    {
        // Test data
        $data = [
            'dog' => 'cooper',
        ];

        // Create our collection with NO data
        $data_collection = new DataCollection();

        // Set our data from our test data
        $data_collection->{key($data)} = current($data);

        // Make sure the set worked
        static::assertSame(current($data), $data_collection->get(key($data)));
    }

    #[DataProvider('sampleDataProvider')]
    public function testMagicIsset($sample_data, $data_collection)
    {
        // @mago-expect lint:no-isset (tests the magic __isset()/offsetExists())
        static::assertTrue(isset($data_collection->id));
        // @mago-expect lint:no-isset (tests the magic __isset()/offsetExists())
        static::assertTrue(isset($data_collection->name));
        // @mago-expect lint:no-isset (tests the magic __isset()/offsetExists())
        static::assertTrue(isset($data_collection->float));
        // @mago-expect lint:no-isset (tests the magic __isset()/offsetExists())
        static::assertFalse(isset($data_collection->{static::$nonexistent_key}));
    }

    #[DataProvider('sampleDataProvider')]
    public function testMagicUnset($sample_data, $data_collection)
    {
        // @mago-expect lint:no-isset (tests the magic __isset()/offsetExists())
        static::assertTrue(isset($data_collection->id));

        unset($data_collection->id);

        // @mago-expect lint:no-isset (tests the magic __isset()/offsetExists())
        static::assertFalse(isset($data_collection->id));
    }

    #[DataProvider('sampleDataProvider')]
    public function testIteratorAggregate($sample_data, $data_collection)
    {
        $filled_data = [];

        foreach ($data_collection as $key => $data) {
            $filled_data[$key] = $data;
        }

        static::assertSame($filled_data, $sample_data);
    }

    #[DataProvider('sampleDataProvider')]
    public function testArrayAccessGet($sample_data, $data_collection)
    {
        static::assertSame($sample_data['float'], $data_collection['float']);
        static::assertNull($data_collection[static::$nonexistent_key]);
    }

    public function testArrayAccessSet()
    {
        // Test data
        $data = [
            'dog' => 'cooper',
        ];

        // Create our collection with NO data
        $data_collection = new DataCollection();

        // Set our data from our test data
        $data_collection[key($data)] = current($data);

        // Make sure the set worked
        static::assertSame(current($data), $data_collection->get(key($data)));
    }

    #[DataProvider('sampleDataProvider')]
    public function testArrayAccessIsset($sample_data, $data_collection)
    {
        // @mago-expect lint:no-isset (tests the magic __isset()/offsetExists())
        static::assertTrue(isset($data_collection['id']));
        // @mago-expect lint:no-isset (tests the magic __isset()/offsetExists())
        static::assertFalse(isset($data_collection[static::$nonexistent_key]));
    }

    #[DataProvider('sampleDataProvider')]
    public function testArrayAccessUnset($sample_data, $data_collection)
    {
        // @mago-expect lint:no-isset (tests the magic __isset()/offsetExists())
        static::assertTrue(isset($data_collection['id']));

        unset($data_collection['id']);

        // @mago-expect lint:no-isset (tests the magic __isset()/offsetExists())
        static::assertFalse(isset($data_collection['id']));
    }

    #[DataProvider('sampleDataProvider')]
    public function testCount($sample_data, $data_collection)
    {
        static::assertSame(count($sample_data), $data_collection->count());
        static::assertGreaterThan(1, $data_collection->count());
    }
}
