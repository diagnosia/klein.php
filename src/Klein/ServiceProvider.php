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

/**
 * ServiceProvider
 *
 * Service provider class for handling logic extending between
 * a request's data and a response's behavior
 */
class ServiceProvider
{
    /**
     * The Request instance containing HTTP request data and behaviors
     */
    protected Request|null $request = null;

    /**
     * The Response instance containing HTTP response data and behaviors
     */
    protected AbstractResponse|null $response = null;

    /**
     * The id of the current PHP session
     */
    protected string|false|null $session_id = null;

    /**
     * The view layout
     */
    protected string|null $layout = null;

    /**
     * The view to render
     */
    protected string|null $view = null;

    /**
     * Shared data collection
     */
    protected DataCollection $shared_data;

    /**
     * Constructor
     *
     * @param Request|null $request         Object containing all HTTP request data and behaviors
     * @param AbstractResponse|null $response Object containing all HTTP response data and behaviors
     */
    public function __construct(Request|null $request = null, AbstractResponse|null $response = null)
    {
        // Bind our objects
        $this->bind($request, $response);

        // Instantiate our shared data collection
        $this->shared_data = new DataCollection();
    }

    /**
     * Bind object instances to this service
     *
     * @param Request|null $request            Object containing all HTTP request data and behaviors
     * @param AbstractResponse|null $response  Object containing all HTTP response data and behaviors
     */
    public function bind(Request|null $request = null, AbstractResponse|null $response = null): static
    {
        // Keep references
        $this->request = $request ?? $this->request;
        $this->response = $response ?? $this->response;

        return $this;
    }

    /**
     * Checks if both a request and a response are bound
     */
    public function isBound(): bool
    {
        return null !== $this->request && null !== $this->response;
    }

    /**
     * Misspelled alias of isBound()
     */
    #[\Deprecated(message: 'use isBound() instead')]
    public function isBond(): bool
    {
        return $this->isBound();
    }

    /**
     * Returns the shared data collection object
     */
    public function sharedData(): DataCollection
    {
        return $this->shared_data;
    }

