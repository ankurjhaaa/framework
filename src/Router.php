<?php

namespace Kite\Core;

/**
 * The KitePHP Router.
 * Maps incoming HTTP URIs to their corresponding Controller actions or Closure functions.
 */
class Router
{
    // Stores all registered routes
    protected static array $routes = [];
    
    // Maps route names to their URI strings for reverse routing (e.g., route('home'))
    protected static array $namedRoutes = [];
    
    // Variables for route grouping (Prefixes and Middleware)
    protected static string $currentGroupPrefix = '';
    protected static array $currentGroupMiddleware = [];

    /**
     * Register a GET route.
     */
    public static function get(string $uri, $action)
    {
        return self::addRoute('GET', $uri, $action);
    }

    /**
     * Register a POST route.
     */
    public static function post(string $uri, $action)
    {
        return self::addRoute('POST', $uri, $action);
    }
    
    /**
     * Register a PUT route (typically used for full updates).
     */
    public static function put(string $uri, $action)
    {
        return self::addRoute('PUT', $uri, $action);
    }
    
    /**
     * Register a DELETE route (typically used for destroying resources).
     */
    public static function delete(string $uri, $action)
    {
        return self::addRoute('DELETE', $uri, $action);
    }

    /**
     * Internal method to add a route to the collection.
     * It handles grouping prefixes and returns an anonymous class to allow method chaining (e.g., ->name('route_name')).
     */
    protected static function addRoute(string $method, string $uri, $action)
    {
        // Prepend the current group prefix if we are inside a route group
        $uri = self::$currentGroupPrefix . $uri;
        
        // Normalize the URI (remove trailing slashes, unless it's just '/')
        $uri = $uri === '/' ? $uri : rtrim($uri, '/');

        // Build the route array
        $route = [
            'method' => $method,
            'uri' => $uri,
            'action' => $action,
            'middleware' => self::$currentGroupMiddleware
        ];

        // Store the route in the global list
        self::$routes[] = $route;
        
        // Get the index of the newly added route so we can modify it via chaining
        $routeIndex = count(self::$routes) - 1;

        // Return an anonymous class to enable method chaining, specifically for naming the route
        return new class($routeIndex) {
            private int $index;
            public function __construct(int $index) { $this->index = $index; }
            
            // Assigns a name to the route
            public function name(string $name) {
                Router::nameRoute($this->index, $name);
                return $this;
            }
        };
    }

    /**
     * Assign a unique name to a route index.
     */
    public static function nameRoute(int $index, string $name)
    {
        if (isset(self::$routes[$index])) {
            self::$routes[$index]['name'] = $name;
            self::$namedRoutes[$name] = self::$routes[$index]['uri'];
        }
    }

    /**
     * Generate a URL based on a named route.
     * Replaces dynamic parameters in the URI (e.g., {id}) with provided values.
     */
    public static function route(string $name, array $parameters = [])
    {
        // If the route name doesn't exist, throw a 500 error to alert the developer immediately
        if (!isset(self::$namedRoutes[$name])) {
            abort(500, "Route [{$name}] not defined.");
        }

        $uri = self::$namedRoutes[$name];
        
        // Inject parameters into the URI placeholders
        foreach ($parameters as $key => $value) {
            $uri = str_replace('{' . $key . '}', (string) $value, $uri);
        }

        // Return the fully qualified URL (e.g., http://localhost:8000/uri)
        return url($uri);
    }

    /**
     * Dispatch the incoming request.
     * Matches the request URI and Method against registered routes and executes the matched action.
     */
    public static function dispatch(Request $request)
    {
        $uri = rtrim($request->uri, '/');
        if ($uri === '') $uri = '/';

        $methodNotAllowed = false;

        foreach (self::$routes as $route) {
            // Convert Route URI placeholders (e.g., {id}) into Regex capture groups
            $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<\1>[a-zA-Z0-9_.-]+)', $route['uri']);
            $pattern = '#^' . $pattern . '$#';

            // Check if the current request URI matches the regex pattern
            if (preg_match($pattern, $uri, $matches)) {
                
                // Check if HTTP method matches (or if the route accepts ANY method)
                if ($route['method'] !== $request->method && $route['method'] !== 'ANY') {
                    $methodNotAllowed = true;
                    continue; // URI matches, but method is wrong. Keep looking.
                }

                // Extract matched parameters (e.g., 'id' => 5)
                $parameters = [];
                foreach ($matches as $key => $value) {
                    if (is_string($key)) {
                        $parameters[$key] = $value;
                    }
                }

                // Execute the route action (Controller or Closure)
                $response = self::execute($route['action'], $parameters, $request);
                
                // If the action returned a proper Response object, return it to the App
                if ($response instanceof Response) {
                    return $response;
                }
                
                // If the action already echoed output directly (like View::make), 
                // return a dummy response object to satisfy the App::run() requirement
                return new class {
                    public function send() {}
                };
            }
        }

        if ($methodNotAllowed) {
            abort(405, 'Request method not supported for this route.');
        }

        // If loop finishes without matching any route, throw a 404 Not Found exception
        abort(404, 'Page not found');
    }

    /**
     * Execute the matched route action.
     * The action can be a Closure (anonymous function) or a "Controller@method" string.
     */
    protected static function execute($action, array $parameters, Request $request)
    {
        // Always pass the Request object as the first parameter to the action
        array_unshift($parameters, $request);

        // If the action is an anonymous function, call it directly
        if (is_callable($action)) {
            return call_user_func_array($action, array_values($parameters));
        }

        // If the action is a string (e.g., "HomeController@index"), resolve and instantiate the controller
        if (is_string($action)) {
            // Split the string into Class and Method
            list($controller, $method) = explode('@', $action);
            
            // Build the fully qualified namespace
            $controllerClass = "App\\Controller\\" . $controller;
            
            // Check if the class exists, instantiate it, and call the specified method
            if (class_exists($controllerClass)) {
                $instance = new $controllerClass();
                return call_user_func_array([$instance, $method], array_values($parameters));
            }
        }
        
        // If we reach here, the action was invalid or the controller class was missing
        abort(500, 'Route action not found or invalid format.');
    }
}
