<?php

namespace Kite\Core;

/**
 * Global Error and Exception Handler.
 * Intercepts PHP errors, fatals, and exceptions, and renders them beautifully 
 * in the browser (if debug mode is on) or hides them securely (if debug is off).
 */
class ErrorHandler
{
    /**
     * Register the custom handlers with PHP's core.
     */
    public static function register()
    {
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    /**
     * Convert standard PHP errors (warnings, notices) into strict Exceptions.
     */
    public static function handleError($level, $message, $file = '', $line = 0)
    {
        if (error_reporting() & $level) {
            throw new \ErrorException($message, 0, $level, $file, $line);
        }
    }

    /**
     * Handle all uncaught Exceptions.
     * This is where the magic happens for displaying the beautiful error page.
     */
    public static function handleException(\Throwable $exception)
    {
        // CRITICAL: Clear all current output buffers so that any half-rendered HTML 
        // doesn't mix with our error page.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $code = $exception->getCode();
        
        // Map common codes to HTTP statuses, default to 500 (Internal Server Error)
        $httpCode = ($code >= 400 && $code < 600) ? $code : 500;
        
        http_response_code($httpCode);

        $request = Request::capture();

        // Check if debug mode is active in .env
        if (Env::get('APP_DEBUG', false)) {
            
            // If it's an AJAX request expecting JSON, return a JSON error instead of HTML
            if ($request->isAjax() && strpos($request->header('Accept'), 'application/json') !== false) {
                Response::json([
                    'message' => $exception->getMessage(),
                    'file'    => $exception->getFile(),
                    'line'    => $exception->getLine(),
                    'trace'   => $exception->getTrace()
                ], $httpCode);
            } else {
                // Otherwise, render the beautiful HTML error page
                self::renderErrorPage($exception);
            }
            
        } else {
            // Production Mode (Debug = False): Hide sensitive stack traces
            $message = $httpCode == 404 ? 'Page Not Found' : 'An error occurred. Please try again later.';
            
            if ($request->isAjax()) {
                Response::json(['error' => $message], $httpCode);
            } else {
                // Show a simple generic error message to the user
                echo "<h1 style='text-align:center;font-family:sans-serif;margin-top:50px;color:#333;'>{$httpCode} - {$message}</h1>";
            }
        }
    }

    /**
     * Catch fatal errors that bypass the standard error handler (like Out of Memory or Syntax Errors).
     */
    public static function handleShutdown()
    {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            self::handleException(new \ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']));
        }
    }

    /**
     * Prepare data and render the custom error view page.
     */
    protected static function renderErrorPage(\Throwable $exception)
    {
        $file = $exception->getFile();
        $line = $exception->getLine();
        
        $lines = [];
        // Extract 10 lines of code before and after the line that caused the error
        if (file_exists($file)) {
            $fileContent = file($file);
            $start = max(0, $line - 10);
            $end = min(count($fileContent) - 1, $line + 10);
            for ($i = $start; $i <= $end; $i++) {
                $lines[$i + 1] = rtrim($fileContent[$i]);
            }
        }
        
        try {
            $data = [
                'exception'   => $exception,
                'class'       => get_class($exception),
                'message'     => $exception->getMessage(),
                'file'        => $file,
                'line'        => $line,
                'trace'       => $exception->getTraceAsString(),
                'traceArray'  => $exception->getTrace(),
                'codeSnippet' => $lines
            ];
            
            extract($data);
            
            // Require the error template directly without using the View compiler 
            // to prevent loops if the error originated from the View compiler itself.
            $viewPath = App::basePath() . '/resource/view/system/error.php';
            if (file_exists($viewPath)) {
                require $viewPath;
            } else {
                echo "<h1>Fatal Error</h1><p>" . htmlspecialchars($exception->getMessage()) . "</p>";
            }
            
        } catch (\Throwable $e) {
            // Absolute fallback if everything else fails
            echo "<h1>Fatal Error</h1><p>" . htmlspecialchars($exception->getMessage()) . "</p>";
        }
    }
}
