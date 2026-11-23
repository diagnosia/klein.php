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

use Klein\Request;
use Klein\Tests\Mocks\MockRequestFactory;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * RequestTest
 */
class RequestTest extends AbstractKleinTestCase
{
    public function testConstructorAndGetters()
    {
        // Test data
        $params_get = ['get'];
        $params_post = ['post'];
        $cookies = ['cookies'];
        $server = ['server'];
        $files = ['files'];
        $body = 'body';

        // Create the request
        $request = new Request($params_get, $params_post, $cookies, $server, $files, $body);

        // Make sure our data's the same
        static::assertSame($params_get, $request->paramsGet()->all());
        static::assertSame($params_post, $request->paramsPost()->all());
        static::assertSame($cookies, $request->cookies()->all());
        static::assertSame($server, $request->server()->all());
        static::assertSame($files, $request->files()->all());
        static::assertSame($body, $request->body());
    }

    public function testGlobalsCreation()
    {
        // Create a unique key
        $key = uniqid();

        // Test data
        $_GET = array_merge($_GET, [$key => 'get']);
        $_POST = array_merge($_POST, [$key => 'post']);
        $_COOKIE = array_merge($_COOKIE, [$key => 'cookies']);
        $_SERVER = array_merge($_SERVER, [$key => 'server']);
        $_FILES = array_merge($_FILES, [$key => 'files']);

        // Create the request
        $request = Request::createFromGlobals();

        // Make sure our data's the same
        static::assertSame($_GET[$key], $request->paramsGet()->get($key));
        static::assertSame($_POST[$key], $request->paramsPost()->get($key));
        static::assertSame($_COOKIE[$key], $request->cookies()->get($key));
        static::assertSame($_SERVER[$key], $request->server()->get($key));
        static::assertSame($_FILES[$key], $request->files()->get($key));
    }

    public function testUniversalParams()
    {
        // Test data
        $params_get = ['page' => 2, 'per_page' => 10, 'num' => 1, 5 => 'ok', 'empty' => null, 'blank' => ''];
        $params_post = ['first_name' => 'Trevor', 'last_name' => 'Suarez', 'num' => 2, 3 => 'hmm', 4 => 'thing'];
        $cookies = ['user' => 'Rican7', 'PHPSESSID' => 'randomstring', 'num' => 3, 4 => 'dog'];
        $named = ['id' => '1f8ae', 'num' => 4];

        // Create the request
        $request = new Request($params_get, $params_post, $cookies);

        // Set our named params
        $request->paramsNamed()->replace($named);

        // Merge our params for our expected results
        $params = array_merge($params_get, $params_post, $cookies, $named);

        static::assertSame($params, $request->params());
        static::assertSame($params['num'], $request->param('num'));
        static::assertNull($request->param('thisdoesntexist'));
    }

    public function testUniversalParamsWithFilter()
    {
        // Test data
        $params_get = ['page' => 2, 'per_page' => 10, 'num' => 1, 5 => 'ok', 'empty' => null, 'blank' => ''];
        $params_post = ['first_name' => 'Trevor', 'last_name' => 'Suarez', 'num' => 2, 3 => 'hmm', 4 => 'thing'];
        $cookies = ['user' => 'Rican7', 'PHPSESSID' => 'randomstring', 'num' => 3, 4 => 'dog'];

        // Create our filter and expected results
        $filter = ['page', 'user', 'num', 'this-key-never-showed-up-anywhere'];
        $expected = ['page' => 2, 'user' => 'Rican7', 'num' => 3, 'this-key-never-showed-up-anywhere' => null];

        // Create the request
        $request = new Request($params_get, $params_post, $cookies);

        static::assertSame($expected, $request->params($filter));
    }

    public function testMagic()
    {
        // Test data
        $params = ['page' => 2, 'per_page' => 10, 'num' => 1];

        // Create the request
        $request = new Request($params);

        // Test Exists
        // @mago-expect lint:no-isset (tests the magic __isset()/offsetExists())
        static::assertTrue(isset($request->per_page));

        // Test Getter
        static::assertSame($params['per_page'], $request->per_page);

        // Test Setter
        static::assertSame($request->test = '#yup', $request->param('test'));

        // Test Unsetter
        unset($request->test);
        static::assertNull($request->param('test'));
    }

    public function testSecure()
    {
        $request = new Request();
        $request->server()->set('HTTPS', true);

        static::assertTrue($request->isSecure());
    }

