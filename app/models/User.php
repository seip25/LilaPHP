<?php

namespace Models;

/**
 * Example User API Model.
 * 
 * Demonstrates table definition, validation rules, JSON serialization, and migration schema.
 * 
 * @package Models
 */
class User extends BaseModel
{
    protected string $table = 'users';

    public ?int $id = null;
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $role = 'user';
    public ?string $created_at = null;
    public ?string $updated_at = null;
    public ?string $deleted_at = null;

    protected array $rules = [
        'name' => 'required|min_length:3|max_length:100',
        'email' => 'required|email|max_length:150',
        'password' => 'required|min_length:6',
        'role' => 'in:user,admin'
    ];

    /**
     * Excludes sensitive fields (like password) when serialized to JSON.
     * 
     * @return array
     * @example echo json_encode($user);
     */
    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();
        unset($data['password']);
        return $data;
    }

    /**
     * Returns MySQL table schema definition for migrations.
     * 
     * @return array<string, array>
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
            'name' => [
                'type' => 'string',
                'length' => 100,
                'nullable' => false
            ],
            'email' => [
                'type' => 'string',
                'length' => 150,
                'nullable' => false,
                'unique' => true
            ],
            'password' => [
                'type' => 'string',
                'length' => 255,
                'nullable' => false
            ],
            'role' => [
                'type' => 'string',
                'length' => 20,
                'nullable' => false,
                'default' => 'user'
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
            ],
            'deleted_at' => [
                'type' => 'timestamp',
                'nullable' => true,
                'default' => null
            ]
        ];
    }
}
