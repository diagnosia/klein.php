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

use Klein\DataCollection\RouteCollection;
use Klein\Exceptions\DispatchHaltedException;
use Klein\Exceptions\HttpException;
use Klein\Exceptions\HttpExceptionInterface;
use Klein\Exceptions\LockedResponseException;
use Klein\Exceptions\RegularExpressionCompilationException;
use Klein\Exceptions\RoutePathCompilationException;
use Klein\Exceptions\UnhandledException;
use OutOfBoundsException;
use SplQueue;
use SplStack;
use Throwable;

/**
 * Klein
 *
 * Main Klein router class
 */
class Klein
{
    /**
     * Class constants
     */

    /**
     * The regular expression used to compile and match URL's
     *
     * @var string
     */
    const ROUTE_COMPILE_REGEX = '`(\\\?(?:/|\.|))(?:\[([^:\]]*)(?::([^:\]]*))?\])(\?|)`';

    /**
     * The regular expression used to escape the non-named param section of a route URL
     *
     * @var string
     */
    const ROUTE_ESCAPE_REGEX = '`(?<=^|\])[^\]\[\?]+?(?=\[|$)`';

    /**
     * Dispatch route output handling
     *
     * Don't capture anything. Behave as normal.
     *
     * @var int
     */
    const DISPATCH_NO_CAPTURE = 0;

    /**
     * Dispatch route output handling
     *
     * Capture all output and return it from dispatch
     *
     * @var int
     */
    const DISPATCH_CAPTURE_AND_RETURN = 1;

    /**
     * Dispatch route output handling
     *
     * Capture all output and replace the response body with it
     *
     * @var int
     */
    const DISPATCH_CAPTURE_AND_REPLACE = 2;

    /**
     * Dispatch route output handling
     *
     * Capture all output and prepend it to the response body
     *
     * @var int
     */
    const DISPATCH_CAPTURE_AND_PREPEND = 3;

    /**
     * Dispatch route output handling
     *
     * Capture all output and append it to the response body
     *
     * @var int
     */
    const DISPATCH_CAPTURE_AND_APPEND = 4;

    /**
     * Class properties
     */

    /**
     * The types to detect in a defined match "block"
     *
     * Examples of these blocks are as follows:
     *
     * - integer:       '[i:id]'
     * - alphanumeric:  '[a:username]'
     * - hexadecimal:   '[h:color]'
     * - slug:          '[s:article]'
     *
     * @var array
     */
    protected $match_types = [
        'i' => '[0-9]++',
        'a' => '[0-9A-Za-z]++',
        'h' => '[0-9A-Fa-f]++',
        's' => '[0-9A-Za-z-_]++',
        '*' => '.+?',
        '**' => '.++',
        '' => '[^/]+?',
    ];

    /**
     * Collection of the routes to match on dispatch
     *
     * @var RouteCollection
     */
    protected $routes;

    /**
     * The Route factory object responsible for creating Route instances
     *
     * @var AbstractRouteFactory
     */
    protected $route_factory;

    /**
     * A stack of error callback callables
     *
     * @var SplStack
     */
    protected $error_callbacks;

    /**
     * A stack of HTTP error callback callables
     *
     * @var SplStack
     */
    protected $http_error_callbacks;

    /**
     * A queue of callbacks to call after processing the dispatch loop
     * and before the response is sent
     *
     * @var SplQueue
     */
    protected $after_filter_callbacks;

    /**
     * The output buffer level used by the dispatch process
     *
     * @var int
     */
    private $output_buffer_level;

    /**
     * Route objects
     */

    /**
     * The Request object passed to each matched route
     *
     * @var Request
     */
    protected $request;

    /**
     * The Response object passed to each matched route
     *
     * @var AbstractResponse
     */
    protected $response;

    /**
     * The service provider object passed to each matched route
     *
     * @var ServiceProvider
     */
    protected $service;

    /**
     * A generic variable passed to each matched route
     *
     * @var mixed
     */
    protected $app;

    /**
     * Methods
     */

