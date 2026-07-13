<?php

namespace Kite\Core;

/**
 * The Session Manager.
 * Safely handles PHP sessions and provides helper methods for flash messages and old input data.
 * Implements the Singleton pattern to ensure the session is only started once.
 */
class Session
{
    protected static ?self $instance = null;

    /**
     * Protected constructor to enforce Singleton pattern.
     * Automatically starts the PHP session if it hasn't been started yet.
     */
    protected function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            // Store session files safely in the storage directory instead of the system temp folder
            $savePath = App::basePath() . '/storage/session';
            if (is_dir($savePath)) {
                session_save_path($savePath);
            }
            session_start();
        }
    }

    /**
     * Retrieve the singleton instance of the Session class.
     */
    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get a value from the session (including flash messages).
     * Flash messages are automatically cleared upon retrieval.
     * 
     * @param string $key The session key
     * @param mixed $default The fallback value if the key doesn't exist
     */
    public static function get(string $key, $default = null)
    {
        self::instance();
        
        // Check flash messages first
        if (isset($_SESSION['_flash'][$key])) {
            $value = $_SESSION['_flash'][$key];
            unset($_SESSION['_flash'][$key]);
            return $value;
        }

        return $_SESSION[$key] ?? $default;
    }

    /**
     * Check if a key exists in the session or flash data.
     */
    public function has(string $key): bool
    {
        self::instance();
        return isset($_SESSION[$key]) || isset($_SESSION['_flash'][$key]);
    }

    /**
     * Store a permanent value in the session.
     */
    public function put(string $key, $value): void
    {
        self::instance();
        $_SESSION[$key] = $value;
    }

    /**
     * Store a "flash" message in the session.
     * Flash messages are typically only meant to survive for a single subsequent request 
     * (e.g., "Item saved successfully!").
     */
    public function flash(string $key, $value): void
    {
        self::instance();
        $_SESSION['_flash'][$key] = $value;
    }

    /**
     * Retrieve old form input data.
     * Useful when validation fails and you want to repopulate the form fields.
     */
    public function getOldInput(string $key, $default = '')
    {
        self::instance();
        return $_SESSION['_old_input'][$key] ?? $default;
    }
}
