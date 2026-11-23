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
use JsonException;
use Klein\DataCollection\HeaderDataCollection;
use Klein\DataCollection\ResponseCookieDataCollection;
use Klein\Exceptions\LockedResponseException;
use Klein\Exceptions\ResponseAlreadySentException;
use Klein\HttpStatus;
use Klein\Response;
use Klein\ResponseCookie;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

/**
 * ResponseTest
 */
class ResponseTest extends AbstractKleinTestCase
{
    public function testProtocolVersionGetSet()
    {
        $version_reg_ex = '/^[0-9]\.[0-9]$/';

        // Empty constructor
        $response = new Response();

        static::assertNotNull($response->protocolVersion());
        static::assertIsString($response->protocolVersion());
        static::assertMatchesRegularExpression($version_reg_ex, $response->protocolVersion());

        // Set in method
        $response = new Response();
        $response->protocolVersion('2.0');

        static::assertSame('2.0', $response->protocolVersion());
    }

    public function testBodyGetSet()
    {
        // Empty constructor
        $response = new Response();

        static::assertEmpty($response->body());

        // Body set in constructor
        $response = new Response('dog');

        static::assertSame('dog', $response->body());

        // Body set in method
        $response = new Response();
        $response->body('testing');

        static::assertSame('testing', $response->body());
    }

    public function testCodeGetSet()
    {
        // Empty constructor
        $response = new Response();

        static::assertNotNull($response->code());
        static::assertIsInt($response->code());

        // Code set in constructor
        $response = new Response(null, 503);

        static::assertSame(503, $response->code());

        // Code set in method
        $response = new Response();
        $response->code(204);

        static::assertSame(204, $response->code());
    }

    public function testStatusGetter()
    {
        $response = new Response();

        static::assertIsObject($response->status());
        static::assertTrue($response->status() instanceof HttpStatus);
    }

    public function testHeadersGetter()
    {
        $response = new Response();

        static::assertIsObject($response->headers());
        static::assertTrue($response->headers() instanceof HeaderDataCollection);
    }

    public function testCookiesGetter()
    {
        $response = new Response();

        static::assertIsObject($response->cookies());
        static::assertTrue($response->cookies() instanceof ResponseCookieDataCollection);
    }

    public function testPrepend()
    {
        $response = new Response('ein');
        $response->prepend('Kl');

        static::assertSame('Klein', $response->body());
    }

    public function testAppend()
    {
        $response = new Response('Kl');
        $response->append('ein');

        static::assertSame('Klein', $response->body());
    }

    public function testLockToggleAndGetters()
    {
        $response = new Response();

        static::assertFalse($response->isLocked());

        $response->lock();

        static::assertTrue($response->isLocked());

        $response->unlock();

        static::assertFalse($response->isLocked());
    }

    public function testLockedNotModifiable()
    {
        $response = new Response();
        $response->lock();

        // Get initial values
        $protocol_version = $response->protocolVersion();
        $body = $response->body();
        $code = $response->code();
        $headers = $response->headers()->all();
        $cookies = $response->cookies()->all();

        // Attempt to modify, every attempt has to be rejected
        $attempts = [
            'protocolVersion' => static fn() => $response->protocolVersion('2.0'),
            'body' => static fn() => $response->body('WOOT!'),
            'code' => static fn() => $response->code(204),
            'prepend' => static fn() => $response->prepend('cat'),
            'append' => static fn() => $response->append('dog'),
            'header' => static fn() => $response->header('X-Pet', 'cat'),
            'cookie' => static fn() => $response->cookie('pet', 'dog'),
            'noCache' => $response->noCache(...),
            'chunk' => $response->chunk(...),
        ];

        foreach ($attempts as $method => $attempt) {
            try {
                $attempt();
                static::fail($method . '() did not throw a LockedResponseException');
            } catch (LockedResponseException $e) {
                static::assertSame('Response is locked', $e->getMessage());
            }
        }

        // Assert nothing has changed
        static::assertSame($protocol_version, $response->protocolVersion());
        static::assertSame($body, $response->body());
        static::assertSame($code, $response->code());
        static::assertSame($headers, $response->headers()->all());
        static::assertSame($cookies, $response->cookies()->all());
        static::assertFalse($response->chunked);
    }

