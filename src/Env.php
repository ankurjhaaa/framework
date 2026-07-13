<?php

namespace Kite\Core;

/**
 * Extremely lightweight .env parser.
 * Reads KEY=VALUE pairs and loads them into $_ENV and putenv().
 */
class Env
{
    /**
     * Load variables from a .env file.
     *
     * @param string $path Path to the .env file.
     */
    public static function load(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            // skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // Split by the first equal sign
            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            list($name, $value) = $parts;
            $name = trim($name);
            $value = trim($value);

            // remove quotes if any
            $value = trim($value, "\"'");

            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }

    /**
     * Get an environment variable.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        $value = getenv($key);

        if ($value === false) {
            return $default;
        }

        switch (strtolower($value)) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'empty':
            case '(empty)':
                return '';
            case 'null':
            case '(null)':
                return null;
        }

        return $value;
    }
}