    /**
     * Constructor
     *
     * Create a new Klein instance with optionally injected dependencies
     * This DI allows for easy testing, object mocking, or class extension
     *
     * @param ServiceProvider $service              Service provider object responsible for utilitarian behaviors
     * @param mixed $app                            An object passed to each route callback, defaults to an App instance
     * @param RouteCollection $routes               Collection object responsible for containing all route instances
     * @param AbstractRouteFactory $route_factory   A factory class responsible for creating Route instances
     */
    public function __construct(
        ServiceProvider|null $service = null,
        $app = null,
        RouteCollection|null $routes = null,
        AbstractRouteFactory|null $route_factory = null,
    ) {
        // Instanciate and fall back to defaults
        $this->service = $service ?? new ServiceProvider();
        $this->app = $app ?? new App();
        $this->routes = $routes ?? new RouteCollection();
        $this->route_factory = $route_factory ?? new RouteFactory();

        $this->error_callbacks = new SplStack();
        $this->http_error_callbacks = new SplStack();
        $this->after_filter_callbacks = new SplQueue();
    }

    /**
     * Returns the routes object
     *
     * @return RouteCollection
     */
    public function routes()
    {
        return $this->routes;
    }

    /**
     * Returns the request object
     *
     * @return Request
     */
    public function request()
    {
        return $this->request;
    }

    /**
     * Returns the response object
     *
     * @return Response
     */
    public function response()
    {
        return $this->response;
    }

    /**
     * Returns the service object
     *
     * @return ServiceProvider
     */
    public function service()
    {
        return $this->service;
    }

    /**
     * Returns the app object
     *
     * @return mixed
     */
    public function app()
    {
        return $this->app;
    }

    /**
     * Parse our extremely loose argument order of our "respond" method and its aliases
     *
     * This method takes its arguments in a loose format and order.
     * The method signature is simply there for documentation purposes, but allows
     * for the minimum of a callback to be passed in its current configuration.
     *
     * @see Klein::respond()
     * @param mixed $args               An argument array. Hint: This works well when passing "func_get_args()"
     *  @named string | array $method   HTTP Method to match
     *  @named string $path             Route URI path to match
     *  @named callable $callback       Callable callback method to execute on route match
     * @return array                    A named parameter array containing the keys: 'method', 'path', and 'callback'
     */
    protected function parseLooseArgumentOrder(array $args): array
    {
        // Get the arguments in a very loose format
        $callback = array_pop($args);
        $path = array_pop($args);
        $method = array_pop($args);

        // Return a named parameter array
        return [
            'method' => $method,
            'path' => $path,
            'callback' => $callback,
        ];
    }

    /**
     * Add a new route to be matched on dispatch
     *
     * Essentially, this method is a standard "Route" builder/factory,
     * allowing a loose argument format and a standard way of creating
     * Route instances
     *
     * This method takes its arguments in a very loose format
     * The only "required" parameter is the callback (which is very strange considering the argument definition order)
     *
     * <code>
     * $router = new Klein();
     *
     * $router->respond( function() {
     *     echo 'this works';
     * });
     * $router->respond( '/endpoint', function() {
     *     echo 'this also works';
     * });
     * $router->respond( 'POST', '/endpoint', function() {
     *     echo 'this also works!!!!';
     * });
     * </code>
     *
     * @param string|array|callable $method     HTTP Method to match
     * @param string|callable $path             Route URI path to match
     * @param callable $callback                Callable callback method to execute on route match
     *
     * @return Route
     */
    public function respond($method, $path = '*', $callback = null): Route
    {
        // Get the arguments in a very loose format
        extract($this->parseLooseArgumentOrder(func_get_args()), EXTR_OVERWRITE);

        $route = $this->route_factory->build($callback, $path, $method);

        $this->routes->add($route);

        return $route;
    }

    /**
     * Collect a set of routes under a common namespace
     *
     * The routes may be passed in as either a callable (which holds the route definitions),
     * or as a string of a filename, of which to "include" under the Klein router scope
     *
     * <code>
     * $router = new Klein();
     *
     * $router->with('/users', function($router) {
     *     $router->respond( '/', function() {
     *         // do something interesting
     *     });
     *     $router->respond( '/[i:id]', function() {
     *         // do something different
     *     });
     * });
     *
     * $router->with('/cars', __DIR__ . '/routes/cars.php');
     * </code>
     *
     * @param string $namespace         The namespace under which to collect the routes
     * @param callable|string $routes   The defined routes callable or filename to collect under the namespace
     * @return void
     */
    public function with($namespace, $routes)
    {
        $previous = $this->route_factory->getNamespace();

        $this->route_factory->appendNamespace($namespace);

        if (is_callable($routes)) {
            call_user_func($routes, $this);
            $this->route_factory->setNamespace($previous);

            return;
        }

        require $routes;

        $this->route_factory->setNamespace($previous);
    }