    public static function secureServerValueProvider(): array
    {
        return [
            'on' => ['on', true],
            '1' => ['1', true],
            'true' => [true, true],
            'off (IIS)' => ['off', false],
            'OFF (IIS)' => ['OFF', false],
            'empty' => ['', false],
            'null' => [null, false],
        ];
    }

    #[DataProvider('secureServerValueProvider')]
    public function testSecureServerValues(mixed $https, bool $expected)
    {
        $request = new Request();
        $request->server()->set('HTTPS', $https);

        static::assertSame($expected, $request->isSecure());
    }

    public function testNotSecureWithoutHttps()
    {
        static::assertFalse(new Request()->isSecure());
    }

    public function testIp()
    {
        // Test data
        $ip = '127.0.0.1';

        $request = new Request();
        $request->server()->set('REMOTE_ADDR', $ip);

        static::assertSame($ip, $request->ip());
    }

    public function testUserAgent()
    {
        // Test data
        $user_agent = 'phpunittt';

        $request = new Request();
        $request->headers()->set('USER_AGENT', $user_agent);

        static::assertSame($user_agent, $request->userAgent());
    }

    public function testUri()
    {
        // Test data
        $uri = 'localhostofthingsandstuff';
        $query = '?q=search';

        $request = new Request();
        $request->server()->set('REQUEST_URI', $uri . $query);

        static::assertSame($uri . $query, $request->uri());
    }

    public function testPathname()
    {
        // Test data
        $uri = 'localhostofthingsandstuff';
        $query = '?q=search';

        $request = new Request();
        $request->server()->set('REQUEST_URI', $uri . $query);

        static::assertSame($uri, $request->pathname());
    }

    public function testBody()
    {
        // Test data
        $body = '_why is an interesting guy<br> - Trevor';

        // Blank constructor
        $request = new Request();

        static::assertEmpty($request->body());

        // In constructor
        $request = new Request([], [], [], [], [], $body);

        static::assertSame($body, $request->body());
    }

    public function testBodyZeroIsKept()
    {
        // "0" is falsy, so it used to be dropped (and php://input read instead)
        $request = new Request([], [], [], [], [], '0');

        static::assertSame('0', $request->body());
    }

    public function testMethod()
    {
        // Test data
        $method = 'PATCH';

        $request = new Request();
        $request->server()->set('REQUEST_METHOD', $method);

        static::assertSame($method, $request->method());
        static::assertTrue($request->method($method));
        static::assertTrue($request->method(strtolower($method)));
    }

    public function testMethodOverride()
    {
        // Test data
        $method = 'POST';
        $override_method = 'TRACE';
        $weird_override_method = 'DELETE';

        $request = new Request();
        $request->server()->set('REQUEST_METHOD', $method);
        // The key PHP uses for the X-HTTP-Method-Override header
        $request->server()->set('HTTP_X_HTTP_METHOD_OVERRIDE', $override_method);

        static::assertSame($override_method, $request->method());
        static::assertTrue($request->method($override_method));
        static::assertTrue($request->method(strtolower($override_method)));

        $request->server()->remove('HTTP_X_HTTP_METHOD_OVERRIDE');
        $request->paramsPost()->set('_method', $weird_override_method);

        static::assertSame($weird_override_method, $request->method());
        static::assertTrue($request->method($weird_override_method));
        static::assertTrue($request->method(strtolower($weird_override_method)));
    }

    public function testMethodOverrideParamOnlyFromPostBody()
    {
        $server = ['REQUEST_METHOD' => 'POST'];

        // A query string or cookie could be set by a plain link or another site, so they must not override
        static::assertSame('POST', new Request(['_method' => 'DELETE'], [], [], $server)->method());
        static::assertSame('POST', new Request([], [], ['_method' => 'DELETE'], $server)->method());
        static::assertSame('DELETE', new Request([], ['_method' => 'DELETE'], [], $server)->method());
    }

    public function testMethodOverrideHeaderFromServerGlobals()
    {
        // As createFromGlobals() would get it for a request with an "X-HTTP-Method-Override: put" header
        $request = new Request([], [], [], ['REQUEST_METHOD' => 'POST', 'HTTP_X_HTTP_METHOD_OVERRIDE' => 'put']);

        static::assertSame('PUT', $request->method());
        static::assertSame('put', $request->headers()->get('X-HTTP-Method-Override'));
    }

