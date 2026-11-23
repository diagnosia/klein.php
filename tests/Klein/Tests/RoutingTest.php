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
use Klein\Exceptions\HttpException;
use Klein\Exceptions\RoutePathCompilationException;
use Klein\Exceptions\UnhandledException;
use Klein\Klein;
use Klein\Request;
use Klein\Response;
use Klein\Route;
use Klein\ServiceProvider;
use Klein\Tests\Mocks\MockRequestFactory;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;

/**
 * RoutingTest
 */
class RoutingTest extends AbstractKleinTestCase
{
    public function testBasic()
    {
        $this->expectOutputString('x');

        $this->klein_app->respond('/', static function () {
            echo 'x';
        });
        $this->klein_app->respond('/something', static function () {
            echo 'y';
        });

        $this->klein_app->dispatch(MockRequestFactory::create('/'));
    }

    public function testCallable()
    {
        $this->expectOutputString('okok');

        $this->klein_app->respond('/', [__NAMESPACE__ . '\Mocks\TestClass', 'GET']);
        $this->klein_app->respond('/', __NAMESPACE__ . '\Mocks\TestClass::GET');

        $this->klein_app->dispatch(MockRequestFactory::create('/'));
    }

    public function testCallbackArguments()
    {
        // Create expected objects
        $expected_objects = [
            'request' => null,
            'response' => null,
            'service' => null,
            'app' => null,
            'klein' => null,
            'matched' => null,
            'methods_matched' => null,
        ];

        $this->klein_app->respond(static function ($a, $b, $c, $d, $e, $f, $g) use (&$expected_objects) {
            $expected_objects['request'] = $a;
            $expected_objects['response'] = $b;
            $expected_objects['service'] = $c;
            $expected_objects['app'] = $d;
            $expected_objects['klein'] = $e;
            $expected_objects['matched'] = $f;
            $expected_objects['methods_matched'] = $g;
        });

        $this->klein_app->dispatch();

        static::assertTrue($expected_objects['request'] instanceof Request);
        static::assertTrue($expected_objects['response'] instanceof Response);
        static::assertTrue($expected_objects['service'] instanceof ServiceProvider);
        static::assertTrue($expected_objects['app'] instanceof App);
        static::assertTrue($expected_objects['klein'] instanceof Klein);
        static::assertTrue($expected_objects['matched'] instanceof RouteCollection);
        static::assertTrue(is_array($expected_objects['methods_matched']));

        static::assertSame($expected_objects['request'], $this->klein_app->request());
        static::assertSame($expected_objects['response'], $this->klein_app->response());
        static::assertSame($expected_objects['service'], $this->klein_app->service());
        static::assertSame($expected_objects['app'], $this->klein_app->app());
        static::assertSame($expected_objects['klein'], $this->klein_app);
    }

    public function testAppReference()
    {
        $this->expectOutputString('ab');

        $this->klein_app->respond('/', static function ($request, $response, $service, $app) {
            $app->state = 'a';
        });
        $this->klein_app->respond('/', static function ($request, $response, $service, $app) {
            $app->state .= 'b';
        });
        $this->klein_app->respond('/', static function ($request, $response, $service, $app) {
            print $app->state;
        });

        $this->klein_app->dispatch(MockRequestFactory::create('/'));
    }

    public function testDispatchOutput()
    {
        $expected_output = [
            'returned1' => 'alright!',
            'returned2' => 'woot!',
        ];

        $this->klein_app->respond(static fn() => $expected_output['returned1']);
        $this->klein_app->respond(static fn() => $expected_output['returned2']);

        $this->klein_app->dispatch();

        // Expect our output to match our ECHO'd output
        $this->expectOutputString($expected_output['returned1'] . $expected_output['returned2']);

        // Make sure our response body matches the concatenation of what we returned in each callback
        static::assertSame(
            $expected_output['returned1'] . $expected_output['returned2'],
            $this->klein_app->response()->body(),
        );
    }

    public function testDispatchOutputNotSent()
    {
        $this->klein_app->respond(static fn() => 'test output');

        $this->klein_app->dispatch(null, null, false);

        $this->expectOutputString('');

        static::assertSame('test output', $this->klein_app->response()->body());
    }

    public function testDispatchOutputCaptured()
    {
        $expected_output = [
            'echoed' => 'yup',
            'returned' => 'nope',
        ];

        $this->klein_app->respond(static function () use ($expected_output) {
            echo $expected_output['echoed'];
        });
        $this->klein_app->respond(static fn() => $expected_output['returned']);

        $output = $this->klein_app->dispatch(null, null, true, Klein::DISPATCH_CAPTURE_AND_RETURN);

        // Make sure nothing actually printed to the screen
        $this->expectOutputString('');

        // Make sure our returned output matches what we ECHO'd
        static::assertSame($expected_output['echoed'], $output);

        // Make sure our response body matches what we returned
        static::assertSame($expected_output['returned'], $this->klein_app->response()->body());
    }

    public function testDispatchOutputReplaced()
    {
        $expected_output = [
            'echoed' => 'yup',
            'returned' => 'nope',
        ];

        $this->klein_app->respond(static function () use ($expected_output) {
            echo $expected_output['echoed'];
        });
        $this->klein_app->respond(static fn() => $expected_output['returned']);

        $this->klein_app->dispatch(null, null, false, Klein::DISPATCH_CAPTURE_AND_REPLACE);

        // Make sure nothing actually printed to the screen
        $this->expectOutputString('');

        // Make sure our response body matches what we echoed
        static::assertSame($expected_output['echoed'], $this->klein_app->response()->body());
    }

    public function testDispatchOutputPrepended()
    {
        $expected_output = [
            'echoed' => 'yup',
            'returned' => 'nope',
            'echoed2' => 'sure',
        ];

        $this->klein_app->respond(static function () use ($expected_output) {
            echo $expected_output['echoed'];
        });
        $this->klein_app->respond(static fn() => $expected_output['returned']);
        $this->klein_app->respond(static function () use ($expected_output) {
            echo $expected_output['echoed2'];
        });

        $this->klein_app->dispatch(null, null, false, Klein::DISPATCH_CAPTURE_AND_PREPEND);

        // Make sure nothing actually printed to the screen
        $this->expectOutputString('');

        // Make sure our response body matches what we echoed
        static::assertSame(
            $expected_output['echoed'] . $expected_output['echoed2'] . $expected_output['returned'],
            $this->klein_app->response()->body(),
        );
    }

