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

use Exception;
use Klein\App;
use Klein\DataCollection\RouteCollection;
use Klein\Exceptions\DispatchHaltedException;
use Klein\Exceptions\HttpExceptionInterface;
use Klein\Exceptions\UnhandledException;
use Klein\Klein;
use Klein\Request;
use Klein\Response;
use Klein\Route;
use Klein\ServiceProvider;
use OutOfBoundsException;

/**
 * KleinTest
 */
class KleinTest extends AbstractKleinTestCase
{
    /**
     * Constants
     */

    const string TEST_CALLBACK_MESSAGE = 'yay';

    /**
     * Helpers
     */

    protected function getTestCallable($message = self::TEST_CALLBACK_MESSAGE)
    {
        return static fn() => $message;
    }

    /**
     * Tests
     */

    public function testConstructor()
    {
        $klein = new Klein();

        static::assertNotNull($klein);
        static::assertTrue($klein instanceof Klein);
    }

    public function testService()
    {
        $service = $this->klein_app->service();

        static::assertNotNull($service);
        static::assertTrue($service instanceof ServiceProvider);
    }

    public function testApp()
    {
        $app = $this->klein_app->app();

        static::assertNotNull($app);
        static::assertTrue($app instanceof App);
    }

    public function testRoutes()
    {
        $routes = $this->klein_app->routes();

        static::assertNotNull($routes);
        static::assertTrue($routes instanceof RouteCollection);
    }

    public function testRequest()
    {
        $this->klein_app->dispatch();

        $request = $this->klein_app->request();

        static::assertNotNull($request);
        static::assertTrue($request instanceof Request);
    }

    public function testResponse()
    {
        $this->klein_app->dispatch();

        $response = $this->klein_app->response();

        static::assertNotNull($response);
        static::assertTrue($response instanceof Response);
    }

    public function testRespond()
    {
        $route = $this->klein_app->respond($this->getTestCallable());

        $object_id = spl_object_hash($route);

        static::assertNotNull($route);
        static::assertTrue($route instanceof Route);
        static::assertTrue($this->klein_app->routes()->exists($object_id));
        static::assertSame($route, $this->klein_app->routes()->get($object_id));
    }

    public function testWith()
    {
        // Test data
        $test_namespace = '/test/namespace';
        $passed_context = null;

        $this->klein_app->with($test_namespace, static function ($context) use (&$passed_context) {
            $passed_context = $context;
        });

        static::assertTrue($passed_context instanceof Klein);
    }

    public function testWithRestoresNamespaceOnException()
    {
        try {
            $this->klein_app->with('/outer', static function ($klein) {
                $klein->with('/inner', static function () {
                    throw new Exception('route definition failed');
                });
            });
            static::fail('Expected the exception to propagate');
        } catch (Exception $e) {
            static::assertSame('route definition failed', $e->getMessage());
        }

        // Used to become "/outer/inner/after"
        $route = $this->klein_app->respond('/after', static function () {});

        static::assertSame('/after', $route->getPath());
    }

    public function testWithStringCallable()
    {
        // Test data
        $test_namespace = '/test/namespace';

        $this->klein_app->with($test_namespace, 'test_num_args_wrapper');

        $this->expectOutputString('1');
    }

    /**
     * Weird PHPUnit bug is causing scope errors for the
     * isolated process tests, unless I run this also in an
     * isolated process
     *
     * @runInSeparateProcess
     */
    public function testWithUsingFileInclude()
    {
        // Test data
        $test_namespace = '/test/namespace';
        $test_routes_include = __DIR__ . '/routes/random.php';

        // Test file include
        static::assertEmpty($this->klein_app->routes()->all());
        $this->klein_app->with($test_namespace, $test_routes_include);

        static::assertNotEmpty($this->klein_app->routes()->all());

        $all_routes = array_values($this->klein_app->routes()->all());
        $test_route = $all_routes[0];

        static::assertTrue($test_route instanceof Route);
        static::assertSame($test_namespace . '/?', $test_route->getPath());
    }

    public function testDispatch()
    {
        $request = new Request();
        $response = new Response();

        $this->klein_app->dispatch($request, $response);

        static::assertSame($request, $this->klein_app->request());
        static::assertSame($response, $this->klein_app->response());
    }

