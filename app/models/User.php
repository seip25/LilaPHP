<?php

namespace Models;

use Core\BaseModel;
use Core\Field;
use Core\FieldDatabase;

/**
 * User Model
 * 
 * Example model demonstrating both validation (Field) and database schema (FieldDatabase) attributes.
 * 
 * @package Models
 */
class User extends BaseModel
{
    #[FieldDatabase(type: 'int', primaryKey: true, autoIncrement: true, unsigned: true)]
    protected int $id;

    #[FieldDatabase(type: 'datetime', default: 'CURRENT_TIMESTAMP', comment: 'Record creation timestamp')]
    protected string $created_at;

    #[FieldDatabase(type: 'datetime', nullable: true, comment: 'Record update timestamp')]
    protected ?string $updated_at;

    #[Field(required: true, format: 'email', messages: ['email' => 'Please provide a valid email address'])]
    #[FieldDatabase(type: 'varchar', length: 255, unique: true, index: true, comment: 'User email address')]
    public string $email;

    #[Field(required: true, min_length: 6)]
    #[FieldDatabase(type: 'varchar', length: 255, comment: 'Hashed password')]
    public string $password;

    #[Field(required: false, max_length: 100)]
    #[FieldDatabase(type: 'varchar', length: 100, nullable: true, comment: 'User full name')]
    public ?string $name;

    #[FieldDatabase(type: 'boolean', default: '1', comment: 'Account active status')]
    public bool $is_active = true;
}