    public function testDispatchOutputAppended()
    {
        $expected_output = [
            'echoed' => 'yup',
            'returned' => 'nope',
            'echoed2' => 'sure',
        ];

        $this->klein_app->respond(static function () use ($expected_output) {
            echo $expected_output['echoed'];
        });
        $this->klein_app->respond(static fn() => $expected_output['returned']);
        $this->klein_app->respond(static function () use ($expected_output) {
            echo $expected_output['echoed2'];
        });

        $this->klein_app->dispatch(null, null, false, Klein::DISPATCH_CAPTURE_AND_APPEND);

        // Make sure nothing actually printed to the screen
        $this->expectOutputString('');

        // Make sure our response body matches what we echoed
        static::assertSame(
            $expected_output['returned'] . $expected_output['echoed'] . $expected_output['echoed2'],
            $this->klein_app->response()->body(),
        );
    }

    public function testDispatchResponseReplaced()
    {
        $expected_body = 'You SHOULD see this';
        $expected_code = 201;

        $expected_append = 'This should be appended?';

        $this->klein_app->respond('/', static function ($request, $response) {
            // Set our response code
            $response->code(569);

            return 'This should disappear';
        });
        $this->klein_app->respond('/', static fn() => new Response($expected_body, $expected_code));
        $this->klein_app->respond('/', static fn() => $expected_append);

        $this->klein_app->dispatch(null, null, false, Klein::DISPATCH_CAPTURE_AND_RETURN);

        // Make sure our response body and code match up
        static::assertSame($expected_body . $expected_append, $this->klein_app->response()->body());
        static::assertSame($expected_code, $this->klein_app->response()->code());
    }

    public function testRespondReturn()
    {
        $return_one = $this->klein_app->respond(static fn() => 1337);
        $return_two = $this->klein_app->respond(static fn() => 'dog');

        $this->klein_app->dispatch(null, null, false);

        static::assertTrue(is_callable($return_one));
        static::assertTrue(is_callable($return_two));
    }

    public function testRespondReturnChaining()
    {
        $return_one = $this->klein_app->respond(static fn() => 1337);
        $return_two = $this->klein_app->respond(static fn() => 1337)->getPath();

        static::assertSame($return_one->getPath(), $return_two);
    }

    public function testCatchallImplicit()
    {
        $this->expectOutputString('b');

        $this->klein_app->respond('/one', static function () {
            echo 'a';
        });
        $this->klein_app->respond(static function () {
            echo 'b';
        });
        $this->klein_app->respond('/two', static function () {});
        $this->klein_app->respond('/three', static function () {
            echo 'c';
        });

        $this->klein_app->dispatch(MockRequestFactory::create('/two'));
    }

    public function testCatchallAsterisk()
    {
        $this->expectOutputString('b');

        $this->klein_app->respond('/one', static function () {
            echo 'a';
        });
        $this->klein_app->respond('*', static function () {
            echo 'b';
        });
        $this->klein_app->respond('/two', static function () {});
        $this->klein_app->respond('/three', static function () {
            echo 'c';
        });

        $this->klein_app->dispatch(MockRequestFactory::create('/two'));
    }

    public function testCatchallImplicitTriggers404()
    {
        $this->expectOutputString("b404\n");

        $this->klein_app->onHttpError(static function ($code) {
            if (404 === $code) {
                echo "404\n";
            }
        });

        $this->klein_app->respond(static function () {
            echo 'b';
        });

        $this->klein_app->dispatch(MockRequestFactory::create('/'));
    }

    public function testRegex()
    {
        $this->expectOutputString('zz');

        $this->klein_app->respond('@/bar', static function () {
            echo 'z';
        });

        $this->klein_app->respond('@/[0-9]s', static function () {
            echo 'z';
        });

        $this->klein_app->dispatch(MockRequestFactory::create('/bar'));
        $this->klein_app->dispatch(MockRequestFactory::create('/8s'));
        $this->klein_app->dispatch(MockRequestFactory::create('/88s'));
    }

    public function testRegexNegate()
    {
        $this->expectOutputString('y');

        $this->klein_app->respond('!@/foo', static function () {
            echo 'y';
        });

        $this->klein_app->dispatch(MockRequestFactory::create('/bar'));
    }

    public function testNormalNegate()
    {
        $this->expectOutputString('');

        $this->klein_app->respond('!/foo', static function () {
            echo 'y';
        });

        $this->klein_app->dispatch(MockRequestFactory::create('/foo'));
    }

    public function test404()
    {
        $this->expectOutputString("404\n");

        $this->klein_app->onHttpError(static function ($code) {
            if (404 === $code) {
                echo "404\n";
            }
        });

        $this->klein_app->respond('/', static function () {
            echo 'a';
        });

        $this->klein_app->dispatch(MockRequestFactory::create('/foo'));

        $this->assertSame(404, $this->klein_app->response()->code());
    }

    public function testParamsBasic()
    {
        $this->expectOutputString('blue');

        $this->klein_app->respond('/[:color]', static function ($request) {
            echo $request->param('color');
        });

        $this->klein_app->dispatch(MockRequestFactory::create('/blue'));
    }

    public function testLiteralBacktickInRoute()
    {
        // The backtick is the delimiter of the compiled route regex and has to be escaped
        $this->expectOutputString('5');

        $this->klein_app->respond('/a`b/[i:id]', static function ($request) {
            echo $request->param('id');
        });

        $this->klein_app->dispatch(MockRequestFactory::create('/a`b/5'));
    }

    public function testParamsIntegerSuccess()
    {
        $this->expectOutputString('string(3) "987"');

        $this->klein_app->respond('/[i:age]', static function ($request) {
            $age = $request->param('age');

            printf('%s(%d) "%s"', gettype($age), strlen($age), $age);
        });

        $this->klein_app->dispatch(MockRequestFactory::create('/987'));
    }

    public function testParamsIntegerFail()
    {
        $this->expectOutputString('404 Code');

        $this->klein_app->onHttpError(static function ($code) {
            if (404 === $code) {
                echo '404 Code';
            }
        });

        $this->klein_app->respond('/[i:age]', static function ($request) {
            echo $request->param('age');
        });

        $this->klein_app->dispatch(MockRequestFactory::create('/blue'));
    }

