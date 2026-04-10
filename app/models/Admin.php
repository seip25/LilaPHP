<?php

namespace Models;

use Core\BaseModel;
use Core\Field;
use Core\FieldDatabase;

/**
 * Admin Model
 * 
 * Handles administrative user accounts.
 * 
 * @package Models
 */
class Admin extends BaseModel
{
    /**
     * Primary key
     */
    #[FieldDatabase(type: 'int', primaryKey: true, autoIncrement: true, unsigned: true)]
    protected int $id;

    /**
     * Creation timestamp
     */
    #[FieldDatabase(type: 'datetime', default: 'CURRENT_TIMESTAMP')]
    protected string $created_at;

    /**
     * Admin username
     */
    #[Field(required: true, min_length: 3, max_length: 50)]
    #[FieldDatabase(type: 'varchar', length: 50, unique: true, index: true)]
    public string $username;

    /**
     * Hashed password
     */
    #[Field(required: true, min_length: 8)]
    #[FieldDatabase(type: 'varchar', length: 255)]
    public string $password;
}
