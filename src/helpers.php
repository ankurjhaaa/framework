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
     * Generate standard SEO meta tags (Title, Description, Canonical).
     */
    function seo(array $data)
    {
        $html = '';
        if (isset($data['title'])) {
            $html .= '<title>' . e($data['title']) . '</title>' . PHP_EOL;
            $html .= '<meta property="og:title" content="' . e($data['title']) . '">' . PHP_EOL;
            $html .= '<meta name="twitter:title" content="' . e($data['title']) . '">' . PHP_EOL;
        }
        if (isset($data['description'])) {
            $html .= '<meta name="description" content="' . e($data['description']) . '">' . PHP_EOL;
            $html .= '<meta property="og:description" content="' . e($data['description']) . '">' . PHP_EOL;
            $html .= '<meta name="twitter:description" content="' . e($data['description']) . '">' . PHP_EOL;
        }
        if (isset($data['canonical'])) {
            $html .= '<link rel="canonical" href="' . e($data['canonical']) . '">' . PHP_EOL;
            $html .= '<meta property="og:url" content="' . e($data['canonical']) . '">' . PHP_EOL;
        }
        return $html;
    }
}
