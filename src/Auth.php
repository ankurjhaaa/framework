<?php

namespace Kite\Core;

/**
 * Authentication Engine for KitePHP.
 * Handles user login, logout, password hashing, and session management.
 */
class Auth
{
    protected static ?object $user = null;

    /**
     * Attempt to authenticate a user using the provided credentials.
     * 
     * @param array $credentials e.g. ['email' => '...', 'password' => '...']
     * @param string $table The database table (default: 'users')
     * @return bool True on success, False on failure.
     */
    public static function attempt(array $credentials, string $table = 'users'): bool
    {
        $password = $credentials['password'] ?? '';
        unset($credentials['password']);

        // Build the query to find the user by the remaining credentials
        $query = db($table);
        foreach ($credentials as $key => $value) {
            $query->where($key, $value);
        }
        
        $user = $query->first();

        // If user exists and password matches the hash
        if ($user && password_verify($password, $user->password ?? '')) {
            self::login($user);
            return true;
        }

        return false;
    }

    /**
     * Log a specific user into the application.
     */
    public static function login(object $user): void
    {
        Session::instance()->put('auth_user_id', $user->id);
        self::$user = $user;
    }

    /**
     * Log the user out of the application.
     */
    public static function logout(): void
    {
        Session::instance()->forget('auth_user_id');
        self::$user = null;
    }

    /**
     * Check if the user is authenticated.
     */
    public static function check(): bool
    {
        return Session::instance()->has('auth_user_id');
    }

    /**
     * Get the currently authenticated user.
     * Caches the user object for the duration of the request.
     * 
     * @param string $table The database table to fetch from (default: 'users')
     */
    public static function user(string $table = 'users'): ?object
    {
        if (self::$user !== null) {
            return self::$user;
        }

        $id = Session::instance()->get('auth_user_id');

        if ($id) {
            self::$user = db($table)->where('id', $id)->first();
            return self::$user;
        }

        return null;
    }

    /**
     * Get the ID of the currently authenticated user.
     */
    public static function id()
    {
        return Session::instance()->get('auth_user_id');
    }
}
