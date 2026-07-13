<?php

namespace Kite\Core;

use Kite\Core\Validator;
use Kite\Core\Session;

/**
 * The Request class.
 * Captures and encapsulates incoming HTTP request data such as URI, Method, Query strings, 
 * POST payloads, File uploads, and Headers into an object-oriented format.
 */
class Request
{
    public string $method;   // e.g., GET, POST
    public string $uri;      // e.g., /about-us
    public array $query;     // Data from $_GET
    public array $post;      // Data from $_POST or JSON body
    public array $files;     // Data from $_FILES
    public array $server;    // Data from $_SERVER
    public array $headers;   // Extracted HTTP headers

    /**
     * Automatically capture the current global HTTP state and return a Request instance.
     */
    public static function capture(): self
    {
        $request = new self();
        
        // Determine method and exact path
        $request->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $request->uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        
        // Grab superglobal data
        $request->query = $_GET;
        $request->post = $_POST;
        $request->files = $_FILES;
        $request->server = $_SERVER;
        
        // Extract headers from the $_SERVER array (keys starting with 'HTTP_')
        $request->headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                // Convert 'HTTP_CONTENT_TYPE' into 'Content-Type'
                $header = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $request->headers[$header] = $value;
            }
        }

        // Handle raw JSON payloads and PUT/PATCH form data
        // PHP doesn't populate $_POST for JSON requests, so we must read 'php://input'
        if (in_array($request->method, ['PUT', 'PATCH', 'DELETE']) || strpos($request->header('Content-Type', ''), 'application/json') !== false) {
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            if (is_array($data)) {
                $request->post = array_merge($request->post, $data);
            }
        }

        return $request;
    }

    /**
     * Retrieve a specific HTTP header value.
     */
    public function header(string $name, $default = null)
    {
        return $this->headers[$name] ?? $default;
    }

    /**
     * Check if the current request was sent via AJAX (KiteJS or standard XMLHttpRequest).
     * This is useful for returning JSON instead of HTML views.
     */
    public function isAjax(): bool
    {
        return $this->header('X-Requested-With') === 'XMLHttpRequest' || $this->header('X-Kite-Request') === 'true';
    }

    /**
     * Get a specific input value, checking POST payload first, then GET query string.
     */
    public function input(string $key, $default = null)
    {
        return $this->post[$key] ?? $this->query[$key] ?? $default;
    }

    /**
     * Get all input data (merged GET and POST).
     */
    public function all(): array
    {
        return array_merge($this->query, $this->post);
    }

    /**
     * Validate the current request data against the given rules.
     * Automatically redirects back with errors if validation fails.
     */
    public function validate(array $rules): array
    {
        $validator = Validator::make($this->all(), $rules);

        if ($validator->fails()) {
            // Flash errors and old input
            Session::instance()->flash('errors', $validator->errors());
            Session::instance()->flash('_old_input', $this->all());
            
            // Redirect back
            $referer = $_SERVER['HTTP_REFERER'] ?? '/';
            header("Location: {$referer}");
            exit;
        }

        // Return only the validated data
        $validated = [];
        foreach ($rules as $key => $rule) {
            $validated[$key] = $this->input($key);
        }
        return $validated;
    }
}