    /**
     * Testing headers is a pain in the ass. ;)
     *
     * Technically... we can't. So, yea.
     *
     * Attempt to run in a separate process so we can
     * at least call our internal methods
     */
    public function testSendHeaders()
    {
        $response = new Response('woot!');
        $response->headers()->set('test', 'sure');
        $response->headers()->set('Authorization', 'Basic asdasd');

        $response->sendHeaders();

        $this->expectOutputString('');
    }

    /**
     * @runInSeparateProcess
     */
    public function testSendHeadersInIsolateProcess()
    {
        $this->testSendHeaders();
    }

    /**
     * Testing cookies is exactly like testing headers
     * ... So, yea.
     */
    public function testSendCookies()
    {
        $response = new Response();
        $response->cookies()->set('test', 'woot!');
        $response->cookies()->set('Cookie-name', 'wtf?');

        $response->sendCookies();

        $this->expectOutputString('');
    }

    /**
     * @runInSeparateProcess
     */
    public function testSendCookiesInIsolateProcess()
    {
        $this->testSendCookies();
    }

    public function testSendBody()
    {
        $response = new Response('woot!');
        $response->sendBody();

        static::assertSame('woot!', $response->body());
    }

    public function testSend()
    {
        $response = new Response('woot!');
        $response->send();

        $this->expectOutputString('woot!');
        static::assertTrue($response->isLocked());
    }

    public function testSendWhenAlreadySent()
    {
        $this->expectException(ResponseAlreadySentException::class);
        $response = new Response();
        $response->send();

        static::assertTrue($response->isLocked());

        $response->send();
    }

    /**
     * This uses some crazy exploitation to make sure that the
     * `fastcgi_finish_request()` function gets called.
     * Because of this, this MUST be run in a separate process
     */
    #[RunInSeparateProcess]
    public function testSendCallsFastCGIFinishRequest()
    {
        // Custom fastcgi function
        implement_custom_fastcgi_function();

        $this->expectOutputString('');

        $response = new Response();
        $response->send();
    }

    public function testChunk()
    {
        $content = [
            'initial content',
            'more',
            'content',
        ];

        $response = new Response($content[0]);

        $response->chunk();
        $response->chunk($content[1]);
        $response->chunk($content[2]);

        // Each chunk is prefixed with its length in hex
        $this->expectOutputString(
            dechex(strlen($content[0]))
            . "\r\n{$content[0]}\r\n"
            . dechex(strlen($content[1]))
            . "\r\n{$content[1]}\r\n"
            . dechex(strlen($content[2]))
            . "\r\n{$content[2]}\r\n",
        );

        static::assertTrue($response->chunked);
        static::assertSame('chunked', $response->headers()->get('Transfer-Encoding'));
    }

    public function testSendEndsChunkedResponse()
    {
        $response = new Response('initial');
        $response->chunk('more');
        $response->body('rest');

        // The rest of the body as a last chunk, then the empty chunk that terminates the response
        $this->expectOutputString("7\r\ninitial\r\n4\r\nmore\r\n4\r\nrest\r\n0\r\n\r\n");

        $response->send();

        static::assertTrue($response->isSent());
    }

    public function testChunkWithEmptyStringWritesNothing()
    {
        $response = new Response();
        $response->chunk();

        // An empty chunk ("0\r\n\r\n") would terminate the response early
        $this->expectOutputString('');

        $response->chunk('');
    }

    public function testChunkAfterSendThrows()
    {
        $response = new Response();
        $response->chunk('data');
        $response->send();

        $this->expectOutputString("4\r\ndata\r\n0\r\n\r\n");
        $this->expectException(ResponseAlreadySentException::class);

        $response->chunk('too late');
    }

    public function testChunkOfLockedResponseWritesNothing()
    {
        $response = new Response();
        $response->chunk();
        $response->body('locked body')->lock();

        $this->expectOutputString('');

        try {
            $response->chunk();
            static::fail('Expected a LockedResponseException');
        } catch (LockedResponseException) {
            // Nothing written, so no half written chunk
            static::assertSame('locked body', $response->body());
        }
    }

    public function testHeader()
    {
        $headers = [
            'test' => 'woot!',
            'test' => 'sure',
            'okay' => 'yup',
        ];

        $response = new Response();

        // Make sure the headers are initially empty
        static::assertEmpty($response->headers()->all());

        // Set the headers
        foreach ($headers as $name => $value) {
            $response->header($name, $value);
        }

        static::assertNotEmpty($response->headers()->all());

        // Set the headers
        foreach ($headers as $name => $value) {
            static::assertSame($value, $response->headers()->get($name));
        }
    }

