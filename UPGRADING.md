# Klein Upgrade Guide


## 4.3.x to the next release (unreleased)

### Interface Changes

All methods, properties and class constants now have native types. Code that only *calls* Klein is mostly
unaffected (arguments of the wrong type throw a `TypeError` instead of being coerced or misbehaving later), but
code that *extends* Klein's classes needs to match the new types:

- Methods you override need compatible return types (e.g. `public function send(bool $override = false): static`
  in a `Response` subclass), and must not narrow parameter types
- Properties you redeclare need the same type (e.g. `protected array $match_types` in a `Klein` subclass)
- Class constants you redeclare need the same type (e.g. `const int NORMALIZE_ALL` in a `HeaderDataCollection`
  subclass)
- Fluent methods return `static`, getter/setter pairs `<type>|static` (e.g. `AbstractResponse::body()`)
- `KleinExceptionInterface` (and so `HttpExceptionInterface`) extends `Throwable`, so classes implementing it
  must extend `Exception` or `Error`
- `Klein::request()` and `Klein::response()` are typed nullable: they are `null` until the first dispatch
- `HttpStatus` and `HttpException::createFromCode()` take an `int` code between 100 and 599
- `Response::json()` takes a `string|null` JSONP prefix, `Request::__construct()` a `string|null` body

### Behavior Changes

- `Response::header()`, `cookie()` and `noCache()` (and the first `chunk()`) throw a `LockedResponseException` on a
  locked response, like `body()` and `code()` always did. A response is locked by `redirect()`, `send()`, and after
  an `onError()`/`onHttpError()` handler ran. This affects headers added in an `afterDispatch()` callback (e.g. CORS
  headers): after a redirect or a handled error, the exception is passed to the `onError()` callbacks and the header
  is missing. Unlock the response around such changes if they should apply to every response:

  ```php
  $klein->afterDispatch(function ($klein) {
      $response = $klein->response();
      $locked = $response->isLocked();

      $response->unlock()->header('Access-Control-Allow-Origin', '*');

      if ($locked) {
          $response->lock();
      }
  });
  ```
- `RouteFactory::build()` uses its `$name` and `$count_match` arguments, which custom callers may have passed
  expecting them to be ignored
- `ServiceProvider::startSession()` returns the id of an already active session instead of `null`
- `ServiceProvider::isBond()` is deprecated in favor of `isBound()`


## 2.1.1 to 2.1.2

### Interface Changes

- The `RoutePathCompilationException::createFromRoute()` method signature has changed to allow both `Exception` and `Throwable` types with dual support for PHP 5 and PHP 7
- The 4th parameter to the callbacks supported by `Klein#onError` will now be able to receive `Throwable` types under PHP 7


## 2.1.0 to 2.1.1

### Deprecations

- The `HeaderDataCollection::normalizeName()` method has been deprecated in favor of using new normalization options (via constant switches) and other more specific methods on the same class

### Interface Changes

- Three of the Klein internal callback attributes have changed both name and data structure. These attributes are protected, so the effect will only be felt by users that have extended and/or overwritten Klein's internal behaviors. The following changes were made:
    - `Klein#errorCallbacks` was renamed to `Klein#error_callbacks` and it's array data-structure was changed to use an `SplStack`
    - `Klein#httpErrorCallbacks` was renamed to `Klein#http_error_callbacks` and it's array data-structure was changed to use an `SplStack`
    - `Klein#afterFilterCallbacks` was renamed to `Klein#after_filter_callbacks` and it's array data-structure was changed to use an `SplQueue`
- `Validator#defaultAdded` was renamed to `Validator#default_added`


## 2.0.x to 2.1.0

### Deprecations

- Handling 404 and 405 errors with a specially registered route callback is now deprecated. It's now suggested to use Klein's new `onHttpError()` method instead.
- Autoloading the library with Composer no longer utilizes the PSR-0 spec. The composer autoloader now uses PSR-4.

### Interface Changes

- Some of the route callback params have changed. This will effect any route definitions with callbacks using the more advanced parameters.
    - The old params were (in order):
        - `Request $request`
        - `Response $response`
        - `Service $service`
        - `App $app`
        - `int $matched`
        - `array $methods_matched`
    - The new params are (in order):
        - `Request $request`
        - `Response $response`
        - `Service $service`
        - `App $app`
        - `Klein $klein`
        - `RouteCollection $matched`
        - `array $methods_matched`
- Non-match routes (routes that are wildcard and shouldn't consider as "matches") will no longer be considered as part of the "methods matched" array, since they aren't supposed to be matches in the first place
    - This may have implications for users that have created "match-all" OPTIONS method routes, as the OPTIONS method will no longer be considered a match.
    - If you'd like to conserve the old match behavior, you can simply mark the route as one that should be counted as a match with `$route->setCountMatch(true)`