    /**
     * Dispatch the request to the appropriate route(s)
     *
     * Dispatch with optionally injected dependencies
     * This DI allows for easy testing, object mocking, or class extension
     *
     * @param Request $request              The request object to give to each callback
     * @param AbstractResponse $response    The response object to give to each callback
     * @param boolean $send_response        Whether or not to "send" the response after the last route has been matched
     * @param int $capture                  Specify a DISPATCH_* constant to change the output capturing behavior
     * @return void|string
     */
    public function dispatch(
        Request|null $request = null,
        AbstractResponse|null $response = null,
        $send_response = true,
        $capture = self::DISPATCH_NO_CAPTURE,
    ) {
        $this->prepareDispatch($request, $response);

        $req_method = $this->request->method();

        $this->dispatchRoutes($this->request->pathname(), $req_method);

        try {
            if ($this->response->chunked) {
                $this->response->chunk();
            }

            // Output capturing behavior (chunk() never resets the chunked flag)
            if (!$this->response->chunked) {
                if (self::DISPATCH_CAPTURE_AND_RETURN === $capture) {
                    return $this->drainOutputBuffers();
                }

                $capture = $this->captureOutput($capture);
            }

            $this->finishOutput($req_method, $capture);
        } catch (LockedResponseException) { // @mago-expect lint:no-empty-catch-clause
            // Do nothing, since this is an automated behavior
        }

        // Run our after dispatch callbacks
        $this->callAfterDispatchCallbacks();

        if ($send_response && !$this->response->isSent()) {
            $this->response->send();
        }
    }

    /**
     * Sets up the request, response, service, named routes and output buffering for a dispatch
     *
     * @param Request|null $request             The request object, created from the globals if null
     * @param AbstractResponse|null $response   The response object, a new Response if null
     * @return void
     */
    private function prepareDispatch(Request|null $request, AbstractResponse|null $response)
    {
        // Set/Initialize our objects to be sent in each callback
        $this->request = $request ?? Request::createFromGlobals();
        $this->response = $response ?? new Response();

        // Bind our objects to our service
        $this->service->bind($this->request, $this->response);

        // Prepare any named routes
        $this->routes->prepareNamed();

        // Start output buffering
        ob_start();
        $this->output_buffer_level = ob_get_level();
    }

    /**
     * Matches the routes against the request and runs the callbacks of the matching ones
     *
     * @param string $uri           The request URI
     * @param string $req_method    The request method
     * @return void
     */
    private function dispatchRoutes($uri, $req_method)
    {
        // Set up some variables for matching
        $skip_num = 0;
        $matched = $this->routes->cloneEmpty(); // Get a clone of the routes collection, as it may have been injected
        $methods_matched = [];
        $params = [];

        try {
            foreach ($this->routes as $route) {
                // Are we skipping any matches?
                if ($skip_num > 0) {
                    $skip_num--;
                    continue;
                }

                // Grab the properties of the route handler
                $method = $route->getMethod();
                $path = $route->getPath();
                $count_match = $route->getCountMatch();

                // If the method was matched or if it wasn't even passed (in the route callback)
                $possible_match = false !== $this->matchRouteMethod($req_method, $method);

                // ! is used to negate a match
                $negate = ($path[0] ?? null) === '!';

                if ($this->handleDeprecatedHttpErrorRoute($route, $matched, $methods_matched)) {
                    continue;
                }

                $match = $this->matchRoutePath($route, $uri, $params);

                // The literal part of the path didn't match the URI, so skip the route
                if (null === $match) {
                    continue;
                }

                if ($match ^ $negate) {
                    if ($possible_match) {
                        $this->mergeNamedParams($params);

                        // Handle our response callback
                        try {
                            $this->handleRouteCallback($route, $matched, $methods_matched);
                        } catch (DispatchHaltedException $e) {
                            switch ($e->getCode()) {
                                case DispatchHaltedException::SKIP_THIS:
                                    continue 2;
                                case DispatchHaltedException::SKIP_NEXT:
                                    $skip_num = $e->getNumberOfSkips();
                                    break;
                                case DispatchHaltedException::SKIP_REMAINING:
                                    break 2;
                                default:
                                    throw $e;
                            }
                        }

                        if ($count_match && $path !== '*') {
                            $matched->add($route);
                        }
                    }

                    // Don't bother counting this as a method match if the route isn't supposed to match anyway
                    if ($count_match) {
                        $methods_matched = $this->mergeMatchedMethods($methods_matched, $method);
                    }
                }
            }

            $this->requireRouteMatch($matched, $methods_matched, $req_method);
        } catch (HttpExceptionInterface $e) {
            $this->handleHttpErrorKeepingLock($e, $matched, $methods_matched);
        } catch (Throwable $e) {
            $this->error($e);
        }
    }