    /**
     * @group testCookie
     */
    public function testCookie()
    {
        $test_cookie_data = [
            'name' => 'name',
            'value' => 'value',
            'expiry' => null,
            'path' => '/path',
            'domain' => 'whatever.com',
            'secure' => true,
            'httponly' => true,
        ];

        $test_cookie = new ResponseCookie(
            $test_cookie_data['name'],
            $test_cookie_data['value'],
            $test_cookie_data['expiry'],
            $test_cookie_data['path'],
            $test_cookie_data['domain'],
            $test_cookie_data['secure'],
            $test_cookie_data['httponly'],
        );

        $response = new Response();

        // Make sure the cookies are initially empty
        static::assertEmpty($response->cookies()->all());

        // Set a cookies
        $response->cookie(
            $test_cookie_data['name'],
            $test_cookie_data['value'],
            $test_cookie_data['expiry'],
            $test_cookie_data['path'],
            $test_cookie_data['domain'],
            $test_cookie_data['secure'],
            $test_cookie_data['httponly'],
        );

        static::assertNotEmpty($response->cookies()->all());

        $the_cookie = $response->cookies()->get($test_cookie_data['name']);

        static::assertNotNull($the_cookie);
        static::assertTrue($the_cookie instanceof ResponseCookie);
        static::assertSame($test_cookie_data['name'], $the_cookie->getName());
        static::assertSame($test_cookie_data['value'], $the_cookie->getValue());
        static::assertSame($test_cookie_data['path'], $the_cookie->getPath());
        static::assertSame($test_cookie_data['domain'], $the_cookie->getDomain());
        static::assertSame($test_cookie_data['secure'], $the_cookie->getSecure());
        static::assertSame($test_cookie_data['httponly'], $the_cookie->getHttpOnly());
        static::assertNotNull($the_cookie->getExpire());
    }

    public function testCookieWithNonStringValue()
    {
        $response = new Response();

        $response->cookie('count', 5, '3600');

        static::assertSame('5', $response->cookies()->get('count')->getValue());
        static::assertSame(3600, $response->cookies()->get('count')->getExpire());
    }

    public function testNoCache()
    {
        $response = new Response();

        // Make sure the headers are initially empty
        static::assertEmpty($response->headers()->all());

        $response->noCache();

        static::assertArrayHasKey('Cache-Control', $response->headers()->all());
        static::assertSame('no-store, no-cache', $response->headers()->all()['Cache-Control']);
    }

    public function testRedirect()
    {
        $url = 'http://google.com/';
        $code = 302;

        $response = new Response();
        $response->redirect($url, $code);

        static::assertSame($code, $response->code());
        static::assertSame($url, $response->headers()->get('location'));
        static::assertTrue($response->isLocked());
    }

    public function testDump()
    {
        $response = new Response();

        static::assertEmpty($response->body());

        $response->dump('test');

        static::assertStringContainsString('test', $response->body());
    }

    public function testDumpArray()
    {
        $response = new Response();

        static::assertEmpty($response->body());

        $response->dump(['sure', 1, 10, 17, 'ok' => 'no']);

        static::assertNotEmpty($response->body());
        static::assertNotSame('<pre></pre>', $response->body());
    }

    public static function dumpScalarProvider(): array
    {
        return [
            'int' => [42, '42'],
            'float' => [1.5, '1.5'],
            'true' => [true, '1'],
            'false' => [false, ''],
            'null' => [null, ''],
        ];
    }

    #[DataProvider('dumpScalarProvider')]
    public function testDumpNonStringScalar(mixed $value, string $expected)
    {
        $response = new Response();

        $response->dump($value);

        static::assertSame('<pre>' . $expected . "</pre><br />\n", $response->body());
    }

    public function testFileSend()
    {
        $file_name = 'testing';
        $file_mime = 'text/plain';

        $this->klein_app->respond(static function ($request, $response, $service) use ($file_name, $file_mime) {
            $response->file(__FILE__, $file_name, $file_mime);
        });

        $this->klein_app->dispatch();

        // Expect our output to match our file
        $this->expectOutputString(file_get_contents(__FILE__));

        // Assert headers were passed
        static::assertEquals($file_mime, $this->klein_app->response()->headers()->get('Content-Type'));
        static::assertEquals(filesize(__FILE__), $this->klein_app->response()->headers()->get('Content-Length'));
        static::assertStringContainsString(
            $file_name,
            $this->klein_app->response()->headers()->get('Content-Disposition'),
        );
    }

