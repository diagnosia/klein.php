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

use Klein\DataCollection\HeaderDataCollection;
use Klein\DataCollection\ResponseCookieDataCollection;
use Klein\Exceptions\LockedResponseException;
use Klein\Exceptions\ResponseAlreadySentException;
use Stringable;

/**
 * AbstractResponse
 */
abstract class AbstractResponse
{
    /**
     * The default response HTTP status code
     */
    protected static int $default_status_code = 200;

    /**
     * The HTTP version of the response
     */
    protected string $protocol_version = '1.1';

    /**
     * The response body
     */
    protected string $body = '';

    /**
     * HTTP response status
     */
    protected HttpStatus $status;

    /**
     * HTTP response headers
     */
    protected HeaderDataCollection $headers;

    /**
     * HTTP response cookies
     */
    protected ResponseCookieDataCollection $cookies;

    /**
     * Whether or not the response is "locked" from
     * any further modification
     */
    protected bool $locked = false;

    /**
     * Whether or not the response has been sent
     */
    protected bool $sent = false;

    /**
     * Whether the response has been chunked or not
     */
    public bool $chunked = false;

    /**
     * Constructor
     *
     * Create a new AbstractResponse object with a dependency injected Headers instance
     *
     * @param string|Stringable|int|float|bool|null $body The response body's content, cast to string
     * @param int|null $status_code     The status code, the default one if null (or 0)
     * @param array $headers            The response header "hash"
     */
    public function __construct(
        string|Stringable|int|float|bool|null $body = '',
        int|null $status_code = null,
        array $headers = [],
    ) {
        // Any empty status code (null, 0, '') falls back to the default
        if (!$status_code) {
            $status_code = static::$default_status_code;
        }

        // Set our body and code using our internal methods
        $this->body($body);
        $this->code($status_code);

        $this->headers = new HeaderDataCollection($headers);
        $this->cookies = new ResponseCookieDataCollection();
    }

    /**
     * Get (or set) the HTTP protocol version
     *
     * Simply calling this method without any arguments returns the current protocol version.
     * Calling with an integer argument, however, attempts to set the protocol version to what
     * was provided by the argument.
     */
    public function protocolVersion(string|null $protocol_version = null): string|static
    {
        if (null !== $protocol_version) {
            // Require that the response be unlocked before changing it
            $this->requireUnlocked();

            $this->protocol_version = $protocol_version;

            return $this;
        }

        return $this->protocol_version;
    }

    /**
     * Get (or set) the response's body content
     *
     * Simply calling this method without any arguments returns the current response body.
     * Calling with an argument, however, sets the response body to what was provided by the argument.
     *
     * @param string|Stringable|int|float|bool|null $body  The body content, cast to string
     */
    public function body(string|Stringable|int|float|bool|null $body = null): string|static
    {
        if (null !== $body) {
            // Require that the response be unlocked before changing it
            $this->requireUnlocked();

            $this->body = (string) $body;

            return $this;
        }

        return $this->body;
    }

    /**
     * Returns the status object
     */
    public function status(): HttpStatus
    {
        return $this->status;
    }

    /**
     * Returns the headers collection
     */
    public function headers(): HeaderDataCollection
    {
        return $this->headers;
    }

    /**
     * Returns the cookies collection
     */
    public function cookies(): ResponseCookieDataCollection
    {
        return $this->cookies;
    }

    /**
     * Get (or set) the HTTP response code
     *
     * Simply calling this method without any arguments returns the current response code.
     * Calling with an integer argument, however, attempts to set the response code to what
     * was provided by the argument.
     *
     * @param int|null $code    The HTTP status code to send
     */
    public function code(int|null $code = null): int|static
    {
        if (null !== $code) {
            // Require that the response be unlocked before changing it
            $this->requireUnlocked();

            $this->status = new HttpStatus($code);

            return $this;
        }

        return $this->status->getCode();
    }

    /**
     * Prepend a string to the response's content body
     *
     * @param string|Stringable|int|float|bool|null $content   The content to prepend, cast to string
     */
    public function prepend(string|Stringable|int|float|bool|null $content): static
    {
        // Require that the response be unlocked before changing it
        $this->requireUnlocked();

        $this->body = (string) $content . $this->body;

        return $this;
    }

