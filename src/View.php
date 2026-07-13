<?php

namespace Kite\Core;

/**
 * The View Engine.
 * Responsible for rendering `.php` or `.kite.php` template files.
 * Provides a lightweight compiler for Kite syntax (similar to Laravel Blade).
 */
class View
{
    // Stores the active layout file path
    protected static string $layout = '';
    
    // Stores the captured HTML content for different sections
    protected static array $sections = [];
    
    // Tracks which section is currently being buffered
    protected static string $currentSection = '';

    /**
     * Render a view file with the given data.
     * 
     * @param string $view The view name (e.g., 'home' or 'admin.dashboard')
     * @param array $data Variables to pass to the view
     */
    public static function make(string $view, array $data = [])
    {
        // Extract array keys as variables into the current scope
        extract($data);

        // Find the absolute path to the view file (or compile it if it's a .kite.php file)
        $viewPath = self::resolvePath($view);

        // Start output buffering to capture the HTML instead of printing it immediately
        ob_start();
        require $viewPath;
        $content = ob_get_clean(); // Get the buffered HTML and clear the buffer

        // If the view specified a layout file using @extends('layout'), load the layout
        if (self::$layout !== '') {
            $layoutPath = self::resolvePath(self::$layout);
            self::$layout = ''; // Reset layout state for subsequent view renders
            
            if (file_exists($layoutPath)) {
                // Buffer the layout HTML
                ob_start();
                require $layoutPath;
                $content = ob_get_clean();
            }
        }

        // Echo the final compiled HTML to the browser
        echo $content;
    }

    /**
     * Resolves the view name to an absolute file path.
     * Prioritizes `.kite.php` files over standard `.php` files.
     */
    protected static function resolvePath(string $view): string
    {
        // Convert dot notation to directory slashes (e.g., admin.home -> admin/home)
        $base = App::basePath() . '/resource/view/' . str_replace('.', '/', $view);
        
        $kitePath = $base . '.kite.php';
        $phpPath = $base . '.php';

        // Check for Kite template engine file first
        if (file_exists($kitePath)) {
            // Compile the file into pure PHP and return the cached path
            return self::compile($kitePath);
        }

        // Fallback to standard PHP view
        if (file_exists($phpPath)) {
            return $phpPath;
        }

        // Throw an exception if neither file exists
        abort(500, "View [{$view}] not found.");
    }

