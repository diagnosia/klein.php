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

use Klein\AbstractRouteFactory;

/**
 * AbstractRouteFactoryTest
 */
class AbstractRouteFactoryTest extends AbstractKleinTestCase
{
    /**
     * Helpers
     */

    protected function getDefaultMethodsToMock()
    {
        return [
            'build',
        ];
    }

    protected function getMockForFactory()
    {
        return $this->createMock(AbstractRouteFactory::class);
    }

    protected function getMockBuilderForFactory(array|null $methods_to_mock = null)
    {
        $methods_to_mock =
            $methods_to_mock === null || $methods_to_mock === [] ? $this->getDefaultMethodsToMock() : $methods_to_mock;

        return $this->getMockBuilder(AbstractRouteFactory::class)->onlyMethods($methods_to_mock);
    }

    /**
     * Tests
     */

    public function testNamespaceGetSet()
    {
        static::markTestSkipped('not working yet');

        // Test data
        $test_namespace = '/users';

        // Empty constructor
        $factory = $this->getMockForFactory();

        static::assertNull($factory->getNamespace());

        // Set in constructor
        $factory = $this->getMockBuilderForFactory()->setConstructorArgs([$test_namespace])->getMock();

        static::assertSame($test_namespace, $factory->getNamespace());

        // Set in method
        $factory = $this->getMockForFactory();
        $factory->setNamespace($test_namespace);

        static::assertSame($test_namespace, $factory->getNamespace());
    }

    public function testAppendNamespace()
    {
        static::markTestSkipped('not working yet');

        // Test data
        $test_namespace = '/users';
        $test_namespace_append = '/names';

        $factory = $this->getMockForFactory();
        $factory->setNamespace($test_namespace);
        $factory->appendNamespace($test_namespace_append);

        static::assertSame($test_namespace . $test_namespace_append, $factory->getNamespace());
    }
}