    /**
     * Append a string to the response's content body
     *
     * @param string|Stringable|int|float|bool|null $content   The content to append, cast to string
     */
    public function append(string|Stringable|int|float|bool|null $content): static
    {
        // Require that the response be unlocked before changing it
        $this->requireUnlocked();

        $this->body .= (string) $content;

        return $this;
    }

    /**
     * Check if the response is locked
     */
    public function isLocked(): bool
    {
        return $this->locked;
    }

    /**
     * Require that the response is unlocked
     *
     * Throws an exception if the response is locked,
     * preventing any methods from mutating the response
     * when its locked
     *
     * @throws LockedResponseException  If the response is locked
     */
    public function requireUnlocked(): static
    {
        if ($this->isLocked()) {
            throw new LockedResponseException('Response is locked');
        }

        return $this;
    }

    /**
     * Lock the response from further modification
     */
    public function lock(): static
    {
        $this->locked = true;

        return $this;
    }

    /**
     * Unlock the response from further modification
     */
    public function unlock(): static
    {
        $this->locked = false;

        return $this;
    }

    /**
     * Generates an HTTP compatible status header line string
     *
     * Creates the string based off of the response's properties
     */
    protected function httpStatusLine(): string
    {
        return sprintf('HTTP/%s %s', $this->protocol_version, $this->status);
    }

    /**
     * Send our HTTP headers
     *
     * @param boolean $cookies_also Whether or not to also send the cookies after sending the normal headers
     * @param boolean $override     Whether or not to override the check if headers have already been sent
     */
    public function sendHeaders(bool $cookies_also = true, bool $override = false): static
    {
        if (headers_sent() && !$override) {
            return $this;
        }

        // Send our HTTP status line
        header($this->httpStatusLine());

        // Iterate through our Headers data collection and send each header
        foreach ($this->headers as $key => $value) {
            header($key . ': ' . $value, replace: false);
        }

        if ($cookies_also) {
            $this->sendCookies($override);
        }

        return $this;
    }

    /**
     * Send our HTTP response cookies
     *
     * @param boolean $override     Whether or not to override the check if headers have already been sent
     */
    public function sendCookies(bool $override = false): static
    {
        if (headers_sent() && !$override) {
            return $this;
        }

        // Iterate through our Cookies data collection and set each cookie natively
        foreach ($this->cookies as $cookie) {
            $options = [
                'expires' => $cookie->getExpire() ?? 0,
                'path' => $cookie->getPath() ?? '',
                'domain' => $cookie->getDomain() ?? '',
                'secure' => $cookie->getSecure(),
                'httponly' => $cookie->getHttpOnly(),
            ];

            if (null !== $cookie->getSameSite()) {
                $options['samesite'] = $cookie->getSameSite();
            }

            // Use the built-in PHP "setcookie" function (a null value is sent as empty)
            setcookie($cookie->getName(), $cookie->getValue() ?? '', $options);
        }

        return $this;
    }

    /**
     * Send our body's contents
     */
    public function sendBody(): static
    {
        echo (string) $this->body;

        return $this;
    }

    /**
     * Send the response and lock it
     *
     * A chunked response is ended here: what is left of the body is sent as a last chunk,
     * followed by the empty chunk that terminates the response
     *
     * @param boolean $override             Whether or not to override the check if the response has already been sent
     * @throws ResponseAlreadySentException If the response has already been sent
     */
    public function send(bool $override = false): static
    {
        if ($this->sent && !$override) {
            throw new ResponseAlreadySentException('Response has already been sent');
        }

        // Send our response data
        $this->sendHeaders();

        $this->sendBodyOrLastChunks();

        // Lock the response from further modification
        $this->lock();

        // Mark as sent
        $this->sent = true;

        // If there running FPM, tell the process manager to finish the server request/response handling
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }

