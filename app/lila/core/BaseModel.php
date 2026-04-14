<?php

namespace Core;

use Attribute;
use ReflectionProperty;
use Throwable;
use Core\Config;
use Core\Database;
use PDO;

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
 * Relation Attributes
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class HasMany
{
    public function __construct(
        public string $model,
        public string $foreignKey,
        public string $localKey = 'id'
    ) {
    }
}

#[Attribute(Attribute::TARGET_PROPERTY)]
class HasOne
{
    public function __construct(
        public string $model,
        public string $foreignKey,
        public string $localKey = 'id'
    ) {
    }
}

#[Attribute(Attribute::TARGET_PROPERTY)]
class BelongsTo
{
    public function __construct(
        public string $model,
        public string $foreignKey,
        public string $ownerKey = 'id'
    ) {
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
    protected static array $i18nCache = [];
    protected static array $metadataCache = [];
    private string $lang;

    public function __construct(array $data = [], string|null $lang = null, bool $jsonResponse = true)
    {

        $this->lang = in_array(needle: $lang, haystack: ['eng', 'esp', 'bra', 'por']) ? $lang : "eng";

        if (!isset(self::$i18nCache[$this->lang])) {
            $messagesFile = Config::$DIR_PROJECT . "/locales/validation_{$this->lang}.php";
            self::$i18nCache[$this->lang] = file_exists($messagesFile) ? require $messagesFile : [];
        }
        $this->messages = self::$i18nCache[$this->lang];

        $ref = new \ReflectionClass($this);
        $errors = [];
        $validatedValues = [];

        foreach ($ref->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
            $name = $prop->getName();
            $attrs = $prop->getAttributes(Field::class);
            $field = !empty($attrs) ? $attrs[0]->newInstance() : null;

            $hasData = array_key_exists($name, $data);
            if (!$hasData && ($field === null || $field->default === null)) {
                continue;
            }

            $value = $hasData ? $data[$name] : $field->default;

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

        foreach ($validatedValues as $name => $value) {
            $prop = $ref->getProperty($name);
            $prop->setValue($this, $value);
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

        $snakeCase = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $className));

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
        $className = static::class;
        if (isset(self::$metadataCache[$className]['schema'])) {
            return self::$metadataCache[$className]['schema'];
        }

        $cacheFile = Config::$DIR_PROJECT . '/lila/model_cache.php';
        if (file_exists($cacheFile)) {
            $allCaches = require $cacheFile;
            if (isset($allCaches[$className]['schema'])) {
                self::$metadataCache[$className]['schema'] = $allCaches[$className]['schema'];
                return $allCaches[$className]['schema'];
            }
        }

        $ref = new \ReflectionClass($className);
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

        self::$metadataCache[$className]['schema'] = $schema;
        return $schema;
    }

    /**
     * Get all properties with FieldDatabase attribute
     * 
     * @return array Array of property names
     */
    public static function getDatabaseFields(): array
    {
        $className = static::class;
        if (isset(self::$metadataCache[$className]['fields'])) {
            return self::$metadataCache[$className]['fields'];
        }

        $cacheFile = Config::$DIR_PROJECT . '/lila/model_cache.php';
        if (file_exists($cacheFile)) {
            $allCaches = require $cacheFile;
            if (isset($allCaches[$className]['fields'])) {
                self::$metadataCache[$className]['fields'] = $allCaches[$className]['fields'];
                return $allCaches[$className]['fields'];
            }
        }

        $ref = new \ReflectionClass($className);
        $fields = [];

        foreach ($ref->getProperties() as $prop) {
            $attrs = $prop->getAttributes(FieldDatabase::class);

            if (!empty($attrs)) {
                $fields[] = $prop->getName();
            }
        }

        self::$metadataCache[$className]['fields'] = $fields;
        return $fields;
    }

    /**
     * Get database connection lazily
     * 
     * @return PDO
     */
    protected static function getDB(): PDO
    {
        static $db = null;
        if ($db === null) {
            $provider = Config::Env("DB_PROVIDER") ?? 'mysql';
            $host = Config::Env("DB_HOST") ?? 'localhost';
            $dbUser = Config::Env("DB_USER") ?? 'root';
            $dbPassword = Config::Env("DB_PASSWORD") ?? '';
            $dbName = Config::Env("DB_NAME") ?? '';
            $port = (int) (Config::Env("DB_PORT") ?? 3306);

            $database = new Database($provider, $host, $dbUser, $dbPassword, $dbName, $port);
            $db = $database->getConnection();
        }
        return $db;
    }

    /**
     * Find a record by primary key
     * 
     * @param int|string $id Primary key value
     * @param bool $activeOnly Whether to filter by is_active = 1
     * @param array $with Relations to eager load
     * @return static|null
     */
    public static function find(int|string $id, bool $activeOnly = true, array $with = []): ?static
    {
        $table = static::getTableName();
        $pk = 'id';
        $schema = static::getSchema();
        foreach ($schema as $col => $def) {
            if ($def['primaryKey']) {
                $pk = $col;
                break;
            }
        }

        $query = "SELECT * FROM {$table} WHERE {$pk} = :id";
        if ($activeOnly && isset($schema['is_active'])) {
            $query .= " AND is_active = 1";
        }
        $query .= " LIMIT 1";

        $stmt = static::getDB()->prepare($query);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $instance = clone (new \ReflectionClass(static::class))->newInstanceWithoutConstructor();
            foreach ($row as $key => $val) {
                if (property_exists($instance, $key)) {
                    $instance->$key = $val;
                }
            }
            if (!empty($with)) {
                $instance->loadRelations($with);
            }
            return $instance;
        }

        return null;
    }

