<?php

namespace Models;

/**
 * Product API Model.
 * 
 * @package Models
 */
class Product extends BaseModel
{
    protected string $table = 'products';

    public ?int $id = null;
    public string $name = '';
    public ?string $created_at = null;
    public ?string $updated_at = null;

    protected array $rules = [
        'name' => 'required|min_length:2|max_length:100'
    ];

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