    public static function attachmentFilenameProvider(): array
    {
        return [
            'plain' => ['report.pdf', 'attachment; filename="report.pdf"'],
            'quote' => ['a"b.txt', 'attachment; filename="a_b.txt"; filename*=UTF-8\'\'a%22b.txt'],
            'backslash' => ['a\\b.txt', 'attachment; filename="a_b.txt"; filename*=UTF-8\'\'a%5Cb.txt'],
            'non-ASCII' => [
                'Bericht ä.pdf',
                'attachment; filename="Bericht __.pdf"; filename*=UTF-8\'\'Bericht%20%C3%A4.pdf',
            ],
            'line break' => [
                "evil\r\nX-Injected: 1",
                'attachment; filename="evil__X-Injected: 1"; filename*=UTF-8\'\'evil%0D%0AX-Injected%3A%201',
            ],
        ];
    }

    #[DataProvider('attachmentFilenameProvider')]
    public function testFileSendEscapesFilename(string $filename, string $expected)
    {
        $this->expectOutputString(file_get_contents(__FILE__));

        $response = new Response();
        $response->file(__FILE__, $filename, 'text/plain');

        static::assertSame($expected, $response->headers()->get('Content-Disposition'));
    }

    public function testCookieWithSameSite()
    {
        $response = new Response();

        $response->cookie('session', 'abc', null, '/', null, true, true, 'strict');

        static::assertSame('Strict', $response->cookies()->get('session')->getSameSite());
        static::assertNull(
            new Response()
                ->cookie('plain')
                ->cookies()
                ->get('plain')
                ->getSameSite(),
        );
    }

    #[RunInSeparateProcess]
    public function testSendCookiesWithSameSite()
    {
        if (!function_exists('xdebug_get_headers')) {
            static::markTestSkipped('Needs xdebug_get_headers() to inspect the sent headers');
        }

        $response = new Response();
        $response->cookie('session', 'abc', 0, '/', null, true, true, 'Lax');
        $response->cookies()->set('empty', null); // A null value used to throw a TypeError in setcookie()

        $response->sendCookies();

        $headers = xdebug_get_headers(); // @mago-expect lint:no-debug-symbols

        static::assertContains('Set-Cookie: session=abc; path=/; secure; HttpOnly; SameSite=Lax', $headers);
        static::assertCount(2, array_filter($headers, static fn($h) => str_starts_with($h, 'Set-Cookie: ')));
    }

    public function testFileSendLooseArgs()
    {
        $this->klein_app->respond(static function ($request, $response, $service) {
            $response->file(__FILE__);
        });

        $this->klein_app->dispatch();

        // Expect our output to match our file
        $this->expectOutputString(file_get_contents(__FILE__));

        // Assert headers were passed
        static::assertEquals(filesize(__FILE__), $this->klein_app->response()->headers()->get('Content-Length'));
        static::assertNotNull($this->klein_app->response()->headers()->get('Content-Type'));
        static::assertNotNull($this->klein_app->response()->headers()->get('Content-Disposition'));
    }

    public function testFileSendWhenAlreadySent()
    {
        $this->expectException(ResponseAlreadySentException::class);
        // Expect our output to match our file
        $this->expectOutputString(file_get_contents(__FILE__));

        $response = new Response();
        $response->file(__FILE__);

        static::assertTrue($response->isLocked());

        $response->file(__FILE__);
    }

    /**
     * This uses some crazy exploitation to make sure that the
     * `fastcgi_finish_request()` function gets called.
     * Because of this, this MUST be run in a separate process
     */
    #[RunInSeparateProcess]
    public function testFileSendCallsFastCGIFinishRequest()
    {
        // Custom fastcgi function
        implement_custom_fastcgi_function();

        // Expect our output to match our file
        $this->expectOutputString(file_get_contents(__FILE__) . '');

        $response = new Response();
        $response->file(__FILE__);
    }