    public function testParamsAlphaNum()
    {
        $this->klein_app->respond('/[a:audible]', static function ($request) {
            echo $request->param('audible');
        });

        static::assertSame('blue42', $this->dispatchAndReturnOutput(MockRequestFactory::create('/blue42')));
        static::assertSame('', $this->dispatchAndReturnOutput(MockRequestFactory::create('/texas-29')));
        static::assertSame('', $this->dispatchAndReturnOutput(MockRequestFactory::create('/texas29!')));
    }

    public function testParamsHex()
    {
        $this->klein_app->respond('/[h:hexcolor]', static function ($request) {
            echo $request->param('hexcolor');
        });

        static::assertSame('00f', $this->dispatchAndReturnOutput(MockRequestFactory::create('/00f')));
        static::assertSame('abc123', $this->dispatchAndReturnOutput(MockRequestFactory::create('/abc123')));
        static::assertSame('', $this->dispatchAndReturnOutput(MockRequestFactory::create('/876zih')));
        static::assertSame('', $this->dispatchAndReturnOutput(MockRequestFactory::create('/00g')));
        static::assertSame('', $this->dispatchAndReturnOutput(MockRequestFactory::create('/hi23')));
    }

    public function testParamsSlug()
    {
        $this->klein_app->respond('/[s:slug_name]', static function ($request) {
            echo $request->param('slug_name');
        });

        static::assertSame('dog-thing', $this->dispatchAndReturnOutput(MockRequestFactory::create('/dog-thing')));
        static::assertSame(
            'a_badass_slug',
            $this->dispatchAndReturnOutput(MockRequestFactory::create('/a_badass_slug')),
        );
        static::assertSame(
            'AN_UPERCASE_SLUG',
            $this->dispatchAndReturnOutput(MockRequestFactory::create('/AN_UPERCASE_SLUG')),
        );
        static::assertSame(
            'sample-wordpress-like-post-slug-based-on-the-title-2013-edition',
            $this->dispatchAndReturnOutput(MockRequestFactory::create(
                '/sample-wordpress-like-post-slug-based-on-the-title-2013-edition',
            )),
        );
        static::assertSame('', $this->dispatchAndReturnOutput(MockRequestFactory::create('/%!@#')));
        static::assertSame('', $this->dispatchAndReturnOutput(MockRequestFactory::create('/')));
        static::assertSame('', $this->dispatchAndReturnOutput(MockRequestFactory::create('/dog-%thing')));
    }

    public function testPathParamsAreUrlDecoded()
    {
        $this->klein_app->respond('/[:test]', static function ($request) {
            echo $request->param('test');
        });

        static::assertSame('Knife Party', $this->dispatchAndReturnOutput(MockRequestFactory::create('/Knife%20Party')));

        static::assertSame('and/or', $this->dispatchAndReturnOutput(MockRequestFactory::create('/and%2For')));
    }

    public function testPathParamsAreUrlDecodedToRFC3986Spec()
    {
        $this->klein_app->respond('/[:test]', static function ($request) {
            echo $request->param('test');
        });

        static::assertNotSame(
            'Knife Party',
            $this->dispatchAndReturnOutput(MockRequestFactory::create('/Knife+Party')),
        );

        static::assertSame('Knife+Party', $this->dispatchAndReturnOutput(MockRequestFactory::create('/Knife+Party')));
    }

    public function testPathParamsAreUrlDecodedOnlyOnce()
    {
        // A following catch-all route must not decode the params of the previous route again
        $this->expectOutputString('%41|%41|');

        $this->klein_app->respond('/[:a]', static function ($request) {
            echo $request->param('a') . '|';
        });
        $this->klein_app->respond(static function ($request) {
            echo $request->param('a') . '|';
        });

        $this->klein_app->dispatch(MockRequestFactory::create('/%2541'));
    }

    public function test404TriggersOnce()
    {
        $this->expectOutputString('d404 Code');

        $this->klein_app->onHttpError(static function ($code) {
            if (404 === $code) {
                echo '404 Code';
            }
        });

        $this->klein_app->respond(static function () {
            echo 'd';
        });

        $this->klein_app->dispatch(MockRequestFactory::create('/notroute'));
    }

    #[IgnoreDeprecations]
    public function test404RouteDefinitionOrderDoesntEffectWhen404HandlersCalled()
    {
        // Uses a deprecated 404/405 "route" on purpose
        $this->expectUserDeprecationMessage(
            'Use of 404/405 "routes" is deprecated. Use $klein->onHttpError() instead.',
        );

        $this->expectOutputString('onetwo404 Code');

        $this->klein_app->respond(static function () {
            echo 'one';
        });
        $this->klein_app->respond('404', static function () {
            echo '404 Code';
        });
        $this->klein_app->respond(static function () {
            echo 'two';
        });

        // Ignore our deprecation error
        $old_error_val = error_reporting();
        error_reporting(E_ALL ^ E_USER_DEPRECATED);

        $this->klein_app->dispatch(MockRequestFactory::create('/notroute'));

        error_reporting($old_error_val);
    }

    public function testMethodCatchAll()
    {
        $this->expectOutputString('yup!123');

        $this->klein_app->respond('POST', null, static function ($request) {
            echo 'yup!';
        });
        $this->klein_app->respond('POST', '*', static function ($request) {
            echo '1';
        });
        $this->klein_app->respond('POST', '/', static function ($request) {
            echo '2';
        });
        $this->klein_app->respond(static function ($request) {
            echo '3';
        });

        $this->klein_app->dispatch(MockRequestFactory::create('/', 'POST'));
    }

    public function testLazyTrailingMatch()
    {
        $this->expectOutputString('this-is-a-title-123');

        $this->klein_app->respond('/posts/[*:title][i:id]', static function ($request) {
            echo $request->param('title') . $request->param('id');
        });

        $this->klein_app->dispatch(MockRequestFactory::create('/posts/this-is-a-title-123'));
    }

    public function testFormatMatch()
    {
        $this->expectOutputString('xml');

        $this->klein_app->respond('/output.[xml|json:format]', static function ($request) {
            echo $request->param('format');
        });

        $this->klein_app->dispatch(MockRequestFactory::create('/output.xml'));
    }

