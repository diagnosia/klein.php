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

use InvalidArgumentException;
use JsonException;
use Klein\Exceptions\ResponseAlreadySentException;
use RuntimeException;

/**
 * Response
 */
class Response extends AbstractResponse
{
    /**
     * The allowed JSONP callback names: a JavaScript identifier, optionally dotted (e.g. "jQuery123" or "app.cb")
     *
     * Anything else could inject script into the JSONP response
     *
     * @var string
     */
    const JSONP_CALLBACK_REGEX = '/^[A-Za-z_$][0-9A-Za-z_$]*(?:\.[A-Za-z_$][0-9A-Za-z_$]*)*$/D';

    /**
     * Methods
     */

    /**
     * Enable response chunking
     *
     * @link https://github.com/klein/klein.php/wiki/Response-Chunking
     * @link http://bit.ly/hg3gHb
     * @param string $str   An optional string to send as a response "chunk"
     * @return Response
     */
    public function chunk($str = null)
    {
        parent::chunk();

        if (null !== $str) {
            // printf("%x\r\n", strlen($str));
            echo "{$str}\r\n";
            flush();
        }

        return $this;
    }

    /**
     * Dump a variable
     *
     * @param mixed $obj    The variable to dump
     * @return Response
     */
    public function dump($obj)
    {
        // Arrays and objects are printed, anything else (int, float, bool, null) is cast
        $obj = is_array($obj) || is_object($obj) ? print_r($obj, return: true) : (string) $obj;

        $this->append('<pre>' . htmlentities($obj, ENT_QUOTES) . "</pre><br />\n");

        return $this;
    }

    /**
     * Sends a file
     *
     * It should be noted that this method disables caching
     * of the response by default, as dynamically created
     * files responses are usually downloads of some type
     * and rarely make sense to be HTTP cached
     *
     * Also, this method removes any data/content that is
     * currently in the response body and replaces it with
     * the file's data
     *
     * @param string $path      The path of the file to send
     * @param string $filename  The file's name
     * @param string $mimetype  The MIME type of the file, detected from the file if null
     * @throws RuntimeException Thrown if the file could not be read (before anything is sent)
     * @return Response
     */
    public function file($path, $filename = null, $mimetype = null)
    {
        if ($this->sent) {
            throw new ResponseAlreadySentException('Response has already been sent');
        }

        // Check up front, so a missing file doesn't send headers (with an empty Content-length) first
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('The file could not be read');
        }

        $this->body('');
        $this->noCache();

        if (null === $filename) {
            $filename = basename($path);
        }
        if (null === $mimetype) {
            $mimetype = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $path);

            // Fall back to a generic binary type if detection fails
            if (false === $mimetype) {
                $mimetype = 'application/octet-stream';
            }
        }

        $this->header('Content-type', $mimetype);
        $this->header('Content-Disposition', static::attachmentDisposition((string) $filename));

        // If the response is to be chunked, then the content length must not be sent
        // see: https://www.w3.org/Protocols/rfc2616/rfc2616-sec4.html#sec4.4
        if (false === $this->chunked) {
            $this->header('Content-length', filesize($path));
        }

        // Send our response data
        $this->sendHeaders();

        $bytes_read = readfile($path);

        if (false === $bytes_read) {
            throw new RuntimeException('The file could not be read');
        }

        $this->sendBody();

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
     * Builds a Content-Disposition attachment header value for a filename (RFC 6266)
     *
     * Quotes, backslashes and non-printable or non-ASCII characters can't go into the quoted
     * filename as is, so they are replaced there and the exact name is added as UTF-8 "filename*"
     *
     * @param string $filename  The file's name
     * @return string
     */
    protected static function attachmentDisposition($filename)
    {
        $fallback = preg_replace('/[^\x20-\x7E]|["\\\\]/', replacement: '_', subject: $filename);

        $disposition = 'attachment; filename="' . $fallback . '"';

        if ($fallback !== $filename) {
            $disposition .= "; filename*=UTF-8''" . rawurlencode($filename);
        }

        return $disposition;
    }

    /**
     * Sends an object as json or jsonp by providing the padding prefix
     *
     * It should be noted that this method disables caching
     * of the response by default, as json responses are usually
     * dynamic and rarely make sense to be HTTP cached
     *
     * Also, this method removes any data/content that is
     * currently in the response body and replaces it with
     * the passed json encoded object
     *
     * @param mixed $object         The data to encode as JSON
     * @param string $jsonp_prefix  The name of the JSON-P function prefix, see JSONP_CALLBACK_REGEX
     * @throws InvalidArgumentException If the JSON-P function prefix isn't a valid callback name
     * @throws JsonException            If the object can't be encoded (e.g. invalid UTF-8), before anything is sent
     * @return Response
     */
    public function json($object, $jsonp_prefix = null)
    {
        // The prefix usually comes straight from a request param, so it must not be able to inject script
        if (
            null !== $jsonp_prefix
            && (!is_string($jsonp_prefix) || preg_match(static::JSONP_CALLBACK_REGEX, $jsonp_prefix) !== 1)
        ) {
            throw new InvalidArgumentException('The JSONP callback name must be a (dotted) JavaScript identifier');
        }

        // Encode first, so a failure leaves the response untouched instead of sending an empty 200 response
        $json = json_encode($object, JSON_THROW_ON_ERROR);

        $this->body('');
        $this->noCache();

        $is_jsonp = null !== $jsonp_prefix;

        // JSONP should ideally be application/json-p once adopted
        $this->header('Content-Type', $is_jsonp ? 'text/javascript' : 'application/json');
        $this->body($is_jsonp ? "{$jsonp_prefix}({$json});" : $json);

        $this->send();

        return $this;
    }
}
