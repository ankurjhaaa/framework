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

    // --- Role-Based Access Control (RBAC) ---

    protected static ?array $permissions = null;
    protected static ?array $groups = null;

    /**
     * Check if the authenticated user has a specific permission.
     * Django-style architecture.
     */
    public static function hasPerm(string $permission): bool
    {
        $user = self::user();
        if (!$user) return false;

        // Superusers have all permissions
        if (isset($user->is_superuser) && $user->is_superuser) return true;

        if (self::$permissions === null) {
            self::$permissions = self::fetchUserPermissions($user->id);
        }

        return in_array($permission, self::$permissions);
    }

    /**
     * Check if the authenticated user belongs to a specific group.
     */
    public static function inGroup(string $groupName): bool
    {
        $user = self::user();
        if (!$user) return false;

        // Superusers are considered part of all groups for access purposes
        if (isset($user->is_superuser) && $user->is_superuser) return true;

        if (self::$groups === null) {
            $groups = db('auth_groups')
                ->select('auth_groups.name')
                ->join('auth_user_groups', 'auth_user_groups.group_id = auth_groups.id')
                ->where('auth_user_groups.user_id', $user->id)
                ->get();
                
            self::$groups = [];
            foreach ($groups as $g) {
                self::$groups[] = $g->name;
            }
        }

        return in_array($groupName, self::$groups);
    }

    /**
     * Fetch all direct and group permissions for a user from the database.
     */
    protected static function fetchUserPermissions($userId): array
    {
        $perms = [];
        
        // 1. Direct user permissions
        $direct = db('auth_permissions')
            ->select('auth_permissions.codename')
            ->join('auth_user_permissions', 'auth_user_permissions.permission_id = auth_permissions.id')
            ->where('auth_user_permissions.user_id', $userId)
            ->get();
            
        foreach ($direct as $p) {
            $perms[] = $p->codename;
        }

        // 2. Group permissions
        $group = db('auth_permissions')
            ->select('auth_permissions.codename')
            ->join('auth_group_permissions', 'auth_group_permissions.permission_id = auth_permissions.id')
            ->join('auth_user_groups', 'auth_user_groups.group_id = auth_group_permissions.group_id')
            ->where('auth_user_groups.user_id', $userId)
            ->get();
            
        foreach ($group as $p) {
            $perms[] = $p->codename;
        }

        return array_unique($perms);
    }
}
