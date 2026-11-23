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

use Klein\DataCollection\DataCollection;
use Klein\Exceptions\ValidationException;
use Klein\Request;
use Klein\Response;
use Klein\ServiceProvider;
use Klein\Validator;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * ServiceProviderTest
 */
class ServiceProviderTest extends AbstractKleinTestCase
{
    protected function getBasicServiceProvider()
    {
        return new ServiceProvider($request = new Request(), $response = new Response());
    }

    public function testConstructor()
    {
        $service = new ServiceProvider();

        // Make sure our attributes are first null
        static::assertFalse($service->isBond());

        // New service with injected dependencies
        $service = new ServiceProvider($request = new Request(), $response = new Response());

        // Make sure our attributes are set
        static::assertTrue($service->isBond());
    }

    public function testBinder()
    {
        $service = new ServiceProvider();

        // Make sure our attributes are first null
        static::assertFalse($service->isBond());

        // New service with injected dependencies
        $return_val = $service->bind($request = new Request(), $response = new Response());

        // Make sure our attributes are set
        static::assertTrue($service->isBond());

        // Make sure we're chainable
        static::assertEquals($service, $return_val);
        static::assertSame($service, $return_val);
    }

    public function testSharedDataGetter()
    {
        $service = new ServiceProvider();

        static::assertIsObject($service->sharedData());
        static::assertTrue($service->sharedData() instanceof DataCollection);
    }

    public function testStartSession()
    {
        $service = new ServiceProvider();

        $returned = $service->startSession();

        static::assertSame(session_id(), $returned);

        // Clean up
        session_destroy();
    }

    public function testStartSessionFails()
    {
        // Only care about some errors, and keep the old value
        $old_error_val = error_reporting();
        error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);

        session_id('');
        session_start();

        $service = new ServiceProvider();

        $returned = $service->startSession();

        static::assertNull($returned);

