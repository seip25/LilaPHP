<?php

namespace Core;

use Attribute;
use ReflectionProperty;
use Throwable;

/**
 * Database Field Attribute
 * 
 * Defines database schema properties for model fields. Used for automatic
 * table creation and migrations.
 * 
 * @package Core
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class FieldDatabase
{
    public string $type;
    public ?int $length;
    public bool $nullable;
    public mixed $default;
    public bool $primaryKey;
    public bool $autoIncrement;
    public bool $unique;
    public bool $index;
    public bool $unsigned;
    public ?string $comment;

    /**
     * Define database field properties
     * 
     * @param string $type Column type (int, varchar, text, datetime, etc.)
     * @param int|null $length Column length for varchar/char types
     * @param bool $nullable Whether column accepts NULL values
     * @param mixed $default Default value for the column
     * @param bool $primaryKey Mark as primary key
     * @param bool $autoIncrement Enable auto-increment
     * @param bool $unique Add unique constraint
     * @param bool $index Create index on this column
     * @param bool $unsigned For numeric types (MySQL)
     * @param string|null $comment Column comment
     */
    public function __construct(
        string $type = 'varchar',
        ?int $length = null,
        bool $nullable = false,
        mixed $default = null,
        bool $primaryKey = false,
        bool $autoIncrement = false,
        bool $unique = false,
        bool $index = false,
        bool $unsigned = false,
        ?string $comment = null
    ) {
        $this->type = $type;
        $this->length = $length;
        $this->nullable = $nullable;
        $this->default = $default;
        $this->primaryKey = $primaryKey;
        $this->autoIncrement = $autoIncrement;
        $this->unique = $unique;
        $this->index = $index;
        $this->unsigned = $unsigned;
        $this->comment = $comment;
    }
}

/**
 * Validation Field Attribute
 * 
 * Defines validation rules for model fields. Used for automatic
 * validation of user input.
 * 
 * @package Core
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Field
{
    public bool $required;
    public mixed $default;
    public ?int $min = null;
    public ?int $max = null;
    public ?int $min_length = null;
    public ?int $max_length = null;
    public ?int $length = null;
    public ?string $format = null;
    public ?string $pattern = null;
    public array $messages;

    public function __construct(
        bool $required = true,
        mixed $default = null,
        ?int $min = null,
        ?int $max = null,
        ?int $min_length = null,
        ?int $max_length = null,
        ?int $length = null,
        ?string $format = null,
        ?string $pattern = null,
        array $messages = []
    ) {
        $this->required = $required;
        $this->default = $default;
        $this->min = $min;
        $this->max = $max;
        $this->min_length = $min_length;
        $this->max_length = $max_length;
        $this->length = $length;
        $this->format = $format;
        $this->pattern = $pattern;
        $this->messages = $messages;
    }
}



/**
 * Base Model Class
 * 
 * Abstract base class for all models providing automatic validation
 * and database schema introspection for migrations.
 * 
 * @package Core
 */
abstract class BaseModel
{

    protected array $messages = [];

    private string $lang;