    public function testGetPathFor()
    {
        // Test data
        $test_path = '/test';
        $test_name = 'Test Route Thing';

        $route = new Route($this->getTestCallable());
        $route->setPath($test_path);
        $route->setName($test_name);

        $this->klein_app->routes()->addRoute($route);

        // Make sure it fails if not prepared
        try {
            $this->klein_app->getPathFor($test_name);
        } catch (Exception $e) {
            static::assertTrue($e instanceof OutOfBoundsException);
        }

        $this->klein_app->routes()->prepareNamed();

        $returned_path = $this->klein_app->getPathFor($test_name);

        static::assertNotEmpty($returned_path);
        static::assertSame($test_path, $returned_path);
    }

    public function testOnErrorWithStringCallables()
    {
        $this->klein_app->onError('test_num_args_wrapper');

        $this->klein_app->respond(static function ($request, $response, $service) {
            throw new Exception('testing');
        });

        static::assertSame('4', $this->dispatchAndReturnOutput());
    }

    public function testOnErrorWithBadCallables()
    {
        $this->klein_app->onError('this_function_doesnt_exist');

        $this->klein_app->respond(static function ($request, $response, $service) {
            throw new Exception('testing');
        });

        static::assertEmpty($this->klein_app->service()->flashes());

        static::assertSame('', $this->dispatchAndReturnOutput());

        static::assertNotEmpty($this->klein_app->service()->flashes());

        // Clean up
        session_destroy();
    }

    public function testOnErrorRedirectFlashesEscapedMessageOnly()
    {
        // A non-callable error "callback" is a URL to redirect to, with the error flashed
        $this->klein_app->onError('/error-page');

        $this->klein_app->respond(static function () {
            throw new Exception('<b>100% broken</b>, 50% done');
        });

        $this->klein_app->dispatch(null, null, false);

        // Escaped, with the "%" signs intact and without the exception class or stack trace
        static::assertSame(['&lt;b&gt;100% broken&lt;/b&gt;, 50% done'], $this->klein_app->service()->flashes('info'));
        static::assertSame(302, $this->klein_app->response()->code());
        static::assertSame('/error-page', $this->klein_app->response()->headers()->get('Location'));

        // Clean up
        session_destroy();
    }

    public function testOnHttpError()
    {
        // Create expected arguments
        $num_of_args = 0;
        $expected_arguments = [
            'code' => null,
            'klein' => null,
            'matched' => null,
            'methods_matched' => null,
            'exception' => null,
        ];

        $this->klein_app->onHttpError(static function ($code, $klein, $matched, $methods_matched, $exception) use (
            &$num_of_args,
            &$expected_arguments,
        ) {
            // Keep track of our arguments
            $num_of_args = func_num_args();
            $expected_arguments['code'] = $code;
            $expected_arguments['klein'] = $klein;
            $expected_arguments['matched'] = $matched;
            $expected_arguments['methods_matched'] = $methods_matched;
            $expected_arguments['exception'] = $exception;

            $klein->response()->body($code . ' error');
        });

        $this->klein_app->dispatch(null, null, false);

        static::assertSame('404 error', $this->klein_app->response()->body());

        static::assertSame(count($expected_arguments), $num_of_args);

        static::assertTrue(is_int($expected_arguments['code']));
        static::assertTrue($expected_arguments['klein'] instanceof Klein);
        static::assertTrue($expected_arguments['matched'] instanceof RouteCollection);
        static::assertTrue(is_array($expected_arguments['methods_matched']));
        static::assertTrue($expected_arguments['exception'] instanceof HttpExceptionInterface);

        static::assertSame($expected_arguments['klein'], $this->klein_app);
    }

    public function testOnHttpErrorWithStringCallables()
    {
        $this->klein_app->onHttpError('test_num_args_wrapper');

        static::assertSame('5', $this->dispatchAndReturnOutput());
    }

    public function testOnHttpErrorWithBadCallables()
    {
        $this->klein_app->onError('this_function_doesnt_exist');

        static::assertSame('', $this->dispatchAndReturnOutput());
    }

    public function testAfterDispatch()
    {
        $this->klein_app->afterDispatch(static function ($klein) {
            $klein->response()->body('after callbacks!');
        });

        $this->klein_app->dispatch(null, null, false);

        static::assertSame('after callbacks!', $this->klein_app->response()->body());
    }

    public function testAfterDispatchWithMultipleCallbacks()
    {
        $this->klein_app->afterDispatch(static function ($klein) {
            $klein->response()->body('after callbacks!');
        });

        $this->klein_app->afterDispatch(static function ($klein) {
            $klein->response()->body('whatever');
        });

        $this->klein_app->dispatch(null, null, false);

        static::assertSame('whatever', $this->klein_app->response()->body());
    }