    public function testMethodOverrideHeaderWinsOverParam()
    {
        $request = new Request(
            [],
            ['_method' => 'DELETE'],
            [],
            [
                'REQUEST_METHOD' => 'POST',
                'HTTP_X_HTTP_METHOD_OVERRIDE' => 'PATCH',
            ],
        );

        static::assertSame('PATCH', $request->method());
    }

    public function testMethodOverrideLegacyServerKey()
    {
        // The unprefixed key, which older code (and tests) set directly
        $request = new Request([], [], [], ['REQUEST_METHOD' => 'POST', 'X_HTTP_METHOD_OVERRIDE' => 'PUT']);

        static::assertSame('PUT', $request->method());
    }

    public function testMethodOverrideHeaderIgnoredWithoutPost()
    {
        $request = new Request([], [], [], ['REQUEST_METHOD' => 'GET', 'HTTP_X_HTTP_METHOD_OVERRIDE' => 'DELETE']);

        static::assertSame('GET', $request->method());
    }

    public static function unusableMethodOverrideProvider(): array
    {
        return [
            'array (_method[]=x)' => [['DELETE']],
            'empty string' => [''],
        ];
    }

    #[DataProvider('unusableMethodOverrideProvider')]
    public function testMethodOverrideIgnoresUnusableValue(mixed $override)
    {
        $request = new Request([], ['_method' => $override], [], ['REQUEST_METHOD' => 'POST']);

        static::assertSame('POST', $request->method());
    }

    public function testQueryModify()
    {
        $test_uri = '/test?query';
        $query_string = 'search=string&page=2&per_page=3';
        $test_one = '';
        $test_two = '';
        $test_three = '';

        $request = new Request();
        $request->server()->set('REQUEST_URI', $test_uri);
        $request->server()->set('QUERY_STRING', $query_string);

        $this->klein_app->respond(static function ($request, $response, $service) use (
            &$test_one,
            &$test_two,
            &$test_three,
        ) {
            // Add a new var
            $test_one = $request->query('test', 'dog');

            // Modify a current var
            $test_two = $request->query('page', 7);

            // Modify a current var
            $test_three = $request->query(['per_page' => 10]);
        });

        $this->klein_app->dispatch($request);

        $expected_uri = parse_url($this->klein_app->request()->uri(), PHP_URL_PATH);

        static::assertSame($expected_uri . '?' . $query_string . '&test=dog', $test_one);

        static::assertSame(
            $expected_uri . '?' . str_replace('page=2', replace: 'page=7', subject: $query_string),
            $test_two,
        );

        static::assertSame(
            $expected_uri . '?' . str_replace('per_page=3', replace: 'per_page=10', subject: $query_string),
            $test_three,
        );
    }

    public function testQueryWithoutQueryString()
    {
        // A URL without "?" has no QUERY_STRING server entry at all
        $request = new Request([], [], [], ['REQUEST_URI' => '/test']);

        static::assertSame('/test?test=dog', $request->query('test', 'dog'));
        static::assertSame('/test', $request->query([]));
    }

    public function testId()
    {
        // Create two requests
        $request_one = new Request();
        $request_two = new Request();

        // Make sure the ID's aren't null
        static::assertNotNull($request_one->id());
        static::assertNotNull($request_two->id());

        // Make sure that multiple calls yield the same result
        static::assertSame($request_one->id(), $request_one->id());
        static::assertSame($request_one->id(), $request_one->id());
        static::assertSame($request_two->id(), $request_two->id());
        static::assertSame($request_two->id(), $request_two->id());

        // Make sure the ID's are unique to each request
        static::assertNotSame($request_one->id(), $request_two->id());
    }

    public function testMockFactory()
    {
        // Test data
        $uri = '/test/uri';
        $method = 'OPTIONS';
        $params = ['get'];
        $cookies = ['cookies'];
        $server = ['server'];
        $files = ['files'];
        $body = 'body';

        // Create the request
        $request = MockRequestFactory::create($uri, $method, $params, $cookies, $server, $files, $body);

        // Make sure our data's the same
        static::assertSame($uri, $request->uri());
        static::assertSame($method, $request->method());
        static::assertSame($params, $request->paramsGet()->all());

        static::assertSame([], $request->paramsPost()->all());
        static::assertSame([], $request->paramsNamed()->all());
        static::assertSame($cookies, $request->cookies()->all());
        static::assertContains($cookies[0], $request->params());
        static::assertContains($server[0], $request->server()->all());
        static::assertSame($files, $request->files()->all());
        static::assertSame($body, $request->body());
    }
}