    public function testDotSeparator()
    {
        $this->expectOutputString('matchA:slug=ABCD_E--matchB:slug=ABCD_E--');

        $this->klein_app->respond('/[*:cpath]/[:slug].[:format]', static function ($rq) {
            echo 'matchA:slug=' . $rq->param('slug') . '--';
        });
        $this->klein_app->respond('/[*:cpath]/[:slug].[:format]?', static function ($rq) {
            echo 'matchB:slug=' . $rq->param('slug') . '--';
        });
        $this->klein_app->respond('/[*:cpath]/[a:slug].[:format]?', static function ($rq) {
            echo 'matchC:slug=' . $rq->param('slug') . '--';
        });

        $this->klein_app->dispatch(MockRequestFactory::create('/category1/categoryX/ABCD_E.php'));

        static::assertSame(
            'matchA:slug=ABCD_E--matchB:slug=ABCD_E--',
            $this->dispatchAndReturnOutput(MockRequestFactory::create('/category1/categoryX/ABCD_E.php')),
        );
        static::assertSame(
            'matchB:slug=ABCD_E--',
            $this->dispatchAndReturnOutput(MockRequestFactory::create('/category1/categoryX/ABCD_E')),
        );
    }

    public function testControllerActionStyleRouteMatch()
    {
        $this->expectOutputString('donkey-kick');

        $this->klein_app->respond('/[:controller]?/[:action]?', static function ($request) {
            echo $request->param('controller') . '-' . $request->param('action');
        });

        $this->klein_app->dispatch(MockRequestFactory::create('/donkey/kick'));
    }

    public function testRespondArgumentOrder()
    {
        $this->expectOutputString('abcdef');

        $this->klein_app->respond(static function () {
            echo 'a';
        });
        $this->klein_app->respond(null, static function () {
            echo 'b';
        });
        $this->klein_app->respond('/endpoint', static function () {
            echo 'c';
        });
        $this->klein_app->respond('GET', null, static function () {
            echo 'd';
        });
        $this->klein_app->respond(['GET', 'POST'], null, static function () {
            echo 'e';
        });
        $this->klein_app->respond(['GET', 'POST'], '/endpoint', static function () {
            echo 'f';
        });

        $this->klein_app->dispatch(MockRequestFactory::create('/endpoint'));
    }

    public function testTrailingMatch()
    {
        $this->klein_app->respond('/?[*:trailing]/dog/?', static function ($request) {
            echo 'yup';
        });

        static::assertSame('yup', $this->dispatchAndReturnOutput(MockRequestFactory::create('/cat/dog')));
        static::assertSame('yup', $this->dispatchAndReturnOutput(MockRequestFactory::create('/cat/cheese/dog')));
        static::assertSame('yup', $this->dispatchAndReturnOutput(MockRequestFactory::create('/cat/ball/cheese/dog/')));
        static::assertSame('yup', $this->dispatchAndReturnOutput(MockRequestFactory::create('/cat/ball/cheese/dog')));
        static::assertSame('yup', $this->dispatchAndReturnOutput(MockRequestFactory::create('cat/ball/cheese/dog/')));
        static::assertSame('yup', $this->dispatchAndReturnOutput(MockRequestFactory::create('cat/ball/cheese/dog')));
    }

    public function testTrailingPossessiveMatch()
    {
        $this->klein_app->respond('/sub-dir/[**:trailing]', static function ($request) {
            echo 'yup';
        });

        static::assertSame('yup', $this->dispatchAndReturnOutput(MockRequestFactory::create('/sub-dir/dog')));

        static::assertSame('yup', $this->dispatchAndReturnOutput(MockRequestFactory::create('/sub-dir/cheese/dog')));

        static::assertSame(
            'yup',
            $this->dispatchAndReturnOutput(MockRequestFactory::create('/sub-dir/ball/cheese/dog/')),
        );

        static::assertSame(
            'yup',
            $this->dispatchAndReturnOutput(MockRequestFactory::create('/sub-dir/ball/cheese/dog')),
        );
    }

    public function testNSDispatch()
    {
        $this->klein_app->with('/u', static function ($klein_app) {
            $klein_app->respond('GET', '/?', static function ($request, $response) {
                echo 'slash';
            });
            $klein_app->respond('GET', '/[:id]', static function ($request, $response) {
                echo 'id';
            });
        });

        $this->klein_app->onHttpError(static function ($code) {
            if (404 === $code) {
                echo '404';
            }
        });

        static::assertSame('slash', $this->dispatchAndReturnOutput(MockRequestFactory::create('/u')));
        static::assertSame('slash', $this->dispatchAndReturnOutput(MockRequestFactory::create('/u/')));
        static::assertSame('id', $this->dispatchAndReturnOutput(MockRequestFactory::create('/u/35')));
        static::assertSame('404', $this->dispatchAndReturnOutput(MockRequestFactory::create('/35')));
    }

    public function testNSDispatchExternal()
    {
        $ext_namespaces = $this->loadExternalRoutes();

        $this->klein_app->respond(404, static function ($request, $response) {
            echo '404';
        });

        foreach ($ext_namespaces as $namespace) {
            static::assertSame('yup', $this->dispatchAndReturnOutput(MockRequestFactory::create($namespace . '/')));

            static::assertSame(
                'yup',
                $this->dispatchAndReturnOutput(MockRequestFactory::create($namespace . '/testing/')),
            );
        }
    }

    public function testNSDispatchExternalRerequired()
    {
        $ext_namespaces = $this->loadExternalRoutes();

        $this->klein_app->respond(404, static function ($request, $response) {
            echo '404';
        });

        foreach ($ext_namespaces as $namespace) {
            static::assertSame('yup', $this->dispatchAndReturnOutput(MockRequestFactory::create($namespace . '/')));

            static::assertSame(
                'yup',
                $this->dispatchAndReturnOutput(MockRequestFactory::create($namespace . '/testing/')),
            );
        }
    }

    public function test405DefaultRequest()
    {
        $this->klein_app->respond(['GET', 'POST'], '/', static function () {
            echo 'fail';
        });

        $this->klein_app->dispatch(MockRequestFactory::create('/', 'DELETE'));

        $this->assertEquals('405 Method Not Allowed', $this->klein_app->response()->status()->getFormattedString());
        $this->assertEquals('GET, POST', $this->klein_app->response()->headers()->get('Allow'));
    }

    public function testNo405OnNonMatchRoutes()
    {
        $this->klein_app->respond(['GET', 'POST'], null, static function () {
            echo 'this shouldn\'t cause a 405 since this route doesn\'t count as a match anyway';
        });

        $this->klein_app->dispatch(MockRequestFactory::create('/', 'DELETE'));

        static::assertSame(404, $this->klein_app->response()->code());
    }

