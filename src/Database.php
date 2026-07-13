<?php

namespace Kite\Core;

use PDO;
use PDOException;

/**
 * The Database Wrapper.
 * Manages the connection to the database using PHP's native PDO extension.
 * It implements the Singleton pattern to ensure only one connection is open at a time.
 */
class Database
{
    /**
     * Holds the active PDO connection instance.
     */
    protected static ?PDO $connection = null;

    /**
     * Create or retrieve the database connection.
     * Uses environment variables defined in `.env` to configure the connection.
     * 
     * @return PDO The active PDO connection
     */
    public static function connect(): PDO
    {
        // If a connection already exists, return it immediately to avoid reconnecting
        if (self::$connection !== null) {
            return self::$connection;
        }

        // Fetch connection credentials from the environment variables (with fallbacks)
        $connection = Env::get('DB_CONNECTION', 'mysql');
        $host       = Env::get('DB_HOST', '127.0.0.1');
        $port       = Env::get('DB_PORT', '3306');
        $database   = Env::get('DB_DATABASE', 'kitephp');
        $username   = Env::get('DB_USERNAME', 'root');
        $password   = Env::get('DB_PASSWORD', '');

        // Build the Data Source Name (DSN) string required by PDO
        $dsn = "{$connection}:host={$host};port={$port};dbname={$database};charset=utf8mb4";

        try {
            // Attempt to establish a new PDO connection
            self::$connection = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exceptions on SQL errors
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,         // Return results as anonymous objects
                PDO::ATTR_EMULATE_PREPARES   => false,                  // Use native database prepared statements
            ]);
        } catch (PDOException $e) {
            // If connection fails, halt the application and display a 500 error
            abort(500, "Database Connection Error: " . $e->getMessage());
        }

        return self::$connection;
    }

    /**
     * Start a new query on the specified table.
     * 
     * @param string $table The name of the database table (e.g., 'users')
     * @return QueryBuilder A fluent query builder instance
     */
    public static function table(string $table): QueryBuilder
    {
        return new QueryBuilder(self::connect(), $table);
    }
}
