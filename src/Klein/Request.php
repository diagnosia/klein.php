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

namespace Klein;

use Klein\DataCollection\DataCollection;
use Klein\DataCollection\HeaderDataCollection;
use Klein\DataCollection\ServerDataCollection;

/**
 * Request
 */
class Request
{
    /**
     * Class properties
     */

    /**
     * Unique identifier for the request
     *
     * @var string|null
     */
    protected string|null $id = null;

    /**
     * GET (query) parameters
     *
     * @var DataCollection
     */
    protected DataCollection $params_get;

    /**
     * POST parameters
     *
     * @var DataCollection
     */
    protected DataCollection $params_post;

    /**
     * Named parameters
     *
     * @var DataCollection
     */
    protected DataCollection $params_named;

    /**
     * Client cookie data
     *
     * @var DataCollection
     */
    protected DataCollection $cookies;

    /**
     * Server created attributes
     *
     * @var ServerDataCollection
     */
    protected ServerDataCollection $server;

    /**
     * HTTP request headers
     *
     * @var HeaderDataCollection
     */
    protected HeaderDataCollection $headers;

    /**
     * Uploaded temporary files
     *
     * @var DataCollection
     */
    protected DataCollection $files;

    /**
     * The request body
     *
     * Null until read from php://input
     *
     * @var string|null
     */
    protected string|null $body = null;

    /**
     * Methods
     */

    /**
     * Constructor
     *
     * Create a new Request object and define all of its request data
     *
     * @param array  $params_get
     * @param array  $params_post
     * @param array  $cookies
     * @param array  $server
     * @param array  $files
     * @param string|null $body   The request body, read from php://input if null (or empty)
     */
    public function __construct(
        array $params_get = [],
        array $params_post = [],
        array $cookies = [],
        array $server = [],
        array $files = [],
        string|null $body = null,
    ) {
        // Assignment city...
        $this->params_get = new DataCollection($params_get);
        $this->params_post = new DataCollection($params_post);
        $this->cookies = new DataCollection($cookies);
        $this->server = new ServerDataCollection($server);
        $this->headers = new HeaderDataCollection($this->server->getHeaders());
        $this->files = new DataCollection($files);
        $this->body = '' === $body ? null : $body;

        // Non-injected assignments
        $this->params_named = new DataCollection();
    }

    /**
     * Create a new request object using the built-in "superglobals"
     *
     * @link http://php.net/manual/en/language.variables.superglobals.php
     * @return static
     */
    public static function createFromGlobals(): static
    {
        // Create and return a new instance of this
        return new static($_GET, $_POST, $_COOKIE, $_SERVER, $_FILES, null); // Let our content getter take care of the "body"
    }

    /**
     * Gets a unique ID for the request
     *
     * Generates one on the first call
     *
     * @param boolean $hash     Whether or not to hash the ID on creation
     * @return string
     */
    public function id(bool $hash = true): string
    {
        if (null === $this->id) {
            $this->id = uniqid();

            if ($hash) {
                $this->id = sha1($this->id);
            }
        }

        return $this->id;
    }

    /**
     * Returns the GET parameters collection
     *
     * @return DataCollection
     */
    public function paramsGet(): DataCollection
    {
        return $this->params_get;
    }

    /**
     * Returns the POST parameters collection
     *
     * @return DataCollection
     */
    public function paramsPost(): DataCollection
    {
        return $this->params_post;
    }

    /**
     * Returns the named parameters collection
     *
     * @return DataCollection
     */
    public function paramsNamed(): DataCollection
    {
        return $this->params_named;
    }

    /**
     * Returns the cookies collection
     *
     * @return DataCollection
     */
    public function cookies(): DataCollection
    {
        return $this->cookies;
    }

    /**
     * Returns the server collection
     *
     * @return ServerDataCollection
     */
    public function server(): ServerDataCollection
    {
        return $this->server;
    }

    /**
     * Returns the headers collection
     *
     * @return HeaderDataCollection
     */
    public function headers(): HeaderDataCollection
    {
        return $this->headers;
    }

    /**
     * Returns the files collection
     *
     * @return DataCollection
     */
    public function files(): DataCollection
    {
        return $this->files;
    }

    /**
     * Gets the request body
     *
     * @return string
     */
    public function body(): string
    {
        // Only get it once
        if (null === $this->body) {
            $body = file_get_contents('php://input');
            $this->body = false === $body ? '' : $body;
        }

        return $this->body;
    }

    /**
     * Returns all parameters (GET, POST, named, and cookies) that match the mask
     *
     * Takes an optional mask param that contains the names of any params
     * you'd like this method to exclude in the returned array
     *
     * @see \Klein\DataCollection\DataCollection::all()
     * @param array|null $mask          The parameter mask array
     * @param boolean $fill_with_nulls  Whether or not to fill the returned array
     *  with null values to match the given mask
     * @return array
     */
    public function params(array|null $mask = null, bool $fill_with_nulls = true): array
    {
        /*
         * Make sure that each key in the mask has at least a
         * null value, since the user will expect the key to exist
         */
        $attributes = null !== $mask && $fill_with_nulls ? array_fill_keys($mask, value: null) : [];

        // Merge our params in the get, post, cookies, named order
        return array_merge(
            $attributes,
            $this->params_get->all($mask, false),
            $this->params_post->all($mask, false),
            $this->cookies->all($mask, false),
            $this->params_named->all($mask, false), // Add our named params last
        );
    }

