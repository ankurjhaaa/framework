<?php

namespace Kite\Core;

/**
 * The Validator class.
 * Provides Laravel-style array validation.
 */
class Validator
{
    protected array $data;
    protected array $rules;
    protected array $errors = [];

    public function __construct(array $data, array $rules)
    {
        $this->data = $data;
        $this->rules = $rules;
    }

    public static function make(array $data, array $rules): self
    {
        $validator = new self($data, $rules);
        $validator->validate();
        return $validator;
    }

    public function fails(): bool
    {
        return !empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    protected function validate()
    {
        foreach ($this->rules as $field => $ruleset) {
            $rules = is_string($ruleset) ? explode('|', $ruleset) : $ruleset;
            
            $value = $this->data[$field] ?? null;

            foreach ($rules as $rule) {
                // If it's not required and empty, skip other rules
                if ($rule !== 'required' && empty($value) && !in_array('required', $rules)) {
                    continue;
                }

                $parameters = [];
                if (str_contains($rule, ':')) {
                    list($rule, $paramString) = explode(':', $rule, 2);
                    $parameters = explode(',', $paramString);
                }

                $method = 'validate' . ucfirst(strtolower($rule));
                if (method_exists($this, $method)) {
                    if (!$this->$method($field, $value, $parameters)) {
                        break; // Stop validating this field on first error
                    }
                }
            }
        }
    }

    protected function addError(string $field, string $message)
    {
        $this->errors[$field][] = $message;
    }

    protected function validateRequired($field, $value, $parameters): bool
    {
        if ($value === null || trim((string)$value) === '') {
            $this->addError($field, "The {$field} field is required.");
            return false;
        }
        return true;
    }

    protected function validateEmail($field, $value, $parameters): bool
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, "The {$field} must be a valid email address.");
            return false;
        }
        return true;
    }

    protected function validateMin($field, $value, $parameters): bool
    {
        $min = (int) $parameters[0];
        if (mb_strlen((string)$value) < $min) {
            $this->addError($field, "The {$field} must be at least {$min} characters.");
            return false;
        }
        return true;
    }

    protected function validateMax($field, $value, $parameters): bool
    {
        $max = (int) $parameters[0];
        if (mb_strlen((string)$value) > $max) {
            $this->addError($field, "The {$field} must not be greater than {$max} characters.");
            return false;
        }
        return true;
    }

    /**
     * unique:table,column,except_id
     */
    protected function validateUnique($field, $value, $parameters): bool
    {
        $table = $parameters[0];
        $column = $parameters[1] ?? $field;
        $exceptId = $parameters[2] ?? null;

        $query = db($table)->where($column, $value);
        if ($exceptId) {
            $query->where('id', '!=', $exceptId);
        }

        $count = count($query->get());
        if ($count > 0) {
            $this->addError($field, "The {$field} has already been taken.");
            return false;
        }
        return true;
    }
}