    public function __construct(array $data = [], string|null $lang = null, bool $jsonResponse = true)
    {

        $this->lang = in_array(needle: $lang, haystack: ['eng', 'esp', 'bra', 'por']) ? $lang : "eng";

        $messagesFile = dirname(__DIR__) . "/locales/validation_{$this->lang}.php";
        if (file_exists($messagesFile)) {
            $this->messages = require $messagesFile;
        }

        $ref = new \ReflectionClass($this);
        $errors = [];
        $validatedValues = [];

        foreach ($ref->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
            $name = $prop->getName();
            $attrs = $prop->getAttributes(Field::class);
            $field = $attrs[0]->newInstance() ?? null;

            $value = $data[$name] ?? ($field?->default ?? null);

            if ($field) {
                if ($field->required && ($value === null || $value === '')) {
                    $errors[$name][] = $this->formatMessage('required', $field, [':field' => $name]);
                    continue;
                }

                if ($value === null || $value === '') {
                    $validatedValues[$name] = $value;
                    continue;
                }

                if (is_string($value)) {
                    $strlen = strlen($value);

                    if ($field->length !== null && $strlen !== $field->length) {
                        $errors[$name][] = $this->formatMessage('length', $field, [
                            ':field' => $name,
                            ':length' => $field->length
                        ]);
                    }

                    if ($field->min_length !== null && $strlen < $field->min_length) {
                        $errors[$name][] = $this->formatMessage('min_length', $field, [
                            ':field' => $name,
                            ':min_length' => $field->min_length
                        ]);
                    }

                    if ($field->max_length !== null && $strlen > $field->max_length) {
                        $errors[$name][] = $this->formatMessage('max_length', $field, [
                            ':field' => $name,
                            ':max_length' => $field->max_length
                        ]);
                    }
                }

                if (is_numeric($value)) {
                    if ($field->min !== null && $value < $field->min) {
                        $errors[$name][] = $this->formatMessage('min', $field, [
                            ':field' => $name,
                            ':min' => $field->min
                        ]);
                    }
                    if ($field->max !== null && $value > $field->max) {
                        $errors[$name][] = $this->formatMessage('max', $field, [
                            ':field' => $name,
                            ':max' => $field->max
                        ]);
                    }
                }

                if ($field->format && $value !== null && $value !== '') {
                    switch ($field->format) {
                        case 'email':
                            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                                $errors[$name][] = $this->formatMessage('email', $field, [':field' => $name]);
                            }
                            break;
                        case 'ip':
                            if (!filter_var($value, FILTER_VALIDATE_IP)) {
                                $errors[$name][] = $this->formatMessage('ip', $field, [':field' => $name]);
                            }
                            break;
                        case 'url':
                            if (!filter_var($value, FILTER_VALIDATE_URL)) {
                                $errors[$name][] = $this->formatMessage('url', $field, [':field' => $name]);
                            }
                            break;
                        case 'uuid':
                            if (
                                !preg_match(
                                    '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
                                    $value
                                )
                            ) {
                                $errors[$name][] = $this->formatMessage('uuid', $field, [':field' => $name]);
                            }
                            break;
                        case 'number':
                            if (!is_numeric($value)) {
                                $errors[$name][] = $this->formatMessage('number', $field, [':field' => $name]);
                            }
                            break;
                        case 'integer':
                            if (!filter_var($value, FILTER_VALIDATE_INT)) {
                                $errors[$name][] = $this->formatMessage('integer', $field, [':field' => $name]);
                            }
                            break;
                        case 'float':
                            if (!filter_var($value, FILTER_VALIDATE_FLOAT)) {
                                $errors[$name][] = $this->formatMessage('float', $field, [':field' => $name]);
                            }
                            break;
                        case 'boolean':
                            if (!is_bool($value) && !in_array(strtolower($value), ['true', 'false', '1', '0', 'yes', 'no'])) {
                                $errors[$name][] = $this->formatMessage('boolean', $field, [':field' => $name]);
                            }
                            break;
                        case 'date':
                            if (!strtotime($value)) {
                                $errors[$name][] = $this->formatMessage('date', $field, [':field' => $name]);
                            }
                            break;
                        case 'datetime':
                            if (\DateTime::createFromFormat('Y-m-d H:i:s', $value) === false) {
                                $errors[$name][] = $this->formatMessage('datetime', $field, [':field' => $name]);
                            }
                            break;
                        case 'alpha':
                            if (!ctype_alpha($value)) {
                                $errors[$name][] = $this->formatMessage('alpha', $field, [':field' => $name]);
                            }
                            break;
                        case 'alphanumeric':
                            if (!ctype_alnum($value)) {
                                $errors[$name][] = $this->formatMessage('alphanumeric', $field, [':field' => $name]);
                            }
                            break;
                        case 'numeric':
                            if (!ctype_digit($value)) {
                                $errors[$name][] = $this->formatMessage('numeric', $field, [':field' => $name]);
                            }
                            break;
                        case 'phone':
                            if (!preg_match('/^\+?[0-9\s\-\(\)]{10,}$/', $value)) {
                                $errors[$name][] = $this->formatMessage('phone', $field, [':field' => $name]);
                            }
                            break;
                        case 'credit_card':
                            if (!$this->validateCreditCard($value)) {
                                $errors[$name][] = $this->formatMessage('credit_card', $field, [':field' => $name]);
                            }
                            break;
                        case 'domain':
                            if (!filter_var($value, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
                                $errors[$name][] = $this->formatMessage('domain', $field, [':field' => $name]);
                            }
                            break;
                        case 'mac_address':
                            if (!filter_var($value, FILTER_VALIDATE_MAC)) {
                                $errors[$name][] = $this->formatMessage('mac_address', $field, [':field' => $name]);
                            }
                            break;
                        case 'json':
                            if (!json_decode($value)) {
                                $errors[$name][] = $this->formatMessage('json', $field, [':field' => $name]);
                            }
                            break;
                        case 'base64':
                            if (!base64_decode($value, true)) {
                                $errors[$name][] = $this->formatMessage('base64', $field, [':field' => $name]);
                            }
                            break;
                        case 'regex':
                            if ($field->pattern && !preg_match($field->pattern, $value)) {
                                $errors[$name][] = $this->formatMessage('regex', $field, [':field' => $name]);
                            }
                            break;
                    }
                }
            }

            $validatedValues[$name] = $value;
        }

        if (!empty($errors)) {
            throw new ValidationException(errors: $errors, lang: $this->lang, jsonResponse: $jsonResponse);
        }

        foreach ($ref->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
            $prop->setValue($this, $validatedValues[$prop->getName()]);
        }
    }

