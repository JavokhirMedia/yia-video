<?php
// src/Helpers/Validator.php

namespace App\Helpers;

class Validator
{
    private array $errors = [];

    public function validate(array $data, array $rules): bool
    {
        $this->errors = [];
        
        foreach ($rules as $field => $fieldRules) {
            if (!is_array($fieldRules)) {
                $fieldRules = explode('|', $fieldRules);
            }
            
            foreach ($fieldRules as $rule) {
                // Parse rule with parameters
                $parameters = [];
                if (strpos($rule, ':') !== false) {
                    [$rule, $parameterString] = explode(':', $rule, 2);
                    $parameters = explode(',', $parameterString);
                }
                
                $methodName = 'validate' . ucfirst($rule);
                
                if (method_exists($this, $methodName)) {
                    $value = $data[$field] ?? null;
                    if (!$this->$methodName($field, $value, $parameters)) {
                        break; // Stop validating this field after first error
                    }
                }
            }
        }
        
        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    private function validateRequired(string $field, $value, array $parameters): bool
    {
        if ($value === null || $value === '') {
            $this->errors[$field][] = "The {$field} field is required.";
            return false;
        }
        
        return true;
    }

    private function validateEmail(string $field, $value, array $parameters): bool
    {
        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field][] = "The {$field} must be a valid email address.";
            return false;
        }
        
        return true;
    }

    private function validatePhone(string $field, $value, array $parameters): bool
    {
        if ($value !== null && $value !== '' && !preg_match('/^\+?[0-9]{10,15}$/', $value)) {
            $this->errors[$field][] = "The {$field} must be a valid phone number.";
            return false;
        }
        
        return true;
    }

    private function validateMin(string $field, $value, array $parameters): bool
    {
        $min = (int) ($parameters[0] ?? 0);
        
        if ($value !== null && $value !== '' && (is_string($value) ? mb_strlen($value) : $value) < $min) {
            $this->errors[$field][] = "The {$field} must be at least {$min} " . (is_string($value) ? "characters" : "");
            return false;
        }
        
        return true;
    }

    private function validateMax(string $field, $value, array $parameters): bool
    {
        $max = (int) ($parameters[0] ?? 0);
        
        if ($value !== null && $value !== '' && (is_string($value) ? mb_strlen($value) : $value) > $max) {
            $this->errors[$field][] = "The {$field} must not exceed {$max} " . (is_string($value) ? "characters" : "");
            return false;
        }
        
        return true;
    }

    private function validateNumeric(string $field, $value, array $parameters): bool
    {
        if ($value !== null && $value !== '' && !is_numeric($value)) {
            $this->errors[$field][] = "The {$field} must be a number.";
            return false;
        }
        
        return true;
    }

    private function validateInteger(string $field, $value, array $parameters): bool
    {
        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_INT)) {
            $this->errors[$field][] = "The {$field} must be an integer.";
            return false;
        }
        
        return true;
    }
}
