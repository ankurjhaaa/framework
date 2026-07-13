<?php

namespace Kite\Core;

/**
 * The Migration Engine.
 * Responsible for detecting changes in your Django-style models and generating SQL migration files,
 * as well as applying those migrations to the database.
 */
class Migrator
{
    /**
     * Generate migration files based on schema changes.
     * Reads `database/models.php`, compares it with the historical `.schema.json` file,
     * and generates the necessary `ALTER TABLE` or `CREATE TABLE` SQL statements.
     */
    public static function makeMigrations()
    {
        // 1. Ensure the models definition file exists
        $modelsFile = App::basePath() . '/database/models.php';
        if (!file_exists($modelsFile)) {
            echo "Error: database/models.php not found.\n";
            return;
        }

        require_once $modelsFile;
        
        // 2. Find all classes that extend the base Model class
        $classes = get_declared_classes();
        $models = [];
        foreach ($classes as $class) {
            if (is_subclass_of($class, Model::class)) {
                $models[] = $class;
            }
        }

        // 3. Extract the new (current) schema definitions from the Models
        $currentSchema = [];
        foreach ($models as $model) {
            $table = $model::tableName();
            $fields = $model::getSchema(); // Fetches user fields + auto-injected ID & Timestamps
            
            foreach ($fields as $name => $field) {
                // Convert Field objects to raw SQL definitions (e.g. "VARCHAR(255) NOT NULL")
                $currentSchema[$table][$name] = $field->toSql();
            }
        }

        // 4. Load the historical schema state to perform diffing
        $schemaFile = App::basePath() . '/database/migrations/.schema.json';
        $previousSchema = [];
        if (file_exists($schemaFile)) {
            $previousSchema = json_decode(file_get_contents($schemaFile), true) ?: [];
        }

        // 5. If there is no difference, exit early. We don't want to create empty migrations.
        if ($currentSchema === $previousSchema) {
            echo "No changes detected.\n";
            return;
        }

        $sqlStatements = [];
        $actionNames = [];

        // 6. Diffing Logic: Check for New Tables and Added/Modified/Deleted Columns
        foreach ($currentSchema as $table => $columns) {
            
            if (!isset($previousSchema[$table])) {
                // SCENARIO: A completely new model was created
                $cols = [];
                foreach ($columns as $name => $sqlDef) {
                    $cols[] = "`{$name}` {$sqlDef}";
                }
                
                // Build a standard CREATE TABLE statement
                $sqlStatements[] = "CREATE TABLE IF NOT EXISTS `{$table}` (\n    " . implode(",\n    ", $cols) . "\n);";
                $actionNames[] = "create_{$table}_table";
                
            } else {
                // SCENARIO: The table already exists. We need to check column by column.
                $tableAction = '';
                
                foreach ($columns as $name => $sqlDef) {
                    if (!isset($previousSchema[$table][$name])) {
                        // A new column was added to the model
                        $sqlStatements[] = "ALTER TABLE `{$table}` ADD COLUMN `{$name}` {$sqlDef};";
                        if (!$tableAction) $tableAction = "add_{$name}_to_{$table}";
                        
                    } elseif ($previousSchema[$table][$name] !== $sqlDef) {
                        // An existing column's definition was modified (e.g., max_length changed)
                        $sqlStatements[] = "ALTER TABLE `{$table}` MODIFY COLUMN `{$name}` {$sqlDef};";
                        if (!$tableAction) $tableAction = "alter_{$name}_in_{$table}";
                    }
                }
                
                // Check if any old columns were deleted from the model
                foreach ($previousSchema[$table] as $name => $sqlDef) {
                    if (!isset($columns[$name])) {
                        $sqlStatements[] = "ALTER TABLE `{$table}` DROP COLUMN `{$name}`;";
                        if (!$tableAction) $tableAction = "drop_{$name}_from_{$table}";
                    }
                }
                
                // Track actions for descriptive filename generation
                if ($tableAction) {
                    $actionNames[] = $tableAction;
                }
            }
        }

        // 7. Check for Deleted Models/Tables
        foreach ($previousSchema as $table => $columns) {
            if (!isset($currentSchema[$table])) {
                $sqlStatements[] = "DROP TABLE IF EXISTS `{$table}`;";
                $actionNames[] = "drop_{$table}_table";
            }
        }

        // If somehow logic slipped, ensure we have statements
        if (empty($sqlStatements)) {
            echo "No changes detected.\n";
            return;
        }

        // 8. Generate a smart, readable suffix for the migration filename
        if (count($actionNames) === 1) {
            $suffix = '_' . $actionNames[0]; // e.g., _add_phone_to_users
        } elseif (count($actionNames) > 1) {
            $suffix = '_multiple_schema_changes'; // Avoids extremely long filenames
        } else {
            $suffix = '_schema';
        }

        // Generate the final filename timestamp
        $migrationName = date('Ymd_His') . $suffix . '.php';
        $migrationDir = App::basePath() . '/database/migrations';
        
        if (!is_dir($migrationDir)) {
            mkdir($migrationDir, 0755, true);
        }
        
        $migrationFile = $migrationDir . '/' . $migrationName;
        
        // 9. Write the SQL statements into the PHP migration array
        $content = "<?php\n\n// Auto-generated Smart Migration\n\nreturn [\n";
        foreach ($sqlStatements as $sql) {
            // Escape quotes and variables so PHP syntax remains valid
            $escapedSql = addcslashes($sql, '"\\$');
            $content .= "    \"{$escapedSql}\",\n";
        }
        $content .= "];\n";
        
        // Save the migration and update the schema history JSON
        file_put_contents($migrationFile, $content);
        file_put_contents($schemaFile, json_encode($currentSchema, JSON_PRETTY_PRINT));
        
        echo "Created migration: {$migrationName}\n";
    }

    /**
     * Executes all pending migration files against the live database.
     */
    public static function migrate()
    {
        // 1. Establish database connection using PDO
        try {
            $db = Database::connect();
        } catch (\Exception $e) {
            echo "Database connection failed: " . $e->getMessage() . "\n";
            return;
        }
        
        // 2. Ensure the framework's migration tracking table exists
        $db->exec("CREATE TABLE IF NOT EXISTS _migrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL UNIQUE,
            run_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        // 3. Fetch list of migrations that have already been executed
        $stmt = $db->query("SELECT migration FROM _migrations");
        $runMigrations = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        
        // 4. Find all migration files in the directory and sort them alphabetically (by timestamp)
        $migrationFiles = glob(App::basePath() . '/database/migrations/*.php');
        sort($migrationFiles);
        
        $migratedAny = false;
        
        // 5. Loop through files and execute any that haven't been run yet
        foreach ($migrationFiles as $file) {
            $filename = basename($file);
            
            if (!in_array($filename, $runMigrations)) {
                echo "Applying migration: {$filename}...\n";
                
                // Get the array of SQL queries from the file
                $queries = require $file;
                
                // Execute each query
                foreach ($queries as $query) {
                    $db->exec($query);
                }
                
                // Record the successful migration in the tracking table
                $stmt = $db->prepare("INSERT INTO _migrations (migration) VALUES (?)");
                $stmt->execute([$filename]);
                
                $migratedAny = true;
                echo "Successfully applied: {$filename}\n";
            }
        }
        
        if (!$migratedAny) {
            echo "No pending migrations found.\n";
        }
    }
}