    /**
     * Calls the HTTP error handlers, restoring the response's lock state afterwards
     *
     * @param HttpExceptionInterface $http_exception    The HTTP exception that occurred
     * @param RouteCollection $matched                  The matched routes
     * @param array $methods_matched                    The matched methods
     * @return void
     */
    private function handleHttpErrorKeepingLock(
        HttpExceptionInterface $http_exception,
        RouteCollection $matched,
        array $methods_matched,
    ) {
        // Grab our original response lock state
        $locked = $this->response->isLocked();

        // Call our http error handlers
        $this->httpError($http_exception, $matched, $methods_matched);

        // Make sure we return our response to its original lock state
        if (!$locked) {
            $this->response->unlock();
        }
    }

    /**
     * Registers a deprecated 404/405 "route" as HTTP error callback, if it is one and is due
     *
     * @param Route $route              The route to check
     * @param RouteCollection $matched  The routes matched so far
     * @param array $methods_matched    The methods matched so far
     * @return boolean                  Whether the route was handled as a deprecated 404/405 "route"
     */
    private function handleDeprecatedHttpErrorRoute(Route $route, RouteCollection $matched, array $methods_matched)
    {
        $path = $route->getPath();

        if ($path !== '404' && $path !== '405' || !$matched->isEmpty()) {
            return false;
        }

        if (($path === '404') !== count($methods_matched) <= 0) {
            return false;
        }

        // Warn user of deprecation
        trigger_error('Use of 404/405 "routes" is deprecated. Use $klein->onHttpError() instead.', E_USER_DEPRECATED);

        // Deprecated (see the notice above), kept for backwards compatibility
        $this->onHttpError($route);

        return true;
    }

    /**
     * Adds a route's method(s) to the possibly matched methods
     *
     * @param array $methods_matched        The methods matched so far
     * @param string|array|null $method     The method(s) of the route
     * @return array
     */
    private function mergeMatchedMethods(array $methods_matched, $method)
    {
        return array_unique(array_filter(array_merge($methods_matched, (array) $method)));
    }

    /**
     * URL decodes the matched params and merges them into the request's named params
     *
     * @param array $params The params matched from the route's path
     * @return void
     */
    private function mergeNamedParams(array $params)
    {
        if ([] === $params) {
            return;
        }

        /**
         * URL Decode the params according to RFC 3986
         * @link http://www.faqs.org/rfcs/rfc3986
         *
         * Decode here AFTER matching as per @chriso's suggestion
         * @link https://github.com/klein/klein.php/issues/117#issuecomment-21093915
         */
        $this->request->paramsNamed()->merge(array_map('rawurldecode', $params));
    }

    /**
     * Throws the 404/405 HTTP exceptions if no route matched
     *
     * @param RouteCollection $matched  The matched routes
     * @param array $methods_matched    The matched methods
     * @param string $req_method        The request method
     * @throws HttpException            If no route matched (404) or none matched the method (405)
     * @return void
     */
    private function requireRouteMatch(RouteCollection $matched, array $methods_matched, $req_method)
    {
        if (!$matched->isEmpty()) {
            return;
        }

        if (count($methods_matched) === 0) {
            throw HttpException::createFromCode(404);
        }

        // Add our methods to our allow header
        $this->response->header('Allow', implode(', ', $methods_matched));

        if (strcasecmp($req_method, 'OPTIONS') !== 0) {
            throw HttpException::createFromCode(405);
        }
    }

    /**
     * Ends the output buffers started by dispatch, passing each one's content to an optional consumer
     *
     * @param callable|null $consumer   Receives the content of each buffer, innermost first
     * @return string|false|null        The content of the last (outermost) buffer, null if there was none
     */
    private function drainOutputBuffers(callable|null $consumer = null)
    {
        $content = null;

        while (ob_get_level() >= $this->output_buffer_level) {
            $content = ob_get_clean();

            if (null !== $consumer) {
                $consumer($content);
            }
        }

        return $content;
    }

    /**
     * Captures the output into the response body according to the capture behavior
     *
     * @param int $capture  The DISPATCH_* capture behavior (other than DISPATCH_CAPTURE_AND_RETURN)
     * @return int          The capture behavior, DISPATCH_NO_CAPTURE if it isn't a handled one
     */
    private function captureOutput($capture)
    {
        switch ($capture) {
            case self::DISPATCH_CAPTURE_AND_REPLACE:
                $this->drainOutputBuffers($this->response->body(...));
                return $capture;
            case self::DISPATCH_CAPTURE_AND_PREPEND:
                $this->drainOutputBuffers($this->response->prepend(...));
                return $capture;
            case self::DISPATCH_CAPTURE_AND_APPEND:
                $this->drainOutputBuffers($this->response->append(...));
                return $capture;
            default:
                // If not a handled capture strategy, default to no capture
                return self::DISPATCH_NO_CAPTURE;
        }
    }