    /**
     * Get the current session's ID
     *
     * This will start a session if none is active yet
     *
     * @return string|false     The session id (also of a session that was already active), false if it couldn't be
     *  started
     */
    public function startSession(): string|false
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            // Attempt to start a session
            session_start();
        }

        $session_id = session_id();
        $this->session_id = false === $session_id || '' === $session_id ? false : $session_id;

        return $this->session_id;
    }

    /**
     * Stores a flash message of $type
     *
     * The message is a trusted markdown template and is NOT escaped. Pass untrusted
     * text (user input, exception messages) as a param instead, e.g. flash('%s', [$text])
     *
     * @param string $msg               The message to flash
     * @param string|array $type        The flash message type, or the params (with the "info" type)
     * @param array|null $params        Optional params to be parsed by markdown
     */
    public function flash(string $msg, string|array $type = 'info', array|null $params = null): void
    {
        $this->startSession();
        if (is_array($type)) {
            $params = $type;
            $type = 'info';
        }
        $_SESSION['__flashes'] ??= [];
        $_SESSION['__flashes'][$type] ??= [];
        // Without params, the message isn't used as a format string (so a "%" in it stays as is)
        $_SESSION['__flashes'][$type][] = null === $params ? $this->markdown($msg) : $this->markdown($msg, $params);
    }

    /**
     * Returns and clears all flashes of optional $type
     *
     * @param string|null $type The name of the flash message type, all types if null
     */
    public function flashes(string|null $type = null): array
    {
        $this->startSession();

        if (($_SESSION['__flashes'] ?? null) === null) {
            return [];
        }

        if (null === $type) {
            $flashes = $_SESSION['__flashes'];
            unset($_SESSION['__flashes']);

            return $flashes;
        }

        if (($_SESSION['__flashes'][$type] ?? null) === null) {
            return [];
        }

        $flashes = $_SESSION['__flashes'][$type];
        unset($_SESSION['__flashes'][$type]);

        return $flashes;
    }

    /**
     * Render a text string as markdown
     *
     * Supports basic markdown syntax
     *
     * Also, this method takes in EITHER an array of optional arguments (as the second parameter)
     * ... OR this method will simply take a variable number of arguments (after the initial str arg)
     *
     * With arguments, the string is a format string for vsprintf() and the arguments are HTML escaped.
     * Without arguments, the string is used as is (a "%" doesn't need to be escaped as "%%").
     * The string itself is NOT escaped, so it must not contain untrusted text.
     *
     * @param string $str   The text string to parse
     * @param mixed $args   Optional arguments to be parsed by markdown: an array, or the first of several arguments
     */
    public static function markdown(string $str, mixed $args = null): string
    {
        // Create our markdown parse/conversion regex's
        $md = [
            '/\[([^\]]++)\]\(([^\)]++)\)/' => '<a href="$2">$1</a>',
            '/\*\*([^\*]++)\*\*/' => '<strong>$1</strong>',
            '/\*([^\*]++)\*/' => '<em>$1</em>',
        ];

        // Let's make our arguments more "magical"
        $args = func_get_args(); // Grab all of our passed args
        $str = array_shift($args); // Remove the initial arg from the array (and set the $str to it)
        if (is_array($args[0] ?? null)) {
            /**
             * If our "second" argument (now the first array item is an array)
             * just use the array as the arguments and forget the rest
             */
            $args = $args[0];
        }

        // Encode our args so we can insert them into an HTML string
        foreach ($args as &$arg) {
            $arg = htmlentities((string) ($arg ?? ''), ENT_QUOTES, encoding: 'UTF-8');
        }

        // Actually do our markdown conversion
        $html = (string) preg_replace(array_keys($md), $md, $str);

        // Only format when there is something to insert, so a "%" in a plain message can't throw a ValueError
        return [] === $args ? $html : vsprintf($html, $args);
    }

    /**
     * Escapes a string for UTF-8 HTML displaying
     *
     * This is a quick macro for escaping strings designed
     * to be shown in a UTF-8 HTML environment. Its options
     * are otherwise limited by design
     *
     * @param string|int|float|bool|null $str   The string to escape, other scalars (and null) are cast
     * @param int $flags    A bitmask of `htmlentities()` compatible flags
     */
    public static function escape(string|int|float|bool|null $str, int $flags = ENT_QUOTES): string
    {
        return htmlentities((string) $str, $flags, encoding: 'UTF-8');
    }

    /**
     * Redirects the request to the current URL
     */
    public function refresh(): static
    {
        $this->response->redirect($this->request->uri());

        return $this;
    }

    /**
     * Redirects the request back to the referrer
     *
     * Only a referrer on the request's own host is followed, otherwise (or without one) this refreshes,
     * since the Referer header can point anywhere (an open redirect)
     */
    public function back(): static
    {
        $referer = $this->request->server()->get('HTTP_REFERER');

        if (is_string($referer) && $this->isOwnHostUrl($referer)) {
            $this->response->redirect($referer);

            return $this;
        }

        $this->refresh();

        return $this;
    }

    /**
     * Checks if a URL is an absolute HTTP(S) URL on the request's host (and port)
     *
     * @param string $url   The URL to check
     */
    protected function isOwnHostUrl(string $url): bool
    {
        $parts = parse_url($url);
        $request_host = $this->request->server()->get('HTTP_HOST');

        if (false === $parts || !is_string($request_host)) {
            return false;
        }

        $scheme = strtolower($parts['scheme'] ?? '');

        if (($parts['host'] ?? null) === null || $scheme !== 'http' && $scheme !== 'https') {
            return false;
        }

        $host = $parts['host'] . (($parts['port'] ?? null) !== null ? ':' . $parts['port'] : '');

        return strcasecmp($host, $request_host) === 0;
    }

    /**
     * Get (or set) the view's layout
     *
     * Simply calling this method without any arguments returns the current layout.
     * Calling with an argument, however, sets the layout to what was provided by the argument.
     *
     * @param string|null $layout   The layout of the view
     * @return string|static|null   The layout (null if none is set) when getting
     */
    public function layout(string|null $layout = null): string|static|null
    {
        if (null !== $layout) {
            $this->layout = $layout;

            return $this;
        }

        return $this->layout;
    }

    /**
     * Renders the current view
     */
    public function yieldView(): void
    {
        require $this->view;
    }

    /**
     * Renders a view + optional layout
     *
     * @param string $view  The view to render
     * @param array $data   The data to render in the view
     */
    public function render(string $view, array $data = []): void
    {
        $original_view = $this->view;

        if ([] !== $data) {
            $this->shared_data->merge($data);
        }

        $this->view = $view;

        // The view may set a layout while it renders; only the one set before counts
        $layout = $this->layout;

        if (null === $layout) {
            $this->yieldView();
        }

        if (null !== $layout) {
            require $layout;
        }

        if (false !== $this->response->chunked) {
            $this->response->chunk();
        }

        // restore state for parent render()
        $this->view = $original_view;
    }

    /**
     * Renders a view without a layout
     *
     * @param string $view  The view to render
     * @param array $data   The data to render in the view
     */
    public function partial(string $view, array $data = []): void
    {
        $layout = $this->layout;
        $this->layout = null;
        $this->render($view, $data);
        $this->layout = $layout;
    }

    /**
     * Add a custom validator for our validation method
     *
     * @param string $method        The name of the validator method
     * @param callable $callback    The callback to perform on validation
     */
    public function addValidator(string $method, callable $callback): void
    {
        Validator::addValidator($method, $callback);
    }

    /**
     * Start a validator chain for the specified string
     *
     * @param mixed $string     The string to validate, other scalars are cast and anything else fails
     * @param string|bool|null $err The custom exception message to throw or `false` to return the result as boolean
     */
    public function validate(mixed $string, null|string|bool $err = null): Validator
    {
        return new Validator($string, $err);
    }

    /**
     * Start a validator chain for the specified parameter
     *
     * @param string $param     The name of the parameter to validate
     * @param string|bool|null $err The custom exception message to throw or `false` to return the result as boolean
     */
    public function validateParam(string $param, null|string|bool $err = null): Validator
    {
        return $this->validate($this->request->param($param), $err);
    }

    /**
     * Magic "__isset" method
     *
     * Allows the ability to arbitrarily check the existence of shared data
     * from this instance while treating it as an instance property
     *
     * @param string $key     The name of the shared data
     */
    public function __isset(string $key): bool
    {
        return $this->shared_data->exists($key);
    }

    /**
     * Magic "__get" method
     *
     * Allows the ability to arbitrarily request shared data from this instance
     * while treating it as an instance property
     *
     * @param string $key     The name of the shared data
     */
    public function __get(string $key): mixed
    {
        return $this->shared_data->get($key);
    }

    /**
     * Magic "__set" method
     *
     * Allows the ability to arbitrarily set shared data from this instance
     * while treating it as an instance property
     *
     * @param string $key     The name of the shared data
     * @param mixed $value      The value of the shared data
     */
    public function __set(string $key, mixed $value): void
    {
        $this->shared_data->set($key, $value);
    }

    /**
     * Magic "__unset" method
     *
     * Allows the ability to arbitrarily remove shared data from this instance
     * while treating it as an instance property
     *
     * @param string $key     The name of the shared data
     */
    public function __unset(string $key): void
    {
        $this->shared_data->remove($key);
    }
}