    private function formatMessage(string $key, Field $field, array $vars): string
    {
        $msg = $field->messages[$key] ?? $this->messages[$key] ?? $key;
        foreach ($vars as $var => $val) {
            $msg = str_replace($var, $val, $msg);
        }
        return $msg;
    }

    private function validateCreditCard(string $number): bool
    {
        $number = preg_replace('/\D/', '', $number);

        $sum = 0;
        $reverse = strrev($number);

        for ($i = 0; $i < strlen($reverse); $i++) {
            $digit = (int) $reverse[$i];
            if ($i % 2 === 1) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            $sum += $digit;
        }

        return $sum % 10 === 0;
    }

    /**
     * Get table name from model class name
     * 
     * Converts class name to snake_case and pluralizes it.
     * Example: UserProfile -> user_profiles
     * 
     * @return string Table name
     */
    public static function getTableName(): string
    {
        $className = (new \ReflectionClass(static::class))->getShortName();

        // Convert PascalCase to snake_case
        $snakeCase = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $className));

        // Simple pluralization (add 's' if doesn't end with 's')
        if (!str_ends_with($snakeCase, 's')) {
            $snakeCase .= 's';
        }

        return $snakeCase;
    }

    /**
     * Get database schema from FieldDatabase attributes
     * 
     * Returns an array of column definitions for table creation.
     * 
     * @return array Array of column definitions
     * @example
     * ```php
     * [
     *     'id' => [
     *         'type' => 'int',
     *         'primaryKey' => true,
     *         'autoIncrement' => true
     *     ],
     *     'email' => [
     *         'type' => 'varchar',
     *         'length' => 255,
     *         'unique' => true
     *     ]
     * ]
     * ```
     */
    public static function getSchema(): array
    {
        $ref = new \ReflectionClass(static::class);
        $schema = [];

        foreach ($ref->getProperties() as $prop) {
            $attrs = $prop->getAttributes(FieldDatabase::class);

            if (empty($attrs)) {
                continue;
            }

            $fieldDb = $attrs[0]->newInstance();
            $name = $prop->getName();

            $schema[$name] = [
                'type' => $fieldDb->type,
                'length' => $fieldDb->length,
                'nullable' => $fieldDb->nullable,
                'default' => $fieldDb->default,
                'primaryKey' => $fieldDb->primaryKey,
                'autoIncrement' => $fieldDb->autoIncrement,
                'unique' => $fieldDb->unique,
                'index' => $fieldDb->index,
                'unsigned' => $fieldDb->unsigned,
                'comment' => $fieldDb->comment,
            ];
        }

        return $schema;
    }

    /**
     * Get all properties with FieldDatabase attribute
     * 
     * @return array Array of property names
     */
    public static function getDatabaseFields(): array
    {
        $ref = new \ReflectionClass(static::class);
        $fields = [];

        foreach ($ref->getProperties() as $prop) {
            $attrs = $prop->getAttributes(FieldDatabase::class);

            if (!empty($attrs)) {
                $fields[] = $prop->getName();
            }
        }

        return $fields;
    }
}