    public function testAfterDispatchWithStringCallables()
    {
        $this->klein_app->afterDispatch('test_response_edit_wrapper');

        $this->klein_app->dispatch(null, null, false);

        static::assertSame('after callbacks!', $this->klein_app->response()->body());
    }

    public function testAfterDispatchWithBadCallables()
    {
        $this->klein_app->afterDispatch('this_function_doesnt_exist');

        $this->klein_app->dispatch();

        $this->expectOutputString('');
    }

    public function testAfterDispatchWithCallableThatThrowsException()
    {
        $this->expectException(UnhandledException::class);
        $this->klein_app->afterDispatch(static function ($klein) {
            throw new Exception('testing');
        });

        $this->klein_app->dispatch();

        static::assertSame(500, $this->klein_app->response()->code());
    }

    public function testErrorsWithNoCallbacks()
    {
        $this->expectException(UnhandledException::class);
        $this->klein_app->respond(static function ($request, $response, $service) {
            throw new Exception('testing');
        });

        $this->klein_app->dispatch();

        static::assertSame(500, $this->klein_app->response()->code());
    }

    public function testSkipThis()
    {
        try {
            $this->klein_app->skipThis();
        } catch (Exception $e) {
            static::assertTrue($e instanceof DispatchHaltedException);
            static::assertSame(DispatchHaltedException::SKIP_THIS, $e->getCode());
            static::assertSame(1, $e->getNumberOfSkips());
        }
    }

    public function testSkipNext()
    {
        $number_of_skips = 3;

        try {
            $this->klein_app->skipNext($number_of_skips);
        } catch (Exception $e) {
            static::assertTrue($e instanceof DispatchHaltedException);
            static::assertSame(DispatchHaltedException::SKIP_NEXT, $e->getCode());
            static::assertSame($number_of_skips, $e->getNumberOfSkips());
        }
    }

    public function testSkipRemaining()
    {
        try {
            $this->klein_app->skipRemaining();
        } catch (Exception $e) {
            static::assertTrue($e instanceof DispatchHaltedException);
            static::assertSame(DispatchHaltedException::SKIP_REMAINING, $e->getCode());
        }
    }

    public function testAbort()
    {
        $test_code = 503;

        $this->klein_app->respond(static function ($a, $b, $c, $d, $klein_app) use ($test_code) {
            $klein_app->abort($test_code);
        });

        try {
            $this->klein_app->dispatch();
        } catch (Exception $e) {
            static::assertTrue($e instanceof DispatchHaltedException);
            static::assertSame(DispatchHaltedException::SKIP_REMAINING, $e->getCode());
        }

        static::assertSame($test_code, $this->klein_app->response()->code());
        static::assertTrue($this->klein_app->response()->isLocked());
    }

    public function testOptions()
    {
        $route = $this->klein_app->options($this->getTestCallable());

        static::assertNotNull($route);
        static::assertTrue($route instanceof Route);
        static::assertSame('OPTIONS', $route->getMethod());
    }

    public function testHead()
    {
        $route = $this->klein_app->head($this->getTestCallable());

        static::assertNotNull($route);
        static::assertTrue($route instanceof Route);
        static::assertSame('HEAD', $route->getMethod());
    }

    public function testGet()
    {
        $route = $this->klein_app->get($this->getTestCallable());

        static::assertNotNull($route);
        static::assertTrue($route instanceof Route);
        static::assertSame('GET', $route->getMethod());
    }

    public function testPost()
    {
        $route = $this->klein_app->post($this->getTestCallable());

        static::assertNotNull($route);
        static::assertTrue($route instanceof Route);
        static::assertSame('POST', $route->getMethod());
    }

    public function testPut()
    {
        $route = $this->klein_app->put($this->getTestCallable());

        static::assertNotNull($route);
        static::assertTrue($route instanceof Route);
        static::assertSame('PUT', $route->getMethod());
    }

    public function testDelete()
    {
        $route = $this->klein_app->delete($this->getTestCallable());

        static::assertNotNull($route);
        static::assertTrue($route instanceof Route);
        static::assertSame('DELETE', $route->getMethod());
    }

    public function testPatch()
    {
        $route = $this->klein_app->patch($this->getTestCallable());

        static::assertNotNull($route);
        static::assertTrue($route instanceof Route);
        static::assertSame('PATCH', $route->getMethod());
    }
}
