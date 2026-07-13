<?php

namespace Kite\Core\Schema;

class Field
{
    public string $type;
    public array $options;

    public function __construct(string $type, array $options = [])
    {
        $this->type = $type;
        $this->options = $options;
    }

    public static function integer(array $options = []): self
    {
        return new self('integer', $options);
    }

    public static function foreignId(string $table, string $column = 'id', array $options = []): self
    {
        $options['foreign_table'] = $table;
        $options['foreign_column'] = $column;
        return new self('foreign', $options);
    }

    public static function string(array $options = []): self
    {
        return new self('string', $options);
    }

    public static function text(array $options = []): self
    {
        return new self('text', $options);
    }

    public static function timestamp(array $options = []): self
    {
        return new self('timestamp', $options);
    }

    public function toSql(): string
    {
        $sql = '';

        if ($this->type === 'integer' || $this->type === 'foreign') {
            $sql = 'INT';
        } elseif ($this->type === 'string') {
            $len = $this->options['max_length'] ?? 255;
            $sql = "VARCHAR({$len})";
        } elseif ($this->type === 'text') {
            $sql = 'TEXT';
        } elseif ($this->type === 'timestamp') {
            $sql = 'TIMESTAMP';
        }

        if (empty($this->options['nullable'])) {
            $sql .= ' NOT NULL';
        }

        if (!empty($this->options['auto_increment'])) {
            $sql .= ' AUTO_INCREMENT';
        }

        if (!empty($this->options['primary_key'])) {
            $sql .= ' PRIMARY KEY';
        }

        if (!empty($this->options['unique'])) {
            $sql .= ' UNIQUE';
        }

        if (array_key_exists('default', $this->options)) {
            $default = $this->options['default'];
            if (strtoupper($default) === 'CURRENT_TIMESTAMP') {
                $sql .= ' DEFAULT CURRENT_TIMESTAMP';
            } else {
                $sql .= " DEFAULT '{$default}'";
            }
        }

        if ($this->type === 'foreign') {
            $table = $this->options['foreign_table'];
            $column = $this->options['foreign_column'];
            $onDelete = $this->options['onDelete'] ?? 'CASCADE';
            
            // Note: In SQLite or MySQL, FOREIGN KEY constraints are usually defined at the table level, 
            // but inline references are supported. E.g.: INT REFERENCES table(col) ON DELETE CASCADE
            $sql .= " REFERENCES {$table}({$column}) ON DELETE {$onDelete}";
        }

        return ltrim($sql);
    }
}