    #[IgnoreDeprecations]
    public function test405Routes()
    {
        // Uses a deprecated 404/405 "route" on purpose
        $this->expectUserDeprecationMessage(
            'Use of 404/405 "routes" is deprecated. Use $klein->onHttpError() instead.',
        );

        $result_array = [];

        $this->expectOutputString('_');

        $this->klein_app->respond(static function () {
            echo '_';
        });
        $this->klein_app->respond('GET', '/sure', static function () {
            echo 'fail';
        });
        $this->klein_app->respond(['GET', 'POST'], '/sure', static function () {
            echo 'fail';
        });
        $this->klein_app->respond(405, static function ($a, $b, $c, $d, $e, $f, $methods) use (&$result_array) {
            $result_array = $methods;
        });

        // Ignore our deprecation error
        $old_error_val = error_reporting();
        error_reporting(E_ALL ^ E_USER_DEPRECATED);

        $this->klein_app->dispatch(MockRequestFactory::create('/sure', 'DELETE'));

        error_reporting($old_error_val);

        $this->assertCount(2, $result_array);
        $this->assertContains('GET', $result_array);
        $this->assertContains('POST', $result_array);
        $this->assertSame(405, $this->klein_app->response()->code());
    }

    public function test405ErrorHandler()
    {
        $result_array = [];

        $this->expectOutputString('_');

        $this->klein_app->respond(static function () {
            echo '_';
        });
        $this->klein_app->respond('GET', '/sure', static function () {
            echo 'fail';
        });
        $this->klein_app->respond(['GET', 'POST'], '/sure', static function () {
            echo 'fail';
        });
        $this->klein_app->onHttpError(static function ($code, $klein, $matched, $methods, $exception) use (
            &$result_array,
        ) {
            $result_array = $methods;
        });

        $this->klein_app->dispatch(MockRequestFactory::create('/sure', 'DELETE'));

        $this->assertCount(2, $result_array);
        $this->assertContains('GET', $result_array);
        $this->assertContains('POST', $result_array);
        $this->assertSame(405, $this->klein_app->response()->code());
    }

    public function testOptionsDefaultRequest()
    {
        $this->klein_app->respond(static function ($request, $response) {
            $response->code(200);
        });
        $this->klein_app->respond(['GET', 'POST'], '/', static function () {
            echo 'fail';
        });

        $this->klein_app->dispatch(MockRequestFactory::create('/', 'OPTIONS'));

        static::assertSame('200 OK', $this->klein_app->response()->status()->getFormattedString());
        static::assertSame('GET, POST', $this->klein_app->response()->headers()->get('Allow'));
    }

    public function testOptionsRoutes()
    {
        $access_control_headers = [
            [
                'key' => 'Access-Control-Allow-Origin',
                'val' => 'http://example.com',
            ],
            [
                'key' => 'Access-Control-Allow-Methods',
                'val' => 'POST, GET, DELETE, OPTIONS, HEAD',
            ],
        ];

        $this->klein_app->respond('GET', '/', static function () {
            echo 'fail';
        });
        $this->klein_app->respond(['GET', 'POST'], '/', static function () {
            echo 'fail';
        });
        $this->klein_app->respond('OPTIONS', null, static function ($request, $response) use ($access_control_headers) {
            // Add access control headers
            foreach ($access_control_headers as $header) {
                $response->header($header['key'], $header['val']);
            }
        });

        $this->klein_app->dispatch(MockRequestFactory::create('/', 'OPTIONS'));

        // Assert headers were passed
        static::assertSame('GET, POST', $this->klein_app->response()->headers()->get('Allow'));

        foreach ($access_control_headers as $header) {
            static::assertEquals($header['val'], $this->klein_app->response()->headers()->get($header['key']));
        }
    }

    public function testHeadDefaultRequest()
    {
        $expected_headers = [
            [
                'key' => 'X-Some-Random-Header',
                'val' => 'This was a GET route',
            ],
        ];

        $this->klein_app->respond('GET', null, static function ($request, $response) use ($expected_headers) {
            $response->code(200);

            // Add access control headers
            foreach ($expected_headers as $header) {
                $response->header($header['key'], $header['val']);
            }
        });
        $this->klein_app->respond('GET', '/', static function () {
            echo 'GET!';
            return 'more text';
        });
        $this->klein_app->respond('POST', '/', static function () {
            echo 'POST!';
        });

        $this->klein_app->dispatch(MockRequestFactory::create('/', 'HEAD'));

        // Make sure we don't get a response body
        $this->expectOutputString('');

        // Assert headers were passed
        foreach ($expected_headers as $header) {
            static::assertEquals($header['val'], $this->klein_app->response()->headers()->get($header['key']));
        }
    }

    public function testHeadMethodMatch()
    {
        $test_strings = [
            'oh, hello',
            'yea',
        ];

        $test_result = null;

        $this->klein_app->respond(['GET', 'HEAD'], null, static function ($request, $response) use (
            $test_strings,
            &$test_result,
        ) {
            $test_result .= $test_strings[0];
        });
        $this->klein_app->respond('GET', '/', static function ($request, $response) use ($test_strings, &$test_result) {
            $test_result .= $test_strings[1];
        });
        $this->klein_app->respond('POST', '/', static function ($request, $response) use (
            $test_strings,
            &$test_result,
        ) {
            $test_result .= 'nope';
        });

        $this->klein_app->dispatch(MockRequestFactory::create('/', 'HEAD'));

        static::assertSame(implode('', $test_strings), $test_result);
    }

    public function testAbortWithInvalidCodeGoesThroughErrorCallbacks()
    {
        $error_type = null;

        $this->klein_app->onError(static function ($klein, $message, $type) use (&$error_type) {
            $error_type = $type;
        });

        $this->klein_app->respond(static function ($request, $response, $service, $app, $klein) {
            $klein->abort(999);
        });

        // Used to send a "HTTP/1.1 999" status line
        $this->klein_app->dispatch(null, null, false);

        static::assertSame(\InvalidArgumentException::class, $error_type);
        static::assertSame(200, $this->klein_app->response()->code());
    }

