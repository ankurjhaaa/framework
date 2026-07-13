<?php

/**
 * --------------------------------------------------------------------------
 * KitePHP Framework Helper Functions
 * --------------------------------------------------------------------------
 * These functions are globally available anywhere in your application.
 * They act as convenient shortcuts to core framework classes.
 * 
 * This file is auto-loaded by Composer via the echovel/kitephp package.
 */

use Kite\Core\Env;
use Kite\Core\Router;
use Kite\Core\View;
use Kite\Core\Request;
use Kite\Core\Response;
use Kite\Core\Session;
use Kite\Core\Database;

if (!function_exists('env')) {
    /**
     * Get a value from the .env file.
     */
    function env(string $key, $default = null)
    {
        return Env::get($key, $default);
    }
}

if (!function_exists('db')) {
    /**
     * Start a new database query on a given table.
     */
    function db(string $table)
    {
        return Database::table($table);
    }
}

// --------------------------------------------------------------------------
// Route Registration Helpers (Useful inside route/url.php)
// --------------------------------------------------------------------------
if (!function_exists('get')) {
    function get(string $uri, $action) { return Router::get($uri, $action); }
}
if (!function_exists('post')) {
    function post(string $uri, $action) { return Router::post($uri, $action); }
}
if (!function_exists('put')) {
    function put(string $uri, $action) { return Router::put($uri, $action); }
}
if (!function_exists('delete')) {
    function delete(string $uri, $action) { return Router::delete($uri, $action); }
}

if (!function_exists('view')) {
    /**
     * Render a view template.
     */
    function view(string $view, array $data = [])
    {
        return View::make($view, $data);
    }
}

if (!function_exists('redirect')) {
    /**
     * Redirect the user to a specific URL (Handles KiteJS SPA redirects automatically).
     */
    function redirect(string $url, int $status = 302)
    {
        return Response::redirect($url, $status);
    }
}

if (!function_exists('route')) {
    /**
     * Generate an absolute URL for a named route.
     */
    function route(string $name, array $parameters = [])
    {
        return Router::route($name, $parameters);
    }
}

if (!function_exists('url')) {
    /**
     * Generate an absolute URL for an arbitrary path.
     */
    function url(string $path = '')
    {
        $baseUrl = rtrim(env('APP_URL', 'http://localhost'), '/');
        return $baseUrl . ($path ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('asset')) {
    /**
     * Generate a URL for a static asset (CSS, JS, Images).
     */
    function asset(string $path)
    {
        return url($path);
    }
}

if (!function_exists('request')) {
    /**
     * Get the current Request instance to access inputs, headers, etc.
     */
    function request()
    {
        return Request::capture();
    }
}

if (!function_exists('session')) {
    /**
     * Get or set session values, or retrieve the Session instance.
     */
    function session(?string $key = null, $default = null)
    {
        if ($key === null) {
            return Session::instance();
        }
        return Session::get($key, $default);
    }
}

if (!function_exists('cookie')) {
    /**
     * Retrieve a value from the $_COOKIE array.
     */
    function cookie(string $key, $default = null)
    {
        return $_COOKIE[$key] ?? $default;
    }
}

if (!function_exists('json')) {
    /**
     * Return a JSON response to the browser.
     */
    function json($data, int $status = 200)
    {
        return Response::json($data, $status);
    }
}

if (!function_exists('abort')) {
    /**
     * Halt execution and throw an HTTP exception (e.g., abort(404)).
     */
    function abort(int $code, string $message = '')
    {
        throw new \Exception($message ?: "Error {$code}", $code);
    }
}

if (!function_exists('back')) {
    /**
     * Redirect the user back to their previous page.
     */
    function back()
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? url('/');
        return redirect($referer);
    }
}

if (!function_exists('csrf')) {
    /**
     * Generate a hidden CSRF token input field for forms.
     */
    function csrf()
    {
        $token = session('_token');
        if (!$token) {
            $token = bin2hex(random_bytes(32));
            session()->put('_token', $token);
        }
        return '<input type="hidden" name="_token" value="' . $token . '">';
    }
}

if (!function_exists('old')) {
    /**
     * Retrieve old form input after a failed validation redirect.
     */
    function old(string $key, $default = '')
    {
        static $oldInput = null;
        if ($oldInput === null) {
            $oldInput = session('_old_input', []);
        }
        return $oldInput[$key] ?? $default;
    }
}

if (!function_exists('errors')) {
    /**
     * Retrieve validation errors. 
     * If $field is provided, returns the first error for that field, or null.
     */
    function errors(?string $field = null)
    {
        static $errors = null;
        if ($errors === null) {
            $errors = session('errors', []);
        }
        if ($field) {
            return $errors[$field][0] ?? null;
        }
        return $errors;
    }
}

if (!function_exists('e')) {
    /**
     * Safely escape HTML entities to prevent XSS attacks.
     */
    function e(string $value)
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8', false);
    }
}

if (!function_exists('seo')) {
    /**
     * Access the Auto-SEO Engine.
     */
    function seo()
    {
        return \Kite\Core\Seo::instance();
    }
}

if (!function_exists('auth')) {
    /**
     * Get the currently authenticated user, or the Auth engine itself if no method is called.
     * Example: auth()->check(), auth()->user(), auth()->attempt()
     */
    function auth()
    {
        return new class {
            public function __call($method, $args)
            {
                return \Kite\Core\Auth::$method(...$args);
            }
            public function user() { return \Kite\Core\Auth::user(); }
            public function check() { return \Kite\Core\Auth::check(); }
            public function id() { return \Kite\Core\Auth::id(); }
            public function attempt(array $credentials) { return \Kite\Core\Auth::attempt($credentials); }
            public function login($user) { return \Kite\Core\Auth::login($user); }
            public function logout() { return \Kite\Core\Auth::logout(); }
        };
    }
}