    /**
     * Find records matching a condition
     * 
     * @param string $column The column name
     * @param string $operator The comparison operator
     * @param mixed $value The value to match
     * @param bool $activeOnly Whether to filter by is_active = 1
     * @param array $with Relations to eager load
     * @return static[] Array of populated models
     */
    public static function where(string $column, string $operator, mixed $value, bool $activeOnly = true, array $with = []): array
    {
        $table = static::getTableName();
        $schema = static::getSchema();

        $query = "SELECT * FROM {$table} WHERE {$column} {$operator} :val";
        if ($activeOnly && isset($schema['is_active'])) {
            $query .= " AND is_active = 1";
        }

        $stmt = static::getDB()->prepare($query);
        $stmt->execute(['val' => $value]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [];
        $ref = new \ReflectionClass(static::class);
        foreach ($rows as $row) {
            $instance = clone $ref->newInstanceWithoutConstructor();
            foreach ($row as $key => $val) {
                if (property_exists($instance, $key)) {
                    $instance->$key = $val;
                }
            }
            if (!empty($with)) {
                $instance->loadRelations($with);
            }
            $results[] = $instance;
        }
        return $results;
    }

    /**
     * Retrieve all records
     * 
     * @param bool $activeOnly Whether to filter by is_active = 1
     * @param array $with Relations to eager load
     * @return static[] Array of populated models
     */
    public static function all(bool $activeOnly = true, array $with = []): array
    {
        $table = static::getTableName();
        $schema = static::getSchema();

        $query = "SELECT * FROM {$table}";
        if ($activeOnly && isset($schema['is_active'])) {
            $query .= " WHERE is_active = 1";
        }
        $stmt = static::getDB()->query($query);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [];
        $ref = new \ReflectionClass(static::class);
        foreach ($rows as $row) {
            $instance = clone $ref->newInstanceWithoutConstructor();
            foreach ($row as $key => $val) {
                if (property_exists($instance, $key)) {
                    $instance->$key = $val;
                }
            }
            if (!empty($with)) {
                $instance->loadRelations($with);
            }
            $results[] = $instance;
        }
        return $results;
    }

    /**
     * Count total records
     * 
     * @param string $search Search query
     * @param array $searchColumns Columns to search in
     * @param bool $activeOnly Whether to filter by is_active = 1
     * @return int
     */
    public static function count(string $search = '', array $searchColumns = [], bool $activeOnly = true): int
    {
        $table = static::getTableName();
        $schema = static::getSchema();
        $sql = "SELECT COUNT(*) FROM {$table}";
        $wheres = [];
        $params = [];

        if ($activeOnly && isset($schema['is_active'])) {
            $wheres[] = "is_active = 1";
        }

        if (!empty($search) && !empty($searchColumns)) {
            $searchWheres = [];
            foreach ($searchColumns as $col) {
                $searchWheres[] = "{$col} LIKE :search_{$col}";
                $params["search_{$col}"] = "%{$search}%";
            }
            $wheres[] = "(" . implode(" OR ", $searchWheres) . ")";
        }

        if (!empty($wheres)) {
            $sql .= " WHERE " . implode(" AND ", $wheres);
        }

        $stmt = static::getDB()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Paginate records
     * 
     * @param int $page Current page
     * @param int $perPage Records per page
     * @param string $search Search query
     * @param array $searchColumns Columns to search in
     * @param bool $activeOnly Whether to filter by is_active = 1
     * @return static[]
     */
    public static function paginate(int $page = 1, int $perPage = 15, string $search = '', array $searchColumns = [], bool $activeOnly = true): array
    {
        $table = static::getTableName();
        $schema = static::getSchema();
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT * FROM {$table}";
        $wheres = [];
        $params = [];

        if ($activeOnly && isset($schema['is_active'])) {
            $wheres[] = "is_active = 1";
        }

        if (!empty($search) && !empty($searchColumns)) {
            $searchWheres = [];
            foreach ($searchColumns as $col) {
                $searchWheres[] = "{$col} LIKE :search_{$col}";
                $params["search_{$col}"] = "%{$search}%";
            }
            $wheres[] = "(" . implode(" OR ", $searchWheres) . ")";
        }

        if (!empty($wheres)) {
            $sql .= " WHERE " . implode(" AND ", $wheres);
        }

        $pk = 'id';
        foreach ($schema as $col => $def) {
            if ($def['primaryKey']) {
                $pk = $col;
                break;
            }
        }
        $sql .= " ORDER BY {$pk} DESC";
        $sql .= " LIMIT {$perPage} OFFSET {$offset}";

        $stmt = static::getDB()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [];
        $ref = new \ReflectionClass(static::class);
        foreach ($rows as $row) {
            $instance = clone $ref->newInstanceWithoutConstructor();
            foreach ($row as $key => $val) {
                if (property_exists($instance, $key)) {
                    $instance->$key = $val;
                }
            }
            $results[] = $instance;
        }
        return $results;
    }

    /**
     * Insert or update the record in the database
     * 
     * @return bool Success status
     */
    public function save(): bool
    {
        $table = static::getTableName();
        $pk = 'id';
        foreach (static::getSchema() as $col => $def) {
            if ($def['primaryKey']) {
                $pk = $col;
                break;
            }
        }

        $fields = static::getDatabaseFields();
        $data = [];
        foreach ($fields as $f) {
            if (isset($this->$f)) {
                $data[$f] = $this->$f;
            }
        }

        if (!empty($this->$pk) && $this->recordExistsInDB($table, $pk, $this->$pk)) {

            $set = [];
            foreach ($data as $k => $v) {
                if ($k === $pk)
                    continue;
                $set[] = "{$k} = :{$k}";
            }
            if (empty($set))
                return true;
            $setString = implode(', ', $set);
            $stmt = static::getDB()->prepare("UPDATE {$table} SET {$setString} WHERE {$pk} = :_pk");
            $data['_pk'] = $this->$pk;
            return $stmt->execute($data);
        } else {
            $cols = implode(', ', array_keys($data));
            $vals = implode(', ', array_map(fn($k) => ":{$k}", array_keys($data)));
            $stmt = static::getDB()->prepare("INSERT INTO {$table} ({$cols}) VALUES ({$vals})");
            $success = $stmt->execute($data);
            if ($success && static::getDB()->lastInsertId()) {
                $this->$pk = static::getDB()->lastInsertId();
            }
            return $success;
        }
    }

    /**
     * Delete the record from the database
     * 
     * @return bool Success status
     */
    public function delete(bool $logic = true): bool
    {
        $table = static::getTableName();
        $pk = 'id';
        foreach (static::getSchema() as $col => $def) {
            if ($def['primaryKey']) {
                $pk = $col;
                break;
            }
        }

        if (empty($this->$pk))
            return false;
        $q = $logic ?
            "UPDATE {$table} SET is_active = 0 WHERE {$pk} = :id"
            : "DELETE FROM {$table} WHERE {$pk} = :id";
        $stmt = static::getDB()->prepare($q);
        return $stmt->execute(['id' => $this->$pk]);
    }

    private function recordExistsInDB(string $table, string $pk, mixed $id): bool
    {
        $stmt = static::getDB()->prepare("SELECT {$pk} FROM {$table} WHERE {$pk} = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Load specified relationships into the current instance
     */
    public function loadRelations(array $relations): self
    {
        $ref = new \ReflectionClass(static::class);
        foreach ($relations as $rel) {
            if ($ref->hasProperty($rel)) {
                $prop = $ref->getProperty($rel);

                $hasMany = $prop->getAttributes(HasMany::class);
                if (!empty($hasMany)) {
                    $attr = $hasMany[0]->newInstance();
                    $relatedClass = $attr->model;
                    $fk = $attr->foreignKey;
                    $lk = $attr->localKey;
                    $this->$rel = $relatedClass::where($fk, '=', $this->$lk);
                    continue;
                }

                $hasOne = $prop->getAttributes(HasOne::class);
                if (!empty($hasOne)) {
                    $attr = $hasOne[0]->newInstance();
                    $relatedClass = $attr->model;
                    $fk = $attr->foreignKey;
                    $lk = $attr->localKey;
                    $results = $relatedClass::where($fk, '=', $this->$lk);
                    $this->$rel = !empty($results) ? $results[0] : null;
                    continue;
                }

                $belongsTo = $prop->getAttributes(BelongsTo::class);
                if (!empty($belongsTo)) {
                    $attr = $belongsTo[0]->newInstance();
                    $relatedClass = $attr->model;
                    $fk = $attr->foreignKey;
                    $ownerKey = $attr->ownerKey;
                    $this->$rel = $relatedClass::where($ownerKey, '=', $this->$fk);
                    if (!empty($this->$rel)) {
                        $this->$rel = $this->$rel[0];
                    } else {
                        $this->$rel = null;
                    }
                    continue;
                }
            }
        }
        return $this;
    }
}