    /**
     * Discards the output for HEAD requests, otherwise sends it if it isn't captured
     *
     * @param string $req_method    The request method
     * @param int $capture          The DISPATCH_* capture behavior
     * @return void
     */
    private function finishOutput($req_method, $capture)
    {
        // Test for HEAD request (like GET)
        if (strcasecmp($req_method, 'HEAD') === 0) {
            // HEAD requests shouldn't return a body
            $this->response->body('');

            $this->cleanOutputBuffers();

            return;
        }

        if (self::DISPATCH_NO_CAPTURE === $capture) {
            $this->flushOutputBuffers();
        }
    }

    /**
     * Discards the output buffers started by dispatch
     *
     * @return void
     */
    private function cleanOutputBuffers()
    {
        while (ob_get_level() >= $this->output_buffer_level) {
            ob_end_clean();
        }
    }

    /**
     * Sends the output buffers started by dispatch
     *
     * @return void
     */
    private function flushOutputBuffers()
    {
        while (ob_get_level() >= $this->output_buffer_level) {
            ob_end_flush();
        }
    }

    /**
     * Checks a route's method(s) against the request method
     *
     * HEAD requests also match routes for GET
     *
     * @param string $req_method            The request method
     * @param string|array|null $method     The method(s) of the route
     * @return boolean|null                 null if the route doesn't specify a method
     */
    private function matchRouteMethod($req_method, $method)
    {
        if (null === $method) {
            return null;
        }

        $is_head_request = strcasecmp($req_method, 'HEAD') === 0;

        foreach ((array) $method as $test) {
            if (strcasecmp($req_method, $test) === 0) {
                return true;
            }

            if ($is_head_request && (strcasecmp($test, 'HEAD') === 0 || strcasecmp($test, 'GET') === 0)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Matches a route's path against the request URI
     *
     * @param Route $route      The route being matched
     * @param string $uri       The request URI
     * @param array $params     Receives the regex matches; left untouched for a wildcard path
     * @throws RoutePathCompilationException    If the route's path fails to compile
     * @return int|boolean|null                 The match result, null if the route should be skipped
     */
    private function matchRoutePath(Route $route, $uri, &$params)
    {
        $path = $route->getPath();
        // Skip a leading "!", which negates the match
        $i = ($path[0] ?? null) === '!' ? 1 : 0;
        // Check for a wildcard (match all)
        if ($path === '*') {
            return true;
        }

        // @ is used to specify custom regex
        if (($path[$i] ?? null) === '@') {
            return preg_match('`' . substr($path, $i + 1) . '`', $uri, $params);
        }

        // Compiling and matching regular expressions is relatively
        // expensive, so try and match by a substring first

        $expression = null;
        $regex = false;
        $j = 0;
        $n = $path[$i] ?? null;

        // Find the longest non-regex substring and match it against the URI
        while (true) {
            if (($path[$i] ?? null) === null) {
                break;
            }

            if (false === $regex) {
                $c = $n;
                $regex = $c === '[' || $c === '(' || $c === '.';
                if (false === $regex && ($path[$i + 1] ?? null) !== null) {
                    $n = $path[$i + 1];
                    $regex = $n === '?' || $n === '+' || $n === '*' || $n === '{';
                }
                if (false === $regex && $c !== '/' && $c !== ($uri[$j] ?? null)) {
                    return null;
                }
                $j++;
            }
            $expression .= $path[$i++];
        }

        return preg_match($this->getRouteRegex($route, $expression), $uri, $params);
    }

    /**
     * Gets the compiled regular expression of a route's path, cached in APCu if available
     *
     * @param Route $route          The route the expression belongs to
     * @param string $expression    The route's path expression, without a leading "!"
     * @throws RoutePathCompilationException    If the route's path fails to compile
     * @return string
     */
    private function getRouteRegex(Route $route, $expression)
    {
        // Whether compiled route regexes can be cached in APCu
        $apc = function_exists('apcu_fetch');

        try {
            // Check if there's a cached regex string
            $regex = $apc ? apcu_fetch("route:{$expression}") : false;

            if (false === $regex) {
                $regex = $this->compileRoute($expression);

                if ($apc) {
                    apcu_store("route:{$expression}", $regex);
                }
            }
        } catch (RegularExpressionCompilationException $e) {
            throw RoutePathCompilationException::createFromRoute($route, $e);
        }

        return $regex;
    }

    /**
     * Compiles a route string to a regular expression
     *
     * @param string $route     The route string to compile
     * @return string
     */
    protected function compileRoute($route)
    {
        // First escape all of the non-named param (non [block]s) for regex-chars
        $route = preg_replace_callback(
            static::ROUTE_ESCAPE_REGEX,
            static fn($match) => preg_quote($match[0], delimiter: '`'),
            $route,
        );

        // Get a local reference of the match types to pass into our closure
        $match_types = $this->match_types;

        // Now let's actually compile the path
        $route = preg_replace_callback(
            static::ROUTE_COMPILE_REGEX,
            static function ($match) use ($match_types) {
                list(, $pre, $type, $param, $optional) = $match;

                if (($match_types[$type] ?? null) !== null) {
                    $type = $match_types[$type];
                }

                // Older versions of PCRE require the 'P' in (?P<named>)
                return (
                    '(?:'
                    . ($pre !== '' ? $pre : null)
                    . '('
                    . ($param !== '' ? "?P<{$param}>" : null)
                    . $type
                    . '))'
                    . ($optional !== '' ? '?' : null)
                );
            },
            $route,
        );

        $regex = "`^{$route}$`";

        // Check if our regular expression is valid
        $this->validateRegularExpression($regex);

        return $regex;
    }

    /**
     * Validate a regular expression
     *
     * This simply checks if the regular expression is able to be compiled
     * and converts any warnings or notices in the compilation to an exception
     *
     * @param string $regex                          The regular expression to validate
     * @throws RegularExpressionCompilationException If the expression can't be compiled
     * @return boolean
     */
    private function validateRegularExpression($regex)
    {
        $error_string = null;

        // Set an error handler temporarily
        set_error_handler(static function ($errno, $errstr) use (&$error_string) {
            $error_string = $errstr;
        }, E_NOTICE | E_WARNING);

        if (false === preg_match($regex, subject: '') || ($error_string ?? '') !== '') {
            // Remove our temporary error handler
            restore_error_handler();

            throw new RegularExpressionCompilationException($error_string, preg_last_error());
        }

        // Remove our temporary error handler
        restore_error_handler();

        return true;
    }

    /**
     * Get the path for a given route
     *
     * This looks up the route by its passed name and returns
     * the path/url for that route, with its URL params as
     * placeholders unless you pass a valid key-value pair array
     * of the placeholder params and their values
     *
     * If a pathname is a complex/custom regular expression, this
     * method will simply return the regular expression used to
     * match the request pathname, unless an optional boolean is
     * passed "flatten_regex" which will flatten the regular
     * expression into a simple path string
     *
     * This method, and its style of reverse-compilation, was originally
     * inspired by a similar effort by Gilles Bouthenot (@gbouthenot)
     *
     * @link https://github.com/gbouthenot
     * @param string $route_name        The name of the route
     * @param array $params             The array of placeholder fillers
     * @param boolean $flatten_regex    Optionally flatten custom regular expressions to "/"
     * @throws OutOfBoundsException     If the route requested doesn't exist
     * @return string
     */
    public function getPathFor($route_name, array|null $params = null, $flatten_regex = true)
    {
        // First, grab the route
        $route = $this->routes->get($route_name);

        // Make sure we are getting a valid route
        if (null === $route) {
            throw new OutOfBoundsException('No such route with name: ' . $route_name);
        }

        $path = $route->getPath();

        // Use our compilation regex to reverse the path's compilation from its definition
        $reversed_path = preg_replace_callback(
            static::ROUTE_COMPILE_REGEX,
            static function ($match) use ($params) {
                list($block, $pre, , $param, $optional) = $match;

                if (($params[$param] ?? null) !== null) {
                    return $pre . $params[$param];
                }

                if ($optional) {
                    return '';
                }

                return $block;
            },
            $path,
        );

        // If the path and reversed_path are the same, the regex must have not matched/replaced
        if ($path === $reversed_path && $flatten_regex && str_starts_with($path, '@')) {
            // If the path is a custom regular expression and we're "flattening", just return a slash
            return '/';
        }

        return $reversed_path;
    }

    /**
     * Handle a route's callback
     *
     * This handles common exceptions and their output
     * to keep the "dispatch()" method DRY
     *
     * @param Route $route
     * @param RouteCollection $matched
     * @param array $methods_matched
     * @return void
     */
    protected function handleRouteCallback(Route $route, RouteCollection $matched, array $methods_matched)
    {
        // Handle the callback
        $returned = call_user_func(
            $route->getCallback(), // Instead of relying on the slower "invoke" magic
            $this->request,
            $this->response,
            $this->service,
            $this->app,
            $this, // Pass the Klein instance
            $matched,
            $methods_matched,
        );

        if ($returned instanceof AbstractResponse) {
            $this->response = $returned;

            return;
        }

        // Otherwise, attempt to append the returned data
        try {
            $this->response->append($returned);
        } catch (LockedResponseException) { // @mago-expect lint:no-empty-catch-clause
            // Do nothing, since this is an automated behavior
        }
    }

    /**
     * Adds an error callback to the stack of error handlers
     *
     * @param callable $callback            The callable function to execute in the error handling chain
     * @return void
     */
    public function onError($callback)
    {
        $this->error_callbacks->push($callback);
    }

    /**
     * Routes an exception through the error callbacks
     *
     * @param Throwable $err The exception that occurred
     * @throws UnhandledException      If the error/exception isn't handled by an error callback
     * @return void
     */
    protected function error(Throwable $err)
    {
        try {
            if ($this->error_callbacks->isEmpty()) {
                $this->response->code(500);

                $this->cleanOutputBuffers();

                throw new UnhandledException($err->getMessage(), (int) $err->getCode(), $err);
            }

            if ($this->callErrorCallbacks($err)) {
                return;
            }
        } catch (Throwable $e) {
            // Make sure to clean the output buffer before bailing
            $this->cleanOutputBuffers();

            throw $e;
        }

        // Lock our response, since we probably don't want
        // anything else messing with our error code/body
        $this->response->lock();
    }

    /**
     * Runs the error callbacks until the first callable one
     *
     * Non-callable callbacks are treated as URLs to redirect to, with the error flashed
     *
     * @param Throwable $err    The exception that occurred
     * @return boolean          Whether a callable callback handled the error
     */
    private function callErrorCallbacks(Throwable $err)
    {
        $type = get_class($err);
        $msg = $err->getMessage();

        foreach ($this->error_callbacks as $callback) {
            if (is_callable($callback)) {
                call_user_func($callback, $this, $msg, $type, $err);

                return true;
            }

            if (null !== $this->service && null !== $this->response) {
                // Flash only the (escaped) message: it may contain user input, and the trace leaks internals
                $this->service->flash('%s', [$msg]);
                $this->response->redirect($callback);
            }
        }

        return false;
    }

    /**
     * Adds an HTTP error callback to the stack of HTTP error handlers
     *
     * @param callable $callback            The callable function to execute in the error handling chain
     * @return void
     */
    public function onHttpError($callback)
    {
        $this->http_error_callbacks->push($callback);
    }

    /**
     * Handles an HTTP error exception through our HTTP error callbacks
     *
     * @param HttpExceptionInterface $http_exception    The exception that occurred
     * @param RouteCollection $matched                  The collection of routes that were matched in dispatch
     * @param array $methods_matched                    The HTTP methods that were matched in dispatch
     * @return void
     */
    protected function httpError(HttpExceptionInterface $http_exception, RouteCollection $matched, $methods_matched)
    {
        if (!$this->response->isLocked()) {
            $this->response->code($http_exception->getCode());
        }

        if (!$this->http_error_callbacks->isEmpty()) {
            foreach ($this->http_error_callbacks as $callback) {
                if ($callback instanceof Route) {
                    $this->handleRouteCallback($callback, $matched, $methods_matched);

                    continue;
                }

                if (is_callable($callback)) {
                    call_user_func(
                        $callback,
                        $http_exception->getCode(),
                        $this,
                        $matched,
                        $methods_matched,
                        $http_exception,
                    );
                }
            }
        }

        // Lock our response, since we probably don't want
        // anything else messing with our error code/body
        $this->response->lock();
    }

    /**
     * Adds a callback to the stack of handlers to run after the dispatch
     * loop has handled all of the route callbacks and before the response
     * is sent
     *
     * @param callable $callback            The callable function to execute in the after route chain
     * @return void
     */
    public function afterDispatch($callback)
    {
        $this->after_filter_callbacks->enqueue($callback);
    }

    /**
     * Runs through and executes the after dispatch callbacks
     *
     * @return void
     */
    protected function callAfterDispatchCallbacks()
    {
        try {
            foreach ($this->after_filter_callbacks as $callback) {
                if (!is_callable($callback)) {
                    continue;
                }

                call_user_func($callback, $this);
            }
        } catch (Throwable $e) {
            $this->error($e);
        }
    }

    /**
     * Method aliases
     */

    /**
     * Quick alias to skip the current callback/route method from executing
     *
     * @throws DispatchHaltedException To halt/skip the current dispatch loop
     */
    public function skipThis(): never
    {
        throw new DispatchHaltedException('', DispatchHaltedException::SKIP_THIS);
    }

    /**
     * Quick alias to skip the next callback/route method from executing
     *
     * @param int $num The number of next matches to skip
     * @throws DispatchHaltedException To halt/skip the current dispatch loop
     */
    public function skipNext($num = 1): never
    {
        $skip = new DispatchHaltedException('', DispatchHaltedException::SKIP_NEXT);
        $skip->setNumberOfSkips($num);

        throw $skip;
    }

    /**
     * Quick alias to stop the remaining callbacks/route methods from executing
     *
     * @throws DispatchHaltedException To halt/skip the current dispatch loop
     */
    public function skipRemaining(): never
    {
        throw new DispatchHaltedException('', DispatchHaltedException::SKIP_REMAINING);
    }

    /**
     * Alias to set a response code, lock the response, and halt the route matching/dispatching
     *
     * @param int|null $code     Optional HTTP status code to send
     * @throws DispatchHaltedException To halt/skip the current dispatch loop
     */
    public function abort(int|null $code = null): never
    {
        if (null !== $code) {
            throw HttpException::createFromCode($code);
        }

        throw new DispatchHaltedException();
    }

    /**
     * OPTIONS alias for "respond()"
     *
     * @see Klein::respond()
     * @param string|callable $path
     * @param callable $callback
     */
    public function options($path = '*', $callback = null): Route
    {
        // Options the arguments in a very loose format
        extract($this->parseLooseArgumentOrder(func_get_args()), EXTR_OVERWRITE);

        return $this->respond('OPTIONS', $path, $callback);
    }

    /**
     * HEAD alias for "respond()"
     *
     * @see Klein::respond()
     * @param string|callable $path
     * @param callable $callback
     */
    public function head($path = '*', $callback = null): Route
    {
        // Get the arguments in a very loose format
        extract($this->parseLooseArgumentOrder(func_get_args()), EXTR_OVERWRITE);

        return $this->respond('HEAD', $path, $callback);
    }

    /**
     * GET alias for "respond()"
     *
     * @see Klein::respond()
     * @param string|callable $path
     * @param callable $callback
     */
    public function get($path = '*', $callback = null): Route
    {
        // Get the arguments in a very loose format
        extract($this->parseLooseArgumentOrder(func_get_args()), EXTR_OVERWRITE);

        return $this->respond('GET', $path, $callback);
    }

    /**
     * POST alias for "respond()"
     *
     * @see Klein::respond()
     * @param string|callable $path
     * @param callable $callback
     */
    public function post($path = '*', $callback = null): Route
    {
        // Get the arguments in a very loose format
        extract($this->parseLooseArgumentOrder(func_get_args()), EXTR_OVERWRITE);

        return $this->respond('POST', $path, $callback);
    }

    /**
     * PUT alias for "respond()"
     *
     * @see Klein::respond()
     * @param string|callable $path
     * @param callable $callback
     */
    public function put($path = '*', $callback = null): Route
    {
        // Get the arguments in a very loose format
        extract($this->parseLooseArgumentOrder(func_get_args()), EXTR_OVERWRITE);

        return $this->respond('PUT', $path, $callback);
    }

    /**
     * DELETE alias for "respond()"
     *
     * @see Klein::respond()
     * @param string|callable $path
     * @param callable $callback
     */
    public function delete($path = '*', $callback = null): Route
    {
        // Get the arguments in a very loose format
        extract($this->parseLooseArgumentOrder(func_get_args()), EXTR_OVERWRITE);

        return $this->respond('DELETE', $path, $callback);
    }

    /**
     * PATCH alias for "respond()"
     *
     * PATCH was added to HTTP/1.1 in RFC5789
     *
     * @link http://tools.ietf.org/html/rfc5789
     * @see Klein::respond()
     * @param string|callable $path
     * @param callable $callback
     */
    public function patch($path = '*', $callback = null): Route
    {
        // Get the arguments in a very loose format
        extract($this->parseLooseArgumentOrder(func_get_args()), EXTR_OVERWRITE);

        return $this->respond('PATCH', $path, $callback);
    }
}
