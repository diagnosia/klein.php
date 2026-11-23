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

namespace Klein\DataCollection;

/**
 * HeaderDataCollection
 *
 * A DataCollection for HTTP headers
 */
class HeaderDataCollection extends DataCollection
{
    /**
     * Normalization option
     *
     * Don't normalize
     */
    const int NORMALIZE_NONE = 0;

    /**
     * Normalization option
     *
     * Normalize the outer whitespace of the header
     */
    const int NORMALIZE_TRIM = 1;

    /**
     * Normalization option
     *
     * Normalize the delimiters of the header
     */
    const int NORMALIZE_DELIMITERS = 2;

    /**
     * Normalization option
     *
     * Normalize the case of the header
     */
    const int NORMALIZE_CASE = 4;

    /**
     * Normalization option
     *
     * Normalize the header into canonical format
     */
    const int NORMALIZE_CANONICAL = 8;

    /**
     * Normalization option
     *
     * Normalize using all normalization techniques
     */
    const int NORMALIZE_ALL = -1;

    /**
     * The header key normalization technique/style to
     * use when accessing headers in the collection
     */
    protected int $normalization = self::NORMALIZE_ALL;

    /**
     * Constructor
     *
     * @override (doesn't call our parent)
     * @param array $headers        The headers of this collection
     * @param int $normalization    The header key normalization technique/style to use
     */
    public function __construct(array $headers = [], int $normalization = self::NORMALIZE_ALL)
    {
        $this->normalization = $normalization;

        foreach ($headers as $key => $value) {
            $this->set($key, $value);
        }
    }

    /**
     * Get the header key normalization technique/style to use
     */
    public function getNormalization(): int
    {
        return $this->normalization;
    }

    /**
     * Set the header key normalization technique/style to use
     */
    public function setNormalization(int $normalization): static
    {
        $this->normalization = $normalization;

        return $this;
    }

    /**
     * Get a header
     *
     * {@inheritdoc}
     *
     * @see DataCollection::get()
     * @param string|int $key       The key of the header to return
     * @param mixed  $default_val   The default value of the header if it contains no value
     */
    public function get(string|int $key, mixed $default_val = null): mixed
    {
        $key = $this->normalizeKey($key);

        return parent::get($key, $default_val);
    }

    /**
     * Set a header
     *
     * {@inheritdoc}
     *
     * @see DataCollection::set()
     * @param string|int $key   The key of the header to set
     * @param mixed  $value     The value of the header to set
     */
    public function set(string|int $key, mixed $value): static
    {
        $key = $this->normalizeKey($key);

        return parent::set($key, $value);
    }

    /**
     * Check if a header exists
     *
     * {@inheritdoc}
     *
     * @see DataCollection::exists()
     * @param string|int $key   The key of the header
     */
    public function exists(string|int $key): bool
    {
        $key = $this->normalizeKey($key);

        return parent::exists($key);
    }

    /**
     * Remove a header
     *
     * {@inheritdoc}
     *
     * @see DataCollection::remove()
     * @param string|int $key   The key of the header
     */
    public function remove(string|int $key): void
    {
        $key = $this->normalizeKey($key);

        parent::remove($key);
    }

    /**
     * Normalize a header key based on our set normalization style
     *
     * @param string|int $key The ("field") key of the header
     */
    protected function normalizeKey(string|int $key): string
    {
        $key = (string) $key;

        // The normalization steps, applied in this order if their flag is set
        $normalizers = [
            static::NORMALIZE_TRIM => trim(...),
            static::NORMALIZE_DELIMITERS => static::normalizeKeyDelimiters(...),
            static::NORMALIZE_CASE => strtolower(...),
            static::NORMALIZE_CANONICAL => static::canonicalizeKey(...),
        ];

        foreach ($normalizers as $flag => $normalizer) {
            if (!($this->normalization & $flag)) {
                continue;
            }

            $key = $normalizer($key);
        }

        return $key;
    }

    /**
     * Normalize a header key's delimiters
     *
     * This will convert any space or underscore characters
     * to a more standard hyphen (-) character
     *
     * @param string $key The ("field") key of the header
     */
    public static function normalizeKeyDelimiters(string $key): string
    {
        return str_replace([' ', '_'], replace: '-', subject: $key);
    }

    /**
     * Canonicalize a header key
     *
     * The canonical format is all lower case except for
     * the first letter of "words" separated by a hyphen
     *
     * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec4.html#sec4.2
     * @param string $key The ("field") key of the header
     */
    public static function canonicalizeKey(string $key): string
    {
        $words = explode('-', strtolower($key));

        foreach ($words as &$word) {
            $word = ucfirst($word);
        }

        return implode('-', $words);
    }

    /**
     * Normalize a header name by formatting it in a standard way
     *
     * This is useful since PHP automatically capitalizes and underscore
     * separates the words of headers
     *
     * @todo Possibly remove in future, here for backwards compatibility
     * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec4.html#sec4.2
     * @param string $name              The name ("field") of the header
     * @param boolean $make_lowercase   Whether or not to lowercase the name
     * @deprecated Use the normalization options and the other normalization methods instead
     */
    public static function normalizeName(string $name, bool $make_lowercase = true): string
    {
        // Warn user of deprecation
        trigger_error('Use the normalization options and the other normalization methods instead.', E_USER_DEPRECATED);

        /**
         * Lowercasing header names allows for a more uniform appearance,
         * however header names are case-insensitive by specification
         */
        if ($make_lowercase) {
            $name = strtolower($name);
        }

        // Do some formatting and return
        return str_replace([' ', '_'], replace: '-', subject: trim($name));
    }
}
