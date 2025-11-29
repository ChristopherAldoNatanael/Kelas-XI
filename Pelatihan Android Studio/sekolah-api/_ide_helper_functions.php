<?php

/**
 * Laravel IDE Helper - Functions
 * This file provides IDE auto-completion for Laravel helper functions
 * @see https://laravel.com/docs/helpers
 */

if (!function_exists('view')) {
    /**
     * Get the evaluated view contents for the given view.
     * @param  string|null  $view
     * @param  array  $data
     * @param  array  $mergeData
     * @return \Illuminate\Contracts\View\View|\Illuminate\Contracts\View\Factory
     */
    function view($view = null, $data = [], $mergeData = []) {}
}

if (!function_exists('redirect')) {
    /**
     * Get an instance of the redirector.
     * @param  string|null  $to
     * @param  int  $status
     * @param  array  $headers
     * @param  bool|null  $secure
     * @return \Illuminate\Routing\Redirector|\Illuminate\Http\RedirectResponse
     */
    function redirect($to = null, $status = 302, $headers = [], $secure = null) {}
}

if (!function_exists('back')) {
    /**
     * Create a new redirect response to the previous location.
     * @param  int  $status
     * @param  array  $headers
     * @param  mixed  $fallback
     * @return \Illuminate\Http\RedirectResponse
     */
    function back($status = 302, $headers = [], $fallback = false) {}
}

if (!function_exists('response')) {
    /**
     * Return a new response from the application.
     * @param  \Illuminate\Contracts\View\View|string|array|null  $content
     * @param  int  $status
     * @param  array  $headers
     * @return \Illuminate\Http\Response|\Illuminate\Contracts\Routing\ResponseFactory
     */
    function response($content = '', $status = 200, array $headers = []) {}
}

if (!function_exists('bcrypt')) {
    /**
     * Hash the given value against the bcrypt algorithm.
     * @param  string  $value
     * @param  array  $options
     * @return string
     */
    function bcrypt($value, $options = []) {}
}

if (!function_exists('storage_path')) {
    /**
     * Get the path to the storage folder.
     * @param  string  $path
     * @return string
     */
    function storage_path($path = '') {}
}

if (!function_exists('collect')) {
    /**
     * Create a collection from the given value.
     * @param  mixed  $value
     * @return \Illuminate\Support\Collection
     */
    function collect($value = null) {}
}

if (!function_exists('today')) {
    /**
     * Create a new Carbon instance for the current date.
     * @param  \DateTimeZone|string|null  $tz
     * @return \Illuminate\Support\Carbon
     */
    function today($tz = null) {}
}

if (!function_exists('abort')) {
    /**
     * Throw an HttpException with the given data.
     * @param  \Symfony\Component\HttpFoundation\Response|\Illuminate\Contracts\Support\Responsable|int  $code
     * @param  string  $message
     * @param  array  $headers
     * @return never
     */
    function abort($code, $message = '', array $headers = []) {}
}

if (!function_exists('request')) {
    /**
     * Get an instance of the current request or an input item from the request.
     * @param  array|string|null  $key
     * @param  mixed  $default
     * @return mixed|\Illuminate\Http\Request
     */
    function request($key = null, $default = null) {}
}

if (!function_exists('route')) {
    /**
     * Generate the URL to a named route.
     * @param  string  $name
     * @param  mixed  $parameters
     * @param  bool  $absolute
     * @return string
     */
    function route($name, $parameters = [], $absolute = true) {}
}

if (!function_exists('config')) {
    /**
     * Get / set the specified configuration value.
     * @param  array|string|null  $key
     * @param  mixed  $default
     * @return mixed|\Illuminate\Config\Repository
     */
    function config($key = null, $default = null) {}
}

if (!function_exists('env')) {
    /**
     * Gets the value of an environment variable.
     * @param  string  $key
     * @param  mixed  $default
     * @return mixed
     */
    function env($key, $default = null) {}
}

if (!function_exists('app')) {
    /**
     * Get the available container instance.
     * @param  string|null  $abstract
     * @param  array  $parameters
     * @return mixed|\Illuminate\Contracts\Foundation\Application
     */
    function app($abstract = null, array $parameters = []) {}
}

if (!function_exists('auth')) {
    /**
     * Get the available auth instance.
     * @param  string|null  $guard
     * @return \Illuminate\Contracts\Auth\Factory|\Illuminate\Contracts\Auth\Guard|\Illuminate\Contracts\Auth\StatefulGuard
     */
    function auth($guard = null) {}
}

if (!function_exists('session')) {
    /**
     * Get / set the specified session value.
     * @param  array|string|null  $key
     * @param  mixed  $default
     * @return mixed|\Illuminate\Session\Store|\Illuminate\Session\SessionManager
     */
    function session($key = null, $default = null) {}
}

if (!function_exists('now')) {
    /**
     * Create a new Carbon instance for the current time.
     * @param  \DateTimeZone|string|null  $tz
     * @return \Illuminate\Support\Carbon
     */
    function now($tz = null) {}
}

/**
 * Log Facade Helper
 */

namespace Illuminate\Support\Facades {
    class Log
    {
        /**
         * @param string $message
         * @param array $context
         * @return void
         */
        public static function info($message, array $context = []) {}

        /**
         * @param string $message
         * @param array $context
         * @return void
         */
        public static function error($message, array $context = []) {}

        /**
         * @param string $message
         * @param array $context
         * @return void
         */
        public static function warning($message, array $context = []) {}

        /**
         * @param string $message
         * @param array $context
         * @return void
         */
        public static function debug($message, array $context = []) {}
    }

    class Auth
    {
        /**
         * @param array $credentials
         * @return bool
         */
        public static function attempt(array $credentials = []) {}

        /**
         * @return \App\Models\User|null
         */
        public static function user() {}

        /**
         * @return int|string|null
         */
        public static function id() {}

        /**
         * @return bool
         */
        public static function check() {}

        /**
         * @return void
         */
        public static function logout() {}
    }

    class DB
    {
        /**
         * @return void
         */
        public static function beginTransaction() {}

        /**
         * @return void
         */
        public static function commit() {}

        /**
         * @return void
         */
        public static function rollBack() {}

        /**
         * @param string $table
         * @return \Illuminate\Database\Query\Builder
         */
        public static function table($table) {}
    }

    class Validator
    {
        /**
         * @param array $data
         * @param array $rules
         * @param array $messages
         * @param array $customAttributes
         * @return \Illuminate\Validation\Validator
         */
        public static function make(array $data, array $rules, array $messages = [], array $customAttributes = []) {}
    }
}

namespace Illuminate\Http {
    class Request
    {
        /**
         * @param array $rules
         * @param array $messages
         * @param array $customAttributes
         * @return array
         */
        public function validate(array $rules, array $messages = [], array $customAttributes = []) {}

        /**
         * @param string|null $key
         * @param mixed $default
         * @return mixed
         */
        public function input($key = null, $default = null) {}

        /**
         * @param string|null $key
         * @return \Illuminate\Http\UploadedFile|\Illuminate\Http\UploadedFile[]|array|null
         */
        public function file($key = null) {}

        /**
         * @param string|array $key
         * @return bool
         */
        public function has($key) {}

        /**
         * @param array|mixed $keys
         * @return array
         */
        public function only($keys) {}
    }

    class JsonResponse extends \Symfony\Component\HttpFoundation\JsonResponse {}
}

namespace Illuminate\Validation {
    class ValidationException extends \Exception {}
}

namespace Symfony\Component\HttpFoundation {
    class StreamedResponse {}
    class JsonResponse {}
}