    public function testRouteRegexCacheKeyDependsOnMatchTypes()
    {
        $default = $this->createKleinWithIntegerType('[0-9]++');
        $same = $this->createKleinWithIntegerType('[0-9]++');
        $letters = $this->createKleinWithIntegerType('[a-z]++');

        static::assertSame($default->cacheKey('/n/[i:id]'), $same->cacheKey('/n/[i:id]'));
        static::assertNotSame($default->cacheKey('/n/[i:id]'), $letters->cacheKey('/n/[i:id]'));
        static::assertNotSame($default->cacheKey('/n/[i:id]'), $default->cacheKey('/m/[i:id]'));
    }

    public function testRouteRegexCacheDoesNotLeakBetweenMatchTypes()
    {
        if (!function_exists('apcu_enabled') || !apcu_enabled()) {
            static::markTestSkipped('Needs APCu enabled on the CLI (apc.enable_cli=1)');
        }

        apcu_clear_cache();

        // Both compile "/n/[i:id]" and only differ in what "i" matches
        $digits = $this->createKleinWithIntegerType('[0-9]++');
        $letters = $this->createKleinWithIntegerType('[a-z]++');

        foreach ([$digits, $letters] as $klein) {
            $klein->respond('/n/[i:id]', static fn() => 'matched');
        }

        $digits->dispatch(MockRequestFactory::create('/n/123'), null, false);
        $letters->dispatch(MockRequestFactory::create('/n/abc'), null, false);

        // The second one used to get the first one's cached regex (and a 404)
        static::assertSame('matched', $digits->response()->body());
        static::assertSame('matched', $letters->response()->body());
    }

    /**
     * Creates a Klein instance (always of the same class) with a custom "i" match type
     */
    private function createKleinWithIntegerType(string $type): Klein
    {
        return new class($type) extends Klein {
            public function __construct(string $type)
            {
                parent::__construct();

                $this->match_types['i'] = $type;
            }

            public function cacheKey(string $expression): string
            {
                return $this->getRouteRegexCacheKey($expression);
            }
        };
    }

    public function testGetPathForEncodesParams()
    {
        $captured = [];

        $this->klein_app
            ->respond('/users/[:name]', static function ($request) use (&$captured) {
                $captured[] = $request->name;
            })
            ->setName('user');
        $this->klein_app
            ->respond('/files/[**:path]', static function ($request) use (&$captured) {
                $captured[] = $request->path;
            })
            ->setName('file');
        $this->klein_app->respond('/dogs/[i:id]', static function () {})->setName('dog');

        $this->klein_app->routes()->prepareNamed();

        $user_path = $this->klein_app->getPathFor('user', ['name' => 'a/../b?x=1#y z']);
        $file_path = $this->klein_app->getPathFor('file', ['path' => 'docs/my file?.txt']);

        // Used to be "/users/a/../b?x=1#y z", which breaks out of the path
        static::assertSame('/users/a%2F..%2Fb%3Fx%3D1%23y%20z', $user_path);
        // Catch-all params keep their slashes
        static::assertSame('/files/docs/my%20file%3F.txt', $file_path);
        static::assertSame('/dogs/5', $this->klein_app->getPathFor('dog', ['id' => 5]));

        // The paths route back to the original values
        $this->klein_app->dispatch(MockRequestFactory::create($user_path), null, false);
        $this->klein_app->dispatch(MockRequestFactory::create($file_path), null, false);

        static::assertSame(['a/../b?x=1#y z', 'docs/my file?.txt'], $captured);
    }

    public function testGetPathFor()
    {
        $this->klein_app->respond('/dogs', static function () {})->setName('dogs');

        $this->klein_app->respond('/dogs/[i:dog_id]/collars', static function () {})->setName('dog-collars');

        $this->klein_app
            ->respond('/dogs/[i:dog_id]/collars/[a:collar_slug]/?', static function () {})
            ->setName('dog-collar-details');

        $this->klein_app->respond('/dog/foo', static function () {})->setName('dog-foo');

        $this->klein_app->respond('/dog/[i:dog_id]?', static function () {})->setName('dog-optional-details');

        $this->klein_app->respond('@/dog/regex', static function () {})->setName('dog-regex');

        $this->klein_app->respond('!@/dog/regex', static function () {})->setName('dog-neg-regex');

        $this->klein_app->respond('@\.(json|csv)$', static function () {})->setName('complex-regex');

        $this->klein_app->respond('!@^/admin/', static function () {})->setName('complex-neg-regex');

        $this->klein_app->dispatch(MockRequestFactory::create('/', 'HEAD'));

        static::assertSame('/dogs', $this->klein_app->getPathFor('dogs'));
        static::assertSame('/dogs/[i:dog_id]/collars', $this->klein_app->getPathFor('dog-collars'));
        static::assertSame('/dogs/idnumberandstuff/collars', $this->klein_app->getPathFor('dog-collars', [
            'dog_id' => 'idnumberandstuff',
        ]));
        static::assertSame(
            '/dogs/[i:dog_id]/collars/[a:collar_slug]/?',
            $this->klein_app->getPathFor('dog-collar-details'),
        );
        static::assertSame('/dogs/idnumberandstuff/collars/d12f3d1f2d3/?', $this->klein_app->getPathFor('dog-collar-details', [
            'dog_id' => 'idnumberandstuff',
            'collar_slug' => 'd12f3d1f2d3',
        ]));
        static::assertSame('/dog/foo', $this->klein_app->getPathFor('dog-foo'));
        static::assertSame('/dog', $this->klein_app->getPathFor('dog-optional-details'));
        static::assertSame('/', $this->klein_app->getPathFor('dog-regex'));
        static::assertSame('/', $this->klein_app->getPathFor('dog-neg-regex'));
        static::assertSame('@/dog/regex', $this->klein_app->getPathFor('dog-regex', null, false));
        static::assertNotSame('/', $this->klein_app->getPathFor('dog-neg-regex', null, false));
        static::assertSame('/', $this->klein_app->getPathFor('complex-regex'));
        static::assertSame('/', $this->klein_app->getPathFor('complex-neg-regex'));
        static::assertSame('@\.(json|csv)$', $this->klein_app->getPathFor('complex-regex', null, false));
        static::assertNotSame('/', $this->klein_app->getPathFor('complex-neg-regex', null, false));
    }