        return $this;
    }

    /**
     * Check if the response has been sent
     */
    public function isSent(): bool
    {
        return $this->sent;
    }

    /**
     * Enable response chunking
     *
     * @link https://github.com/klein/klein.php/wiki/Response-Chunking
     * @link http://bit.ly/hg3gHb
     * @throws LockedResponseException  If the response is locked (when it isn't chunked yet or has a body left)
     * @throws ResponseAlreadySentException If the response has already been sent (and so ended)
     */
    public function chunk(): static
    {
        if ($this->sent) {
            throw new ResponseAlreadySentException('Response has already been sent');
        }

        if (false === $this->chunked) {
            // Check before marking it as chunked, as the header can't be set on a locked response
            $this->requireUnlocked();

            $this->chunked = true;
            $this->header('Transfer-encoding', 'chunked');
            flush();
        }

        if ('' !== $this->body) {
            // Check before writing, so a locked response doesn't end up with a half written chunk
            $this->requireUnlocked();

            $this->writeChunk($this->body);
            $this->body = '';
        }

        return $this;
    }

    /**
     * Sends the body, or ends a chunked response with the rest of the body and the terminating chunk
     */
    private function sendBodyOrLastChunks(): void
    {
        if (!$this->chunked) {
            $this->sendBody();

            return;
        }

        $this->writeChunk($this->body);
        $this->writeLastChunk();
    }

    /**
     * Writes data as one chunk of a chunked response: its length in hex, then the data
     *
     * Empty data is skipped, as an empty chunk would terminate the response
     */
    protected function writeChunk(string $data): void
    {
        if ('' === $data) {
            return;
        }

        printf("%x\r\n", strlen($data));
        echo $data, "\r\n";
        flush();
    }

    /**
     * Writes the empty chunk that terminates a chunked response
     */
    protected function writeLastChunk(): void
    {
        echo "0\r\n\r\n";
        flush();
    }

    /**
     * Sets a response header
     *
     * @param string $key       The name of the HTTP response header
     * @param string|Stringable|int|float|bool|null $value The value to set the header with
     * @throws LockedResponseException  If the response is locked
     */
    public function header(string $key, string|Stringable|int|float|bool|null $value): static
    {
        // Require that the response be unlocked before changing it
        $this->requireUnlocked();

        $this->headers->set($key, $value);

        return $this;
    }

    /**
     * Sets a response cookie
     *
     * @param string|int $key       The name of the cookie
     * @param string|int|float|bool|null $value The value to set the cookie with, scalars are cast to string
     * @param int|string|null $expiry   The time that the cookie should expire, in 30 days if null
     * @param string|null $path     The path of which to restrict the cookie
     * @param string|null $domain   The domain of which to restrict the cookie
     * @param boolean $secure       Flag of whether the cookie should only be sent over a HTTPS connection
     * @param boolean $httponly     Flag of whether the cookie should only be accessible over the HTTP protocol
     * @param string|null $samesite The SameSite attribute ("Strict", "Lax" or "None"; "None" requires $secure)
     * @throws LockedResponseException  If the response is locked
     */
    public function cookie(
        string|int $key,
        string|int|float|bool|null $value = '',
        int|string|null $expiry = null,
        string|null $path = '/',
        string|null $domain = null,
        bool $secure = false,
        bool $httponly = false,
        string|null $samesite = null,
    ): static {
        // Require that the response be unlocked before changing it
        $this->requireUnlocked();

        if (null === $expiry) {
            $expiry = time() + (3600 * 24 * 30);
        }

        $this->cookies->set(
            $key,
            new ResponseCookie($key, $value, $expiry, $path, $domain, $secure, $httponly, $samesite),
        );

        return $this;
    }

    /**
     * Tell the browser not to cache the response
     *
     * @throws LockedResponseException  If the response is locked
     */
    public function noCache(): static
    {
        $this->header('Pragma', 'no-cache');
        $this->header('Cache-Control', 'no-store, no-cache');

        return $this;
    }

    /**
     * Redirects the request to another URL
     *
     * @param string $url   The URL to redirect to
     * @param int $code     The HTTP status code to use for redirection
     */
    public function redirect(string $url, int $code = 302): static
    {
        $this->code($code);
        $this->header('Location', $url);
        $this->lock();

        return $this;
    }
}
