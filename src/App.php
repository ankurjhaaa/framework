<?php

namespace Kite\Core;

/**
 * The main application wrapper for KitePHP.
 * This class handles the initialization of the application and the routing of incoming HTTP requests.
 * It serves as the entry point for the framework's core lifecycle.
 */
class App
{
    /**
     * The absolute path to the root directory of the project.
     */
    protected string $basePath;

    /**
     * Static reference to the base path, accessible by all core classes.
     */
    protected static string $rootPath = '';

    /**
     * App constructor.
     * Sets the base path and calls the bootstrap method to prepare the environment.
     *
     * @param string $basePath The root directory path (e.g., /var/www/kitephp)
     */
    public function __construct(string $basePath)
    {
        // Remove trailing slashes to ensure consistent path resolution
        $this->basePath = rtrim($basePath, '/');
        self::$rootPath = $this->basePath;
        
        // Initialize the framework components
        $this->bootstrap();
    }

    /**
     * Get the application's root path.
     * Used by View, Migrator, and other core classes to resolve user project paths.
     */
    public static function basePath(): string
    {
        return self::$rootPath;
    }

    /**
     * Set the base path manually (used by CLI tools that don't fully boot the App).
     */
    public static function setBasePath(string $path): void
    {
        self::$rootPath = rtrim($path, '/');
    }

    /**
     * Bootstraps the application.
     * This method loads environment variables, registers error handlers, and loads route files.
     */
    protected function bootstrap(): void
    {
        // 1. Load environment variables from the .env file into the $_ENV superglobal
        Env::load($this->basePath . '/.env');

        // 2. Register our custom, professional error handler
        // This replaces the default PHP error output with a beautiful, readable error page
        if (class_exists(ErrorHandler::class)) {
            ErrorHandler::register();
        }

        // 3. Configure native PHP error reporting as a fallback
        // If APP_DEBUG is true in .env, we show all errors. Otherwise, we hide them for production security.
        $debug = Env::get('APP_DEBUG', false);
        if ($debug) {
            ini_set('display_errors', '1');
            ini_set('display_startup_errors', '1');
            error_reporting(E_ALL);
        } else {
            ini_set('display_errors', '0');
            error_reporting(0);
        }

        // 4. Register core Global Middlewares & Aliases
        Router::addGlobalMiddleware(\Kite\Core\Middleware\VerifyCsrfToken::class);
        Router::aliasMiddleware('auth', \Kite\Core\Middleware\Authenticate::class);

        // 5. Load the user-defined route files
        $this->loadRoutes();
    }

    /**
     * Load the application's route definition files.
     * It includes web routes (for browser access) and api routes (for JSON endpoints).
     */
    protected function loadRoutes(): void
    {
        // Load web routes (Standard HTML pages)
        $webRoutePath = $this->basePath . '/route/url.php';
        if (file_exists($webRoutePath)) {
            require $webRoutePath;
        }

        // Load API routes (JSON responses, usually prefixed with /api)
        $apiRoutePath = $this->basePath . '/route/api.php';
        if (file_exists($apiRoutePath)) {
            require $apiRoutePath;
        }
    }

    /**
     * Run the application.
     * This method captures the incoming HTTP request, dispatches it to the Router, 
     * and sends the final HTTP response back to the client.
     */
    public function run(): void
    {
        // Capture the current HTTP request (URI, Method, Headers, POST data)
        $request = Request::capture();
        
        // Pass the request to the Router to find the matching route and execute its controller
        $response = Router::dispatch($request);
        
        // Output the final headers and body to the browser
        $response->send();
    }
}
