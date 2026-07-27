<?php

namespace Models;

use Core\Database;
use Core\Validate;
use Core\Response;
use JsonSerializable;
use ReflectionClass;
use ReflectionProperty;

/**
 * Abstract Base Model for API entities.
 * 
 * Provides automated CRUD database interaction, schema introspection for migrations,
 * JSON serialization, and integrated validation (`Core\Validate`).
 * 
 * @package Models
 */
abstract class BaseModel implements JsonSerializable
{
    protected string $table = '';
    protected string $primaryKey = 'id';
    protected array $rules = [];
    protected array $attributes = [];

    /**
     * Initializes model instance with optional attribute data.
     * 
     * @param array $data Column-value associative array
     * @example $user = new \Models\User(['name' => 'Sara', 'email' => 'sara@example.com']);
     */
    public function __construct(array $data = [])
    {
        if ($this->table === '') {
            $ref = new ReflectionClass($this);
            $this->table = strtolower($ref->getShortName()) . 's';
        }

        $this->fill($data);
    }

    /**
     * Populates model attributes from an array.
     * 
     * @param array $data Associative data array
     * @return self
     * @example $model->fill($_POST);
     */
    public function fill(array $data): self
    {
        foreach ($data as $key => $value) {
            $this->attributes[$key] = $value;
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
        return $this;
    }

    /**
     * Magic getter for model attributes.
     * 
     * @param string $name Attribute name
     * @return mixed
     */
    public function __get(string $name): mixed
    {
        return $this->attributes[$name] ?? ($this->$name ?? null);
    }

    /**
     * Magic setter for model attributes.
     * 
     * @param string $name Attribute name
     * @param mixed $value Value to set
     */
    public function __set(string $name, mixed $value): void
    {
        $this->attributes[$name] = $value;
        if (property_exists($this, $name)) {
            $this->$name = $value;
        }
    }

    /**
     * Converts the model attributes into an associative array.
     * 
     * @return array
     * @example $array = $user->toArray();
     */
    public function toArray(): array
    {
        $data = $this->attributes;
        $ref = new ReflectionClass($this);
        foreach ($ref->getProperties(ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED) as $prop) {
            $name = $prop->getName();
            if ($name !== 'table' && $name !== 'primaryKey' && $name !== 'rules' && $name !== 'attributes') {
                $data[$name] = $prop->getValue($this);
            }
        }
        return $data;
    }

    /**
     * Serializes model directly to JSON format.
     * 
     * @return array
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Validates current model attributes against defined rules.
     * 
     * @return array<string, array<int, string>> Validation errors array (empty if valid)
     * @example if (!empty($user->validate())) { ... }
     */
    public function validate(): array
    {
        return Validate::check($this->toArray(), $this->rules);
    }

    /**
     * Validates and halts execution with JSON 422 error if rules fail.
     * 
     * @return void
     * @example $user->assertValid();
     */
    public function assertValid(): void
    {
        $errors = $this->validate();
        if (!empty($errors)) {
            Response::error('Model validation failure', 422, $errors);
        }
    }

    /**
     * Finds a record by its primary key ID.
     * 
     * @param int|string $id Primary key identifier
     * @return static|null
     * @example $user = \Models\User::find(1);
     */
    public static function find(int|string $id): ?static
    {
        $instance = new static();
        $sql = "SELECT * FROM `{$instance->table}` WHERE `{$instance->primaryKey}` = ? LIMIT 1";
        $row = Database::fetch($sql, [$id]);

        return $row !== null ? (new static($row)) : null;
    }

    /**
     * Retrieves all records from the model's table matching optional conditions.
     * 
     * @param string $where SQL WHERE condition (default: '1=1')
     * @param array $params Bound parameters array
     * @return static[]
     * @example $activeUsers = \Models\User::all('status = ?', ['active']);
     */
    public static function all(string $where = '1=1', array $params = []): array
    {
        $instance = new static();
        $sql = "SELECT * FROM `{$instance->table}` WHERE {$where}";
        $rows = Database::fetchAll($sql, $params);

        return array_map(fn($row) => new static($row), $rows);
    }

    /**
     * Saves (inserts or updates) the current model instance in the database.
     * 
     * @return bool True on success
     * @example $user->save();
     */
    public function save(): bool
    {
        $data = $this->toArray();
        unset($data['table'], $data['primaryKey'], $data['rules'], $data['attributes']);

        foreach (['created_at', 'updated_at'] as $tsField) {
            if (array_key_exists($tsField, $data) && $data[$tsField] === null) {
                unset($data[$tsField]);
            }
        }

        $pk = $this->primaryKey;
        $id = $data[$pk] ?? null;

        if ($id !== null && self::find($id) !== null) {
            unset($data[$pk]);
            $updated = Database::update($this->table, $data, "`{$pk}` = ?", [$id]);
            return $updated >= 0;
        }

        $insertedId = Database::insert($this->table, $data);
        if ($insertedId !== false && $insertedId !== '0') {
            $this->$pk = $insertedId;
            $this->attributes[$pk] = $insertedId;
            return true;
        }

        return $insertedId !== false;
    }

    /**
     * Paginates records from database with automatic URL query filtering & optional Redis caching.
     * 
     * Supported query parameters from Request / $_GET:
     * - `page`: Target page number (default: 1)
     * - `per_page`: Number of records per page (default: 15, max: 100)
     * - `sort`: Column to sort by (default: primaryKey)
     * - `order`: Sort order ('ASC' or 'DESC', default: 'DESC')
     * - `start_date` / `created_at_from`: Date range filtering
     * - `end_date` / `created_at_to`: Date range filtering
     * 
     * @param array $customFilters Custom key-value column equality filters
     * @param int $cacheTtl Seconds to cache results in Redis (0 = no caching)
     * @return array{data: static[], meta: array}
     * @example $result = \Models\Product::paginate(['status' => 'active'], 60);
     */
    public static function paginate(array $customFilters = [], int $cacheTtl = 0): array
    {
        $instance = new static();
        $table = $instance->table;
        $pk = $instance->primaryKey;

        $page = max(1, (int) (\Core\Request::input('page', 1)));
        $perPage = min(100, max(1, (int) (\Core\Request::input('per_page', 15))));
        $offset = ($page - 1) * $perPage;

        $sort = (string) \Core\Request::input('sort', $pk);
        $order = strtoupper((string) \Core\Request::input('order', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        $where = ['1=1'];
        $params = [];

        // Date range filtering
        $startDate = \Core\Request::input('start_date', \Core\Request::input('created_at_from'));
        $endDate = \Core\Request::input('end_date', \Core\Request::input('created_at_to'));

        if (!empty($startDate)) {
            $where[] = "`created_at` >= ?";
            $params[] = $startDate . (strlen($startDate) === 10 ? ' 00:00:00' : '');
        }

        if (!empty($endDate)) {
            $where[] = "`created_at` <= ?";
            $params[] = $endDate . (strlen($endDate) === 10 ? ' 23:59:59' : '');
        }

        // Custom column equality filters
        foreach ($customFilters as $col => $val) {
            if ($val !== null && $val !== '') {
                $where[] = "`{$col}` = ?";
                $params[] = $val;
            }
        }

        $whereClause = implode(' AND ', $where);
        $cacheKey = "paginate:{$table}:" . md5($whereClause . json_encode($params) . "{$sort}:{$order}:{$page}:{$perPage}");

        $executor = function() use ($table, $whereClause, $params, $sort, $order, $perPage, $offset, $page) {
            $countSql = "SELECT COUNT(*) as total FROM `{$table}` WHERE {$whereClause}";
            $countRow = Database::fetch($countSql, $params);
            $total = (int) ($countRow['total'] ?? 0);

            $dataSql = "SELECT * FROM `{$table}` WHERE {$whereClause} ORDER BY `{$sort}` {$order} LIMIT {$perPage} OFFSET {$offset}";
            $rows = Database::fetchAll($dataSql, $params);

            return [
                'data' => $rows,
                'meta' => [
                    'total' => $total,
                    'page' => $page,
                    'per_page' => $perPage,
                    'last_page' => (int) ceil($total / $perPage)
                ]
            ];
        };

        if ($cacheTtl > 0) {
            $raw = \Core\Cache::db($cacheKey, $executor, $cacheTtl);
        } else {
            $raw = $executor();
        }

        $raw['data'] = array_map(fn($row) => new static($row), $raw['data']);
        return $raw;
    }

    /**
     * Deletes the current record from the database.
     * 
     * @return bool True if deleted
     * @example $user->delete();
     */
    public function delete(): bool
    {
        $pk = $this->primaryKey;
        $id = $this->$pk ?? ($this->attributes[$pk] ?? null);
        if ($id === null) {
            return false;
        }

        return Database::delete($this->table, "`{$pk}` = ?", [$id]) > 0;
    }

    /**
     * Returns the table schema definition for database migration commands.
     * 
     * @return array<string, array> Column definition map
     * @example $schema = \Models\User::getSchema();
     */
    public static function getSchema(): array
    {
        return [
            'id' => [
                'type' => 'int',
                'unsigned' => true,
                'nullable' => false,
                'autoIncrement' => true,
                'primaryKey' => true
            ],
            'created_at' => [
                'type' => 'timestamp',
                'nullable' => false,
                'default' => 'CURRENT_TIMESTAMP'
            ],
            'updated_at' => [
                'type' => 'timestamp',
                'nullable' => false,
                'default' => 'CURRENT_TIMESTAMP'
            ]
        ];
    }
}