    /**
     * Compiles a `.kite.php` file into raw PHP.
     * Uses Regex to replace `{{ }}` and `@if` with native PHP tags.
     * The compiled file is cached in storage to ensure maximum performance.
     */
    protected static function compile(string $path): string
    {
        $cacheDir = App::basePath() . '/storage/cache/views';
        
        // Ensure the cache directory exists
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        // Create a unique hash for the cached PHP file based on the original file path
        $compiledPath = $cacheDir . '/' . md5($path) . '.php';

        // Recompile ONLY if the cached file doesn't exist, OR if the original view file has been modified recently
        if (!file_exists($compiledPath) || filemtime($path) > filemtime($compiledPath)) {
            $content = file_get_contents($path);
            
            // Compile kite:data defaults to inject PHP variables
            $content = preg_replace_callback('/kite:data=["\']({.*?})["\']/', function($matches) {
                $code = '<?php ';
                $dataStr = trim($matches[1], '{}');
                $pairs = explode(',', $dataStr);
                foreach($pairs as $pair) {
                    $parts = explode(':', $pair, 2);
                    if(count($parts) == 2) {
                        $k = trim($parts[0], " '\"\n\r\t");
                        $v = trim($parts[1], " '\"\n\r\t");
                        if (strtolower($v) === 'true') $v = 'true';
                        elseif (strtolower($v) === 'false') $v = 'false';
                        elseif (!is_numeric($v)) $v = "'$v'";
                        $code .= "if (!isset(\$$k)) \$$k = $v; ";
                    }
                }
                $code .= '?>';
                return $code . $matches[0];
            }, $content);

            // Compile Reactive {{ $var }} to <kite-var> wrapper
            $content = preg_replace_callback('/(=\s*["\'][^"\'\>]*?)?{{\s*\$([a-zA-Z0-9_]+)\s*}}/', function($matches) {
                if (!empty($matches[1])) {
                    // It's inside an attribute, don't wrap
                    return $matches[1] . '<?php echo e($' . $matches[2] . '); ?>';
                }
                // Wrap it for client-side reactivity
                return '<kite-var data-key="' . $matches[2] . '"><?php echo e($' . $matches[2] . '); ?></kite-var>';
            }, $content);
            
            // Compile Normal {{ $expr }} to htmlspecialchars output to prevent XSS attacks
            $content = preg_replace('/{{\s*(.+?)\s*}}/', '<?php echo e($1); ?>', $content);
            
            // Compile {!! $var !!} to raw, unescaped output (Use with caution!)
            $content = preg_replace('/{!!\s*(.+?)\s*!!}/', '<?php echo $1; ?>', $content);
            
            // Compile Control Structures (If / Else)
            $content = preg_replace('/@if\s*\(((?:[^()]+|\((?1)\))*)\)/', '<?php if ($1): ?>', $content);
            $content = preg_replace('/@elseif\s*\(((?:[^()]+|\((?1)\))*)\)/', '<?php elseif ($1): ?>', $content);
            $content = preg_replace('/@else/', '<?php else: ?>', $content);
            $content = preg_replace('/@endif/', '<?php endif; ?>', $content);
            
            // Compile Loops (Foreach)
            $content = preg_replace('/@foreach\s*\(((?:[^()]+|\((?1)\))*)\)/', '<?php foreach ($1): ?>', $content);
            $content = preg_replace('/@endforeach/', '<?php endforeach; ?>', $content);

            // Compile Layout Directives
            $content = preg_replace('/@extends\s*\((.*?)\)/', '<?php \Kite\Core\View::layout($1); ?>', $content);
            $content = preg_replace('/@section\s*\((.*?)\)/', '<?php \Kite\Core\View::section($1); ?>', $content);
            $content = preg_replace('/@endsection/', '<?php \Kite\Core\View::endSection(); ?>', $content);
            $content = preg_replace('/@yield\s*\((.*?)\)/', '<?php \Kite\Core\View::yieldSection($1); ?>', $content);
            
            // Compile Error Directives
            $content = preg_replace('/@error\s*\((.*?)\)/', '<?php if ($message = errors($1)): ?>', $content);
            $content = preg_replace('/@enderror/', '<?php endif; ?>', $content);
            
            // Compile built-in helpers
            $content = preg_replace('/@csrf/', '<?php echo csrf(); ?>', $content);

            // Save the compiled pure PHP into the cache folder
            file_put_contents($compiledPath, $content);
        }

        return $compiledPath;
    }

    /**
     * Define the parent layout for the current view.
     */
    public static function layout(string $layout)
    {
        // Strip quotes passed by the regex matching
        self::$layout = trim($layout, "'\"");
    }

    /**
     * Start capturing HTML for a specific section (e.g., 'content').
     */
    public static function section(string $name)
    {
        self::$currentSection = trim($name, "'\"");
        ob_start(); // Start output buffering
    }

    /**
     * End capturing HTML for the current section and save it in the array.
     */
    public static function endSection()
    {
        if (self::$currentSection === '') {
            return;
        }

        // Get the buffered HTML and save it under the section name
        self::$sections[self::$currentSection] = ob_get_clean();
        self::$currentSection = ''; // Reset state
    }

    /**
     * Output a captured section inside a layout file.
     */
    public static function yieldSection(string $name, string $default = '')
    {
        $name = trim($name, "'\"");
        
        // Print the section HTML if it exists, otherwise print the default value
        echo self::$sections[$name] ?? $default;
    }

    /**
     * Include a reusable view component.
     */
    public static function component(string $view, array $data = [])
    {
        $viewPath = self::resolvePath($view);
        extract($data);
        require $viewPath;
    }
}
