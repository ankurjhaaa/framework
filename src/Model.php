<?php

namespace Kite\Core;

use Kite\Core\Schema\Field;

/**
 * Base Model Class.
 * All your application models in `database/models.php` must extend this class.
 * It provides Django-style schema definitions and ties the model to the QueryBuilder.
 */
abstract class Model
{
    /**
     * Magic getter for lazy-loading relationships.
     * When accessing a non-existent property like `$user->posts`, this method checks
     * if a `posts()` method exists, executes it, and returns the related models.
     */
    public function __get(string $key)
    {
        if (method_exists($this, $key)) {
            return $this->$key();
        }
        return null;
    }

    /**
     * Get the database table name associated with the model.
     * Automatically attempts to pluralize the class name (e.g., User -> users, Post -> posts).
     * Override this method in your subclass if you want a custom table name.
     */
    public static function tableName(): string
    {
        $path = explode('\\', static::class);
        $name = strtolower(array_pop($path)); // Get the class name without the namespace

        // Simple pluralization (append 's' unless it already ends in 's')
        if (substr($name, -1) !== 's') {
            $name .= 's';
        }

        return $name;
    }

    /**
     * Fetch the complete schema definition for this model.
     * It reads the developer's custom fields, and automatically injects an 'id', 
     * 'created_at', and 'updated_at' field if they weren't explicitly provided.
     */
    public static function getSchema(): array
    {
        $fields = static::fields(); // Call the subclass's fields() method

        // Automatically inject Primary Key ID if missing
        if (!isset($fields['id'])) {
            $fields = array_merge(
                ['id' => Field::integer(['primary_key' => true, 'auto_increment' => true])],
                $fields
            );
        }

        // Automatically inject created_at timestamp if missing
        if (!isset($fields['created_at'])) {
            $fields['created_at'] = Field::timestamp(['default' => 'CURRENT_TIMESTAMP']);
        }

        // Automatically inject updated_at timestamp if missing
        if (!isset($fields['updated_at'])) {
            $fields['updated_at'] = Field::timestamp(['default' => 'CURRENT_TIMESTAMP', 'nullable' => true]);
        }

        return $fields;
    }

    /**
     * Define the schema fields for this model.
     * Must be implemented by the subclass returning an array of Field objects.
     */
    abstract public static function fields(): array;

    /**
     * Get a fresh QueryBuilder instance bound to this model's table.
     * This acts as the entry point for running database queries.
     * 
     * Usage Example: `User::objects()->where('age', '>', 18)->get();`
     */
    public static function objects(): QueryBuilder
    {
        return new QueryBuilder(Database::connect(), static::tableName(), static::class);
    }

    /**
     * Defines a one-to-one relationship.
     */
    protected function hasOne(string $relatedClass, string $foreignKey)
    {
        return $relatedClass::objects()->where($foreignKey, $this->id)->first();
    }

    /**
     * Defines a one-to-many relationship.
     */
    protected function hasMany(string $relatedClass, string $foreignKey)
    {
        return $relatedClass::objects()->where($foreignKey, $this->id)->get();
    }

    /**
     * Defines an inverse one-to-one or many-to-one relationship.
     */
    protected function belongsTo(string $relatedClass, string $foreignKey)
    {
        return $relatedClass::objects()->where('id', $this->$foreignKey)->first();
    }
}