    public function testDispatchHalt()
    {
        $this->expectOutputString('2,4,7,8,');

        $this->klein_app->respond(static function ($a, $b, $c, $d, $klein_app) {
            $klein_app->skipThis();
            echo '1,';
        });
        $this->klein_app->respond(static function ($a, $b, $c, $d, $klein_app) {
            echo '2,';
            $klein_app->skipNext();
        });
        $this->klein_app->respond(static function ($a, $b, $c, $d, $klein_app) {
            echo '3,';
        });
        $this->klein_app->respond(static function ($a, $b, $c, $d, $klein_app) {
            echo '4,';
            $klein_app->skipNext(2);
        });
        $this->klein_app->respond(static function ($a, $b, $c, $d, $klein_app) {
            echo '5,';
        });
        $this->klein_app->respond(static function ($a, $b, $c, $d, $klein_app) {
            echo '6,';
        });
        $this->klein_app->respond(static function ($a, $b, $c, $d, $klein_app) {
            echo '7,';
        });
        $this->klein_app->respond(static function ($a, $b, $c, $d, $klein_app) {
            echo '8,';
            $klein_app->skipRemaining();
        });
        $this->klein_app->respond(static function ($a, $b, $c, $d, $klein_app) {
            echo '9,';
        });
        $this->klein_app->respond(static function ($a, $b, $c, $d, $klein_app) {
            echo '10,';
        });

        $this->klein_app->dispatch();
    }

    public function testDispatchSkipCauses404()
    {
        $this->expectOutputString('404');

        $this->klein_app->onHttpError(static function ($code) {
            if (404 === $code) {
                echo '404';
            }
        });

        $this->klein_app->respond('POST', '/steez', static function ($a, $b, $c, $d, $klein_app) {
            $klein_app->skipThis();
            echo 'Style... with ease';
        });
        $this->klein_app->respond('GET', '/nope', static function ($a, $b, $c, $d, $klein_app) {
            echo 'How did I get here?!';
        });

        $this->klein_app->dispatch(MockRequestFactory::create('/steez', 'POST'));
    }

    public function testDispatchAbort()
    {
        $this->expectOutputString('1,');

        $this->klein_app->respond(static function ($a, $b, $c, $d, $klein_app) {
            echo '1,';
        });
        $this->klein_app->respond(static function ($a, $b, $c, $d, $klein_app) {
            $klein_app->abort();
            echo '2,';
        });
        $this->klein_app->respond(static function ($a, $b, $c, $d, $klein_app) {
            echo '3,';
        });

        $this->klein_app->dispatch();

        static::assertSame(404, $this->klein_app->response()->code());
    }

    public function testDispatchAbortWithCode()
    {
        $this->expectOutputString('1,');

        $this->klein_app->respond(static function ($a, $b, $c, $d, $klein_app) {
            echo '1,';
        });
        $this->klein_app->respond(static function ($a, $b, $c, $d, $klein_app) {
            $klein_app->abort(404);
            echo '2,';
        });
        $this->klein_app->respond(static function ($a, $b, $c, $d, $klein_app) {
            echo '3,';
        });

        $this->klein_app->dispatch();

        static::assertSame(404, $this->klein_app->response()->code());
    }

    public function testDispatchAbortCallsHttpError()
    {
        $test_code = 451;
        $this->expectOutputString('1,aborted,' . $test_code);

        $this->klein_app->onHttpError(static function ($code, $klein_app) {
            echo 'aborted,';
            echo $code;
        });

        $this->klein_app->respond(static function ($a, $b, $c, $d, $klein_app) {
            echo '1,';
        });
        $this->klein_app->respond(static function ($a, $b, $c, $d, $klein_app) use ($test_code) {
            $klein_app->abort($test_code);
            echo '2,';
        });
        $this->klein_app->respond(static function ($a, $b, $c, $d, $klein_app) {
            echo '3,';
        });

        $this->klein_app->dispatch();

        static::assertSame($test_code, $this->klein_app->response()->code());
    }

    public function testDispatchExceptionRethrowsUnknownCode()
    {
        $this->expectException(UnhandledException::class);
        $this->expectOutputString('');

        $test_message = 'whatever';
        $test_code = 666;

        $this->klein_app->respond(static function ($a, $b, $c, $d, $klein_app) use ($test_message, $test_code) {
            throw new DispatchHaltedException($test_message, $test_code);
        });

        $this->klein_app->dispatch();

        static::assertSame(404, $this->klein_app->response()->code());
    }

    public function testThrowHttpExceptionHandledProperly()
    {
        $this->expectOutputString('');

        $this->klein_app->respond('/', static function ($a, $b, $c, $d, $klein_app) {
            throw HttpException::createFromCode(400);

            echo 'hi!';
        });

        $this->klein_app->dispatch();

        static::assertSame(400, $this->klein_app->response()->code());
    }

    public function testHttpExceptionStopsRouteMatching()
    {
        $this->expectOutputString('one');

        $this->klein_app->respond(static function () {
            echo 'one';

            throw HttpException::createFromCode(404);
        });
        $this->klein_app->respond(static function () {
            echo 'two';
        });

        $this->klein_app->dispatch(MockRequestFactory::create('/notroute'));
    }

    public function testOptionsAlias()
    {
        $this->expectOutputString('1,2,');

        // With path
        $this->klein_app->options('/', static function () {
            echo '1,';
        });

        // Without path
        $this->klein_app->options(static function () {
            echo '2,';
        });

        $this->klein_app->dispatch(MockRequestFactory::create('/', 'OPTIONS'));
    }

    public function testHeadAlias()
    {
        // HEAD requests shouldn't return data
        $this->expectOutputString('');

        // With path
        $this->klein_app->head('/', static function ($request, $response) {
            echo '1,';
            $response->headers()->set('Test-1', 'yup');
        });

        // Without path
        $this->klein_app->head(static function ($request, $response) {
            echo '2,';
            $response->headers()->set('Test-2', 'yup');
        });

        $this->klein_app->dispatch(MockRequestFactory::create('/', 'HEAD'));

        static::assertTrue($this->klein_app->response()->headers()->exists('Test-1'));
        static::assertTrue($this->klein_app->response()->headers()->exists('Test-2'));
        static::assertFalse($this->klein_app->response()->headers()->exists('Test-3'));
    }

    public function testGetAlias()
    {
        $this->expectOutputString('1,2,');

        // With path
        $this->klein_app->get('/', static function () {
            echo '1,';
        });

        // Without path
        $this->klein_app->get(static function () {
            echo '2,';
        });

        $this->klein_app->dispatch(MockRequestFactory::create('/'));
    }