    /**
     * Return a request parameter, or $default if it doesn't exist
     *
     * @param string|int $key   The name of the parameter to return
     * @param mixed $default    The default value of the parameter if it contains no value
     * @return mixed
     */
    public function param(string|int $key, mixed $default = null): mixed
    {
        // Get all of our request params
        $params = $this->params();

        return $params[$key] ?? $default;
    }

    /**
     * Magic "__isset" method
     *
     * Allows the ability to arbitrarily check the existence of a parameter
     * from this instance while treating it as an instance property
     *
     * @param string $param     The name of the parameter
     * @return boolean
     */
    public function __isset(string $param): bool
    {
        // Get all of our request params
        $params = $this->params();

        return ($params[$param] ?? null) !== null;
    }

    /**
     * Magic "__get" method
     *
     * Allows the ability to arbitrarily request a parameter from this instance
     * while treating it as an instance property
     *
     * @param string $param     The name of the parameter
     * @return mixed
     */
    public function __get(string $param): mixed
    {
        return $this->param($param);
    }

    /**
     * Magic "__set" method
     *
     * Allows the ability to arbitrarily set a parameter from this instance
     * while treating it as an instance property
     *
     * NOTE: This currently sets the "named" parameters, since that's the
     * one collection that we have the most sane control over
     *
     * @param string $param     The name of the parameter
     * @param mixed $value      The value of the parameter
     * @return void
     */
    public function __set(string $param, mixed $value): void
    {
        $this->params_named->set($param, $value);
    }

    /**
     * Magic "__unset" method
     *
     * Allows the ability to arbitrarily remove a parameter from this instance
     * while treating it as an instance property
     *
     * @param string $param     The name of the parameter
     * @return void
     */
    public function __unset(string $param): void
    {
        $this->params_named->remove($param);
    }

    /**
     * Is the request secure?
     *
     * @return boolean
     */
    public function isSecure(): bool
    {
        $https = $this->server->get('HTTPS');

        // IIS (with ISAPI) sets "off" for requests that weren't made through HTTPS
        if (is_string($https) && strcasecmp($https, 'off') === 0) {
            return false;
        }

        return (bool) $https;
    }

    /**
     * Gets the request IP address
     *
     * @return string|null
     */
    public function ip(): string|null
    {
        return $this->serverString('REMOTE_ADDR');
    }

    /**
     * Gets the request user agent
     *
     * @return string|null
     */
    public function userAgent(): string|null
    {
        $user_agent = $this->headers->get('USER_AGENT');

        return is_string($user_agent) ? $user_agent : null;
    }

    /**
     * Gets the request URI
     *
     * @return string
     */
    public function uri(): string
    {
        return $this->serverString('REQUEST_URI') ?? '/';
    }

    /**
     * Get the request's pathname
     *
     * @return string
     */
    public function pathname(): string
    {
        $uri = $this->uri();

        // Strip the query string from the URI
        $without_query = strstr($uri, needle: '?', before_needle: true);

        // Keep the URI as is when it has no query string (or nothing before it)
        if ($without_query) {
            $uri = $without_query;
        }

        return $uri;
    }

    /**
     * Gets the request method, or checks it against $is
     *
     * <code>
     * // POST request example
     * $request->method() // returns 'POST'
     * $request->method('post') // returns true
     * $request->method('get') // returns false
     * </code>
     *
     * @param string|null $is          The method to check the current request method against
     * @param boolean $allow_override   Whether or not to allow HTTP method overriding via header or params
     * @return string|boolean
     */
    public function method(string|null $is = null, bool $allow_override = true): string|bool
    {
        $method = $this->serverString('REQUEST_METHOD') ?? 'GET';

        // Override
        if ($allow_override && $method === 'POST') {
            // For legacy servers, override the HTTP method with the X-HTTP-Method-Override header or _method parameter
            // PHP exposes the header as HTTP_X_HTTP_METHOD_OVERRIDE; the unprefixed key is kept for compatibility
            $header_key = $this->server->exists('HTTP_X_HTTP_METHOD_OVERRIDE')
                ? 'HTTP_X_HTTP_METHOD_OVERRIDE'
                : 'X_HTTP_METHOD_OVERRIDE';

            $override = $this->server->exists($header_key)
                ? $this->server->get($header_key, $method)
                : $this->params_post->get('_method', $method); // Only the POST body, so a link can't override it

            // Ignore overrides that aren't a usable method name (e.g. "_method[]=x" gives an array)
            if (is_string($override) && $override !== '') {
                $method = strtoupper($override);
            }
        }

        // We're doing a check
        if (null !== $is) {
            return strcasecmp($method, $is) === 0;
        }

        return $method;
    }

    /**
     * Adds to or modifies the current query string
     *
     * @param string|array $key  The name of the query param, or an array of params to merge
     * @param mixed $value      The value of the query param
     * @return string
     */
    public function query(string|array $key, mixed $value = null): string
    {
        $query = [];

        parse_str($this->serverString('QUERY_STRING') ?? '', $query);

        $query = is_array($key) ? array_merge($query, $key) : array_replace($query, [$key => $value]);

        $request_uri = $this->uri();

        if (str_contains($request_uri, '?')) {
            $request_uri = strstr($request_uri, needle: '?', before_needle: true);
        }

        return $request_uri . ([] !== $query ? '?' . http_build_query($query) : null);
    }

    /**
     * Gets a server value if it is a string
     *
     * @param string $key   The name of the server value
     * @return string|null  Null if the value doesn't exist or isn't a string
     */
    protected function serverString(string $key): string|null
    {
        $value = $this->server->get($key);

        return is_string($value) ? $value : null;
    }
}
