<?php

namespace Kite\Core;

/**
 * The Response Helper.
 * Contains static methods to easily return HTTP responses like JSON or perform Redirects.
 */
class Response
{
    /**
     * Redirect the user to a different URL.
     * It intelligently handles both standard browser requests and KiteJS (AJAX) SPA requests.
     * 
     * @param string $url The destination URL
     * @param int $status The HTTP status code (default 302 Found)
     */
    public static function redirect(string $url, int $status = 302)
    {
        // Check if the request came from KiteJS (SPA frontend)
        if (isset($_SERVER['HTTP_X_KITE_REQUEST']) || isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            // For AJAX, we don't do a hard redirect. 
            // Instead, we send a custom header instructing KiteJS to update the browser URL and fetch the new page.
            header('X-Kite-Redirect: ' . $url);
            http_response_code(200); // Must be 200 so fetch() doesn't throw an error
            exit;
        }

        // For standard browser requests, do a normal PHP HTTP redirect
        header('Location: ' . $url, true, $status);
        exit;
    }

    /**
     * Return a JSON response to the client.
     * Useful for building APIs.
     * 
     * @param mixed $data The array or object to be converted to JSON
     * @param int $status The HTTP status code (default 200 OK)
     */
    public static function json($data, int $status = 200)
    {
        // Tell the browser that the response is JSON
        header('Content-Type: application/json');
        
        // Set the HTTP status code (e.g., 200, 404, 500)
        http_response_code($status);
        
        // Print the JSON string
        echo json_encode($data);
        
        // Halt script execution
        exit;
    }
}