    public function testPostAlias()
    {
        $this->expectOutputString('1,2,');

        // With path
        $this->klein_app->post('/', static function () {
            echo '1,';
        });

        // Without path
        $this->klein_app->post(static function () {
            echo '2,';
        });

        $this->klein_app->dispatch(MockRequestFactory::create('/', 'POST'));
    }

    public function testPutAlias()
    {
        $this->expectOutputString('1,2,');

        // With path
        $this->klein_app->put('/', static function () {
            echo '1,';
        });

        // Without path
        $this->klein_app->put(static function () {
            echo '2,';
        });

        $this->klein_app->dispatch(MockRequestFactory::create('/', 'PUT'));
    }

    public function testDeleteAlias()
    {
        $this->expectOutputString('1,2,');

        // With path
        $this->klein_app->delete('/', static function () {
            echo '1,';
        });

        // Without path
        $this->klein_app->delete(static function () {
            echo '2,';
        });

        $this->klein_app->dispatch(MockRequestFactory::create('/', 'DELETE'));
    }

    /**
     * Advanced string route matching tests
     *
     * As the original Klein project was designed as a PHP version of Sinatra,
     * many of the following tests are ports of the Sinatra ruby equivalents:
     * https://github.com/sinatra/sinatra/blob/cd82a57154d57c18acfadbfefbefc6ea6a5035af/test/routing_test.rb
     */

    public function testMatchesEncodedSlashes()
    {
        $this->klein_app->respond('/[:a]', static fn($request) => $request->param('a'));

        $this->klein_app->dispatch(
            MockRequestFactory::create('/foo%2Fbar'),
            null,
            true,
            Klein::DISPATCH_CAPTURE_AND_RETURN,
        );

        static::assertSame(200, $this->klein_app->response()->code());
        static::assertSame('foo/bar', $this->klein_app->response()->body());
    }

    public function testMatchesDotAsNamedParam()
    {
        $this->klein_app->respond('/[:foo]/[:bar]', static fn($request) => $request->param('foo'));

        $this->klein_app->dispatch(
            MockRequestFactory::create('/user@example.com/name'),
            null,
            true,
            Klein::DISPATCH_CAPTURE_AND_RETURN,
        );

        static::assertSame(200, $this->klein_app->response()->code());
        static::assertSame('user@example.com', $this->klein_app->response()->body());
    }

    public function testMatchesDotOutsideOfNamedParam()
    {
        $file = null;
        $ext = null;

        $this->klein_app->respond('/[:file].[:ext]', static function ($request) use (&$file, &$ext) {
            $file = $request->param('file');
            $ext = $request->param('ext');

            return 'woot!';
        });

        $this->klein_app->dispatch(
            MockRequestFactory::create('/unicorn.png'),
            null,
            true,
            Klein::DISPATCH_CAPTURE_AND_RETURN,
        );

        static::assertSame(200, $this->klein_app->response()->code());
        static::assertSame('woot!', $this->klein_app->response()->body());
        static::assertSame('unicorn', $file);
        static::assertSame('png', $ext);
    }

    public function testMatchesLiteralDotsInPaths()
    {
        $this->klein_app->respond('/file.ext', static function () {});

        // Should match
        $this->klein_app->dispatch(MockRequestFactory::create('/file.ext'));
        static::assertSame(200, $this->klein_app->response()->code());

        // Shouldn't match
        $this->klein_app->dispatch(MockRequestFactory::create('/file0ext'));
        static::assertSame(404, $this->klein_app->response()->code());
    }

    public function testMatchesLiteralDotsInPathBeforeNamedParam()
    {
        $this->klein_app->respond('/file.[:ext]', static function () {});

        // Should match
        $this->klein_app->dispatch(MockRequestFactory::create('/file.ext'));
        static::assertSame(200, $this->klein_app->response()->code());

        // Shouldn't match
        $this->klein_app->dispatch(MockRequestFactory::create('/file0ext'));
        static::assertSame(404, $this->klein_app->response()->code());
    }

    public function testMultipleUnsafeCharactersArentOverQuoted()
    {
        $this->klein_app->respond('/[a:site].[:format]?/[:id].[:format2]?', static function () {});

        $this->klein_app->dispatch(MockRequestFactory::create('/site.main/id.json'));
        static::assertSame(200, $this->klein_app->response()->code());
    }

    public function testMatchesLiteralPlusSignsInPaths()
    {
        $this->klein_app->respond('/te+st', static function () {});

        // Should match
        $this->klein_app->dispatch(MockRequestFactory::create('/te+st'));
        static::assertSame(200, $this->klein_app->response()->code());

        // Shouldn't match
        $this->klein_app->dispatch(MockRequestFactory::create('/teeeeeeeeest'));
        static::assertSame(404, $this->klein_app->response()->code());
    }

    public function testMatchesParenthesesInPaths()
    {
        $this->klein_app->respond('/test(bar)', static function () {});

        $this->klein_app->dispatch(MockRequestFactory::create('/test(bar)'));
        static::assertSame(200, $this->klein_app->response()->code());
    }

    public function testMatchesAdvancedRegularExpressions()
    {
        $this->klein_app->respond('@^/foo.../bar$', static function () {});

        $this->klein_app->dispatch(MockRequestFactory::create('/foooom/bar'));
        static::assertSame(200, $this->klein_app->response()->code());
    }

    public function testApcDependencyFailsGracefully()
    {
        // Custom apc function
        implement_custom_apc_cache_functions();

        $this->klein_app->respond('/test', static function () {});

        $this->klein_app->dispatch(MockRequestFactory::create('/test'));
        static::assertSame(200, $this->klein_app->response()->code());
    }

    public function testRoutePathCompilationFailure()
    {
        $this->klein_app->respond('/users/[i:id]/friends/[i:id]/', static function () {
            echo 'yup';
        });

        $exception = null;

        try {
            $this->klein_app->dispatch(MockRequestFactory::create('/users/1738197/friends/7828316'));
        } catch (Exception $e) {
            $exception = $e->getPrevious();
        }

        static::assertTrue($exception instanceof RoutePathCompilationException);
        static::assertTrue($exception->getRoute() instanceof Route);
    }

    public function testRoutePathCompilationFailureWithoutWarnings()
    {
        $old_error_val = error_reporting();
        error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);

        $this->testRoutePathCompilationFailure();

        error_reporting($old_error_val);
    }
}