        // Clean up
        session_destroy();
        error_reporting($old_error_val);
    }

    public function testFlash()
    {
        // Test data
        $test_session_key = '__flashes';
        $test_flashes = [
            [
                'message' => 'Test info message',
                'type' => 'info',
            ],
            [
                'message' => 'Test error message',
                'type' => 'error',
            ],
        ];

        $service = new ServiceProvider();

        static::assertEmpty($_SESSION);

        $service->flash($test_flashes[0]['message'], $test_flashes[0]['type']);
        $service->flash($test_flashes[1]['message'], $test_flashes[1]['type']);

        static::assertNotEmpty($_SESSION);
        static::assertSame($test_flashes[0]['message'], $_SESSION[$test_session_key][$test_flashes[0]['type']][0]);
        static::assertSame($test_flashes[1]['message'], $_SESSION[$test_session_key][$test_flashes[1]['type']][0]);

        // Clean up
        session_destroy();
        $_SESSION = [];
    }

    public function testFlashWithMarkdown()
    {
        // Test data
        $test_session_key = '__flashes';
        $test_type = 'info';
        $test_message = 'Test message by %s %s';
        $test_params = [
            'Trevor',
            'Suarez',
        ];
        $test_processed = 'Test message by ' . $test_params[0] . ' ' . $test_params[1];

        $service = new ServiceProvider();

        static::assertEmpty($_SESSION);

        $service->flash($test_message, $test_params);

        static::assertNotEmpty($_SESSION);
        static::assertSame($test_processed, $_SESSION[$test_session_key][$test_type][0]);

        // Clean up
        session_destroy();
        $_SESSION = [];
    }

    public function testFlashes()
    {
        // Test data
        $test_session_key = '__flashes';
        $test_flashes = [
            [
                'message' => 'Test info message',
                'type' => 'info',
            ],
            [
                'message' => 'Test error message',
                'type' => 'error',
            ],
            [
                'message' => 'Test second error message',
                'type' => 'error',
            ],
            [
                'message' => 'Test whatever message',
                'type' => 'whatever',
            ],
        ];
        $test_error_flashes = [
            $test_flashes[1]['message'],
            $test_flashes[2]['message'],
        ];

        $service = new ServiceProvider();

        static::assertEmpty($_SESSION);
        static::assertEmpty($service->flashes());

        $service->flash($test_flashes[0]['message'], $test_flashes[0]['type']);
        $service->flash($test_flashes[1]['message'], $test_flashes[1]['type']);
        $service->flash($test_flashes[2]['message'], $test_flashes[2]['type']);
        $service->flash($test_flashes[3]['message'], $test_flashes[3]['type']);

        // Test error flashes only
        $error_flashes = $service->flashes('error');
        static::assertCount(2, $error_flashes);
        static::assertSame($test_error_flashes, $error_flashes);

        // Test the rest
        $all_flashes = $service->flashes();
        static::assertCount(count($test_flashes) - count($error_flashes), $all_flashes);

        // Clean up
        session_destroy();
        $_SESSION = [];
    }

    public function testMarkdownParser()
    {
        // Test basic markdown conversion
        static::assertSame(
            '<strong>dog</strong> <em>cat</em> <a href="src">name</a>',
            ServiceProvider::markdown('**dog** *cat* [name](src)'),
        );

        // Test array arguments
        static::assertSame('<strong>huh</strong> <em>12</em> <strong>CD</strong>', ServiceProvider::markdown('**%s** *%d* **%X**', [
            'huh',
            '12',
            205,
        ]));

        // Test variable number of arguments
        static::assertSame('<strong>huh</strong> <em>12</em> <strong>CD</strong>', ServiceProvider::markdown(
            '**%s** *%d* **%X**',
            'huh',
            '12',
            205,
        ));

        // Test second array argument overrides other arguments
        static::assertSame('<strong>huh</strong> <em>12</em> <strong>CD</strong>', ServiceProvider::markdown(
            '**%s** *%d* **%X**',
            ['huh', '12', 205],
            'dog',
            'cheese',
        ));
    }

    public function testMarkdownWithoutArgsKeepsPercentSigns()
    {
        // Used to throw a ValueError ("The arguments array must contain 2 items")
        static::assertSame(
            '100% <strong>done</strong>, 50% %s left',
            ServiceProvider::markdown('100% **done**, 50% %s left'),
        );
        static::assertSame('100% done', ServiceProvider::markdown('100% done', []));
    }

    public function testMarkdownEscapesArgs()
    {
        static::assertSame('<strong>&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;</strong>', ServiceProvider::markdown('**%s**', [
            '<script>alert("x")</script>',
        ]));
    }

    public function testFlashWithPercentSign()
    {
        $service = new ServiceProvider();

        // "% o" used to be taken as a format spec and eat the "of"
        $service->flash('Saved 100% of the files, 50% faster');

        static::assertSame(['Saved 100% of the files, 50% faster'], $service->flashes('info'));

        // Clean up
        session_destroy();
        $_SESSION = [];
    }

    public function testEscapeCharacters()
    {
        static::assertSame(
            'H&egrave;&egrave;&egrave;llo! A&amp;W root beer is now 20% off!!',
            ServiceProvider::escape('Hèèèllo! A&W root beer is now 20% off!!'),
        );
    }

    public function testEscapeNonString()
    {
        static::assertSame('42', ServiceProvider::escape(42));
        static::assertSame('1', ServiceProvider::escape(true));
        static::assertSame('', ServiceProvider::escape(null));
    }

    public function testRefresh()
    {
        $this->klein_app->respond(static function ($request, $response, $service) {
            $service->refresh();
        });

        $this->klein_app->dispatch();

        static::assertSame(
            $this->klein_app->request()->uri(),
            $this->klein_app->response()->headers()->get('location'),
        );
        static::assertTrue($this->klein_app->response()->isLocked());

        // Make sure we got a 3xx response code
        static::assertGreaterThan(299, $this->klein_app->response()->code());
        static::assertLessThan(400, $this->klein_app->response()->code());
    }

    public static function ownHostRefererProvider(): array
    {
        return [
            'same host' => ['example.com', 'https://example.com/previous?page=2'],
            'host case-insensitive' => ['example.com', 'http://EXAMPLE.com/previous'],
            'same host and port' => ['example.com:8080', 'http://example.com:8080/previous'],
        ];
    }

    #[DataProvider('ownHostRefererProvider')]
    public function testBack(string $host, string $url)
    {
        $request = new Request();
        $request->server()->set('HTTP_HOST', $host);
        $request->server()->set('HTTP_REFERER', $url);

        $this->klein_app->respond(static function ($request, $response, $service) {
            $service->back();
        });

        $this->klein_app->dispatch($request);

        static::assertSame($url, $this->klein_app->response()->headers()->get('location'));
        static::assertTrue($this->klein_app->response()->isLocked());

        // Make sure we got a 3xx response code
        static::assertGreaterThan(299, $this->klein_app->response()->code());
        static::assertLessThan(400, $this->klein_app->response()->code());
    }

    public static function foreignRefererProvider(): array
    {
        return [
            'other host' => ['example.com', 'http://google.com/'],
            'protocol-relative' => ['example.com', '//google.com/'],
            'userinfo trick' => ['example.com', 'http://example.com@evil.com/'],
            'subdomain trick' => ['example.com', 'http://example.com.evil.com/'],
            'other port' => ['example.com', 'http://example.com:8080/'],
            'javascript' => ['example.com', 'javascript:alert(1)'],
            'relative' => ['example.com', '/\\evil.com/'],
            'no request host' => [null, 'http://example.com/'],
        ];
    }

    #[DataProvider('foreignRefererProvider')]
    public function testBackIgnoresForeignReferer(string|null $host, string $url)
    {
        $server = ['REQUEST_URI' => '/current', 'HTTP_REFERER' => $url];

        if (null !== $host) {
            $server['HTTP_HOST'] = $host;
        }

        $this->klein_app->respond(static function ($request, $response, $service) {
            $service->back();
        });

        $this->klein_app->dispatch(new Request([], [], [], $server));

        // Refreshes instead of redirecting off-site
        static::assertSame('/current', $this->klein_app->response()->headers()->get('location'));
        static::assertSame(302, $this->klein_app->response()->code());
    }

    public function testBackWithoutRefererSet()
    {
        $request = new Request();

        $this->klein_app->respond(static function ($request, $response, $service) {
            $service->back();
        });

        $this->klein_app->dispatch($request);

        static::assertTrue($this->klein_app->response()->isLocked());

        // Make sure we got a 3xx response code
        static::assertGreaterThan(299, $this->klein_app->response()->code());
        static::assertLessThan(400, $this->klein_app->response()->code());
    }

    public function testLayoutGetSet()
    {
        $test_layout = 'boom!! :D';

        $service = new ServiceProvider();

        static::assertEmpty($service->layout());

        $service->layout($test_layout);

        static::assertSame($test_layout, $service->layout());
    }

    /**
     * NOTE: Also tests "yield()"
     */
    public function testRender()
    {
        $test_data = [
            'name' => 'trevor suarez',
            'title' => 'about',
            'verb' => 'woot',
        ];

        $this->klein_app->respond(static function ($request, $response, $service) use ($test_data) {
            // Set some data manually
            $service->sharedData()->set('name', 'should be overwritten');

            // Set our layout
            $service->layout(__DIR__ . '/views/layout.php');

            // Render our view, and pass some MORE data
            $service->render(__DIR__ . '/views/test.php', $test_data);
        });

        $this->klein_app->dispatch();

        $this->expectOutputString(
            '<h1>About</h1>'
            . PHP_EOL
            . 'My name is Trevor Suarez.'
            . PHP_EOL
            . 'WOOT!'
            . PHP_EOL
            . '<div>footer</div>'
            . PHP_EOL,
        );
    }

    public function testRenderChunked()
    {
        $test_data = [
            'name' => 'trevor suarez',
            'title' => 'about',
            'verb' => 'woot',
        ];

        $response = new Response();
        $response->chunk();

        $this->klein_app->respond(static function ($request, $response, $service) use ($test_data) {
            // Set some data manually
            $service->sharedData()->set('name', 'should be overwritten');

            // Set our layout
            $service->layout(__DIR__ . '/views/layout.php');

            // Render our view, and pass some MORE data
            $service->render(__DIR__ . '/views/test.php', $test_data);
        });

        $this->klein_app->dispatch(null, $response);

        $this->expectOutputString(
            '<h1>About</h1>'
            . PHP_EOL
            . 'My name is Trevor Suarez.'
            . PHP_EOL
            . 'WOOT!'
            . PHP_EOL
            . '<div>footer</div>'
            . PHP_EOL,
        );
    }

    public function testPartial()
    {
        $test_data = [
            'name' => 'trevor suarez',
            'title' => 'about',
            'verb' => 'woot',
        ];

        $this->klein_app->respond(static function ($request, $response, $service) use ($test_data) {
            // Set our layout
            $service->layout(__DIR__ . '/views/layout.php');

            // Render our view, and pass some MORE data
            $service->partial(__DIR__ . '/views/test.php', $test_data);
        });

        $this->klein_app->dispatch();

        // Make sure the layout doesn't get included
        $this->expectOutputString('My name is Trevor Suarez.' . PHP_EOL . 'WOOT!' . PHP_EOL);
    }

    public function testAddValidator()
    {
        $service = new ServiceProvider();

        // Initially empty
        static::assertEmpty(Validator::$methods);

        $test_callback = static function () {
            echo 'test';
        };

        $service->addValidator('awesome', $test_callback);

        static::assertNotEmpty(Validator::$methods);
        static::assertArrayHasKey('awesome', Validator::$methods);
        static::assertContains($test_callback, Validator::$methods);
    }

    public function testValidate()
    {
        $this->expectException(ValidationException::class);
        $this->klein_app->onError(static function ($a, $b, $c, $exception) {
            throw $exception;
        });

        $this->klein_app->respond(static function ($request, $response, $service) {
            $service->validate('thing')->isLen(3);
        });

        $this->klein_app->dispatch();
    }

    public function testValidateParam()
    {
        $this->expectException(ValidationException::class);
        $this->klein_app->onError(static function ($a, $b, $c, $exception) {
            throw $exception;
        });

        $this->klein_app->respond(static function ($request, $response, $service) {
            // Set a test param
            $request->paramsNamed()->set('name', 'trevor');

            $service->validateParam('name')->notNull()->isLen(3);
        });

        $this->klein_app->dispatch();
    }

    public function testValidateNonStringScalarIsCast()
    {
        $service = new ServiceProvider();

        static::assertTrue($service->validate(42, false)->isInt());
        static::assertTrue($service->validate(1.5, false)->isFloat());
        static::assertTrue($service->validate(true, false)->isLen(1));
    }

    public function testValidateParamArrayFailsEveryCheck()
    {
        // e.g. "?id[]=1"
        $service = new ServiceProvider(new Request(['id' => ['1']]), new Response());

        static::assertFalse($service->validateParam('id', false)->isInt());
        static::assertFalse($service->validateParam('id', false)->isNull());
        static::assertFalse($service->validateParam('id', false)->notNull());
        static::assertFalse($service->validateParam('id', false)->notInt());
    }

    public function testValidateParamArrayThrowsValidationException()
    {
        $service = new ServiceProvider(new Request(['id' => ['1']]), new Response());

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Validation failed for isInt() with value: array');

        $service->validateParam('id')->isInt();
    }

    // Test ALL of the magic setter, getter, exists, and removal methods
    public function testMagicGetSetExistsRemove()
    {
        $test_data = [
            'name' => 'huh?',
        ];

        $service = new ServiceProvider();

        static::assertEmpty($service->sharedData()->all());
        static::assertNull($service->sharedData()->get('test_data'));
        static::assertNull($service->name);
        // @mago-expect lint:no-isset (tests the magic __isset()/offsetExists())
        static::assertFalse(isset($service->name));

        $service->name = $test_data['name'];

        // @mago-expect lint:no-isset (tests the magic __isset()/offsetExists())
        static::assertTrue(isset($service->name));
        static::assertSame($test_data['name'], $service->name);

        unset($service->name);

        static::assertEmpty($service->sharedData()->all());
        static::assertNull($service->sharedData()->get('test_data'));
        static::assertNull($service->name);
        // @mago-expect lint:no-isset (tests the magic __isset()/offsetExists())
        static::assertFalse(isset($service->name));
    }
}
