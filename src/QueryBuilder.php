<?php

namespace Kite\Core;

use PDO;
use Kite\Core\Request;
use Kite\Core\Paginator;

/**
 * A lightweight, fluent SQL Query Builder.
 * Allows constructing complex SQL queries using simple PHP methods.
 * Prevents SQL injection by automatically using PDO Prepared Statements.
 */
class QueryBuilder
{
    // The active database connection
    protected PDO $pdo;
    
    // The target table name and optional model class for hydration
    protected string $table;
    protected ?string $modelClass = null;

    // Query building blocks
    protected array $select = ['*'];
    protected array $joins = [];
    protected array $wheres = [];
    protected array $bindings = []; // Holds values for prepared statement placeholders (?)
    protected string $orderBy = '';
    protected string $limit = '';

    /**
     * Initialize the builder with a PDO instance and a target table.
     */
    public function __construct(PDO $pdo, string $table, ?string $modelClass = null)
    {
        $this->pdo = $pdo;
        $this->table = $table;
        $this->modelClass = $modelClass;
    }

    /**
     * Specify which columns should be returned by the SELECT query.
     * e.g., ->select('id', 'name') or ->select(['id', 'name'])
     */
    public function select(...$columns): self
    {
        $this->select = is_array($columns[0]) ? $columns[0] : $columns;
        return $this;
    }

    /**
     * Add an INNER JOIN clause to the query.
     */
    public function join(string $table, string $condition, string $type = 'INNER'): self
    {
        $this->joins[] = "{$type} JOIN {$table} ON {$condition}";
        return $this;
    }

    /**
     * Add a LEFT JOIN clause to the query.
     */
    public function leftJoin(string $table, string $condition): self
    {
        return $this->join($table, $condition, 'LEFT');
    }

    /**
     * Add a RIGHT JOIN clause to the query.
     */
    public function rightJoin(string $table, string $condition): self
    {
        return $this->join($table, $condition, 'RIGHT');
    }

    /**
     * Add a WHERE condition to the query.
     * e.g., ->where('id', 5) OR ->where('age', '>', 18)
     */
    public function where(string $column, $operator, $value = null): self
    {
        // If only 2 arguments are passed, assume the operator is '='
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        // Store the condition and the binding value for the prepared statement
        $this->wheres[] = compact('column', 'operator', 'value');
        $this->bindings[] = $value;
        
        return $this;
    }

    /**
     * Add an ORDER BY clause.
     * e.g., ->orderBy('created_at', 'DESC')
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orderBy = " ORDER BY {$column} {$direction}";
        return $this;
    }

    /**
     * Limit the number of returned records.
     */
    public function limit(int $limit): self
    {
        $this->limit = " LIMIT {$limit}";
        return $this;
    }

    /**
     * Internally compiles the pieces into a valid raw SELECT SQL string.
     */
    protected function buildSelectQuery(): string
    {
        // Start building the query
        $query = "SELECT " . implode(', ', $this->select) . " FROM {$this->table}";

        // Add JOIN clauses if any exist
        if (!empty($this->joins)) {
            $query .= " " . implode(' ', $this->joins);
        }

        // Add WHERE clauses if any exist
        if (!empty($this->wheres)) {
            $conditions = [];
            foreach ($this->wheres as $where) {
                // Use '?' as a placeholder to prevent SQL injection
                $conditions[] = "{$where['column']} {$where['operator']} ?";
            }
            $query .= " WHERE " . implode(' AND ', $conditions);
        }

        // Append order and limit modifiers
        $query .= $this->orderBy . $this->limit;

        return $query;
    }

    /**
     * Execute the built SELECT query and return ALL matching rows.
     */
    public function get(): array
    {
        $stmt = $this->pdo->prepare($this->buildSelectQuery());
        
        if ($this->modelClass) {
            $stmt->setFetchMode(PDO::FETCH_CLASS, $this->modelClass);
        }
        
        $stmt->execute($this->bindings);
        return $stmt->fetchAll();
    }

    /**
     * Paginate the given query.
     * @param int $perPage The number of items to show per page
     */
    public function paginate(int $perPage = 15): Paginator
    {
        // 1. Get total count
        $countQuery = "SELECT COUNT(*) FROM {$this->table}";
        
        if (!empty($this->joins)) {
            $countQuery .= " " . implode(' ', $this->joins);
        }

        if (!empty($this->wheres)) {
            $conditions = [];
            foreach ($this->wheres as $where) {
                $conditions[] = "{$where['column']} {$where['operator']} ?";
            }
            $countQuery .= " WHERE " . implode(' AND ', $conditions);
        }
        $stmt = $this->pdo->prepare($countQuery);
        $stmt->execute($this->bindings);
        $total = (int) $stmt->fetchColumn();

        // 2. Get current page from the global request
        $request = Request::capture();
        $page = (int) $request->input('page', 1);
        if ($page < 1) $page = 1;

        // 3. Apply limit and offset to the main query builder
        $offset = ($page - 1) * $perPage;
        $this->limit = " LIMIT {$perPage} OFFSET {$offset}";

        // 4. Fetch the actual items
        $items = $this->get();

        // 5. Return a Paginator instance
        return new Paginator($items, $total, $perPage, $page, $request);
    }

    /**
     * Execute the SELECT query and return only the FIRST matching row.
     */
    public function first()
    {
        $this->limit(1);
        $stmt = $this->pdo->prepare($this->buildSelectQuery());
        
        if ($this->modelClass) {
            $stmt->setFetchMode(PDO::FETCH_CLASS, $this->modelClass);
        }
        
        $stmt->execute($this->bindings);
        return $stmt->fetch() ?: null;
    }

    /**
     * Insert a new record into the table.
     * @param array $data Associative array of column => value
     * @return int The ID of the newly inserted record
     */
    public function insert(array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($data), '?'); // Create '?' for every value

        // Build the INSERT query
        $query = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";

        $stmt = $this->pdo->prepare($query);
        $stmt->execute(array_values($data)); // Safely bind the values

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Update existing records matching the current WHERE conditions.
     * @param array $data Associative array of column => new_value
     * @return int Number of affected rows
     */
    public function update(array $data): int
    {
        $columns = array_keys($data);
        // Create "column = ?" strings
        $sets = array_map(fn($col) => "{$col} = ?", $columns);

        $query = "UPDATE {$this->table} SET " . implode(', ', $sets);
        
        $bindings = array_values($data); // Bindings for the SET clauses

        // Add WHERE conditions if present
        if (!empty($this->wheres)) {
            $conditions = [];
            foreach ($this->wheres as $where) {
                $conditions[] = "{$where['column']} {$where['operator']} ?";
            }
            $query .= " WHERE " . implode(' AND ', $conditions);
            
            // Merge SET bindings with WHERE bindings
            $bindings = array_merge($bindings, $this->bindings);
        }

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($bindings);

        return $stmt->rowCount();
    }

    /**
     * Delete records matching the current WHERE conditions.
     * @return int Number of affected rows
     */
    public function delete(): int
    {
        $query = "DELETE FROM {$this->table}";

        if (!empty($this->wheres)) {
            $conditions = [];
            foreach ($this->wheres as $where) {
                $conditions[] = "{$where['column']} {$where['operator']} ?";
            }
            $query .= " WHERE " . implode(' AND ', $conditions);
        }

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($this->bindings);

        return $stmt->rowCount();
    }
}