    public function testJSON()
    {
        // Create a test object to be JSON encoded/decoded
        $test_object = (object) [
            'cheese',
            'dog' => 'bacon',
            1 => 'should be 1 (thanks PHP casting...)',
            'integer' => 1,
            'double' => 1.5,
            '_weird' => true,
            'uniqid' => uniqid(),
        ];

        $this->klein_app->respond(static function ($request, $response, $service) use ($test_object) {
            $response->json($test_object);
        });

        $this->klein_app->dispatch();

        // Expect our output to match our json encoded test object
        $this->expectOutputString(json_encode($test_object));

        // Assert headers were passed
        static::assertSame('no-cache', $this->klein_app->response()->headers()->get('Pragma'));
        static::assertSame('no-store, no-cache', $this->klein_app->response()->headers()->get('Cache-Control'));
        static::assertSame('application/json', $this->klein_app->response()->headers()->get('Content-Type'));
    }

    public function testJSONWithPrefix()
    {
        // Create a test object to be JSON encoded/decoded
        $test_object = [
            'cheese',
        ];
        $prefix = 'dogma';

        $this->klein_app->respond(static function ($request, $response, $service) use ($test_object, $prefix) {
            $response->json($test_object, $prefix);
        });

        $this->klein_app->dispatch();

        // Expect our output to match our json encoded test object
        $this->expectOutputString('dogma(' . json_encode($test_object) . ');');

        // Assert headers were passed
        static::assertSame('no-cache', $this->klein_app->response()->headers()->get('Pragma'));
        static::assertSame('no-store, no-cache', $this->klein_app->response()->headers()->get('Cache-Control'));
        static::assertSame('text/javascript', $this->klein_app->response()->headers()->get('Content-Type'));
    }

    public function testJSONWithUnencodableDataThrows()
    {
        $response = new Response('previous body');

        try {
            // Invalid UTF-8 used to give an empty 200 response
            $response->json(['name' => "\xB1\x31"]);
            static::fail('Expected a JsonException');
        } catch (JsonException) {
            static::assertSame('previous body', $response->body());
            static::assertNull($response->headers()->get('Content-Type'));
            static::assertFalse($response->isSent());
        }
    }

    public static function unreadableFileProvider(): array
    {
        return [
            'missing file' => [__DIR__ . '/some/bogus/path/that/does/not/exist'],
            'directory' => [__DIR__],
        ];
    }

    #[DataProvider('unreadableFileProvider')]
    public function testFileSendWithUnreadableFileSendsNothing(string $path)
    {
        // No output, and no warnings from readfile()/filesize() either
        $this->expectOutputString('');

        $response = new Response('previous body');

        try {
            $response->file($path);
            static::fail('Expected a RuntimeException');
        } catch (\RuntimeException $e) {
            static::assertSame('The file could not be read', $e->getMessage());
            static::assertSame('previous body', $response->body());
            static::assertNull($response->headers()->get('Content-Length'));
            static::assertNull($response->headers()->get('Content-Disposition'));
            static::assertFalse($response->isSent());
            static::assertFalse($response->isLocked());
        }
    }

    public static function validJsonpCallbackProvider(): array
    {
        return [
            'plain' => ['dogma'],
            'jQuery style' => ['jQuery3710_1700000000000'],
            'dollar and underscore' => ['$_cb$'],
            'dotted' => ['app.callbacks.done'],
        ];
    }

    #[DataProvider('validJsonpCallbackProvider')]
    public function testJSONWithValidPrefix(string $prefix)
    {
        $this->expectOutputString($prefix . '(["cheese"]);');

        $response = new Response();
        $response->json(['cheese'], $prefix);

        static::assertSame('text/javascript', $response->headers()->get('Content-Type'));
    }

    public static function invalidJsonpCallbackProvider(): array
    {
        return [
            'script injection' => ['alert(document.cookie);//'],
            'html' => ['<script>alert(1)</script>'],
            'empty' => [''],
            'leading digit' => ['1cb'],
            'trailing dot' => ['cb.'],
            'double dot' => ['a..b'],
            'brackets' => ['cb[0]'],
            'whitespace' => ['cb x'],
            'trailing newline' => ["cb\n"],
        ];
    }

    #[DataProvider('invalidJsonpCallbackProvider')]
    public function testJSONWithInvalidPrefixThrows(string $prefix)
    {
        $response = new Response('previous body');

        try {
            $response->json(['cheese'], $prefix);
            static::fail('Expected an InvalidArgumentException');
        } catch (InvalidArgumentException) {
            // The response must be left untouched and unsent
            static::assertSame('previous body', $response->body());
            static::assertNull($response->headers()->get('Content-Type'));
            static::assertFalse($response->isSent());
        }
    }
}
