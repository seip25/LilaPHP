<?php

namespace Models;

use Core\BaseModel;
use Core\Field;
use Core\FieldDatabase;

/**
 * User Model
 * 
 * Handles user authentication and account management.
 * 
 * @package Models
 */
class User extends BaseModel
{
    /**
     * Primary key
     * 
     * @var int
     */
    #[FieldDatabase(type: 'int', primaryKey: true, autoIncrement: true, unsigned: true)]
    protected int $id;

    /**
     * Record creation timestamp
     * 
     * @var string
     */
    #[FieldDatabase(type: 'datetime', default: 'CURRENT_TIMESTAMP', comment: 'Record creation timestamp')]
    protected string $created_at;

    /**
     * Record update timestamp
     * 
     * @var string|null
     */
    #[FieldDatabase(type: 'datetime', nullable: true, comment: 'Record update timestamp')]
    protected ?string $updated_at = null;

    /**
     * User email address
     * 
     * @var string
     */
    #[Field(required: true, format: 'email')]
    #[FieldDatabase(type: 'varchar', length: 255, unique: true, index: true, comment: 'User email address')]
    public string $email;

    /**
     * Hashed password
     * 
     * @var string
     */
    #[Field(required: true, min_length: 6)]
    #[FieldDatabase(type: 'varchar', length: 255, comment: 'Hashed password')]
    public string $password;

    /**
     * User full name
     * 
     * @var string|null
     */
    #[Field(required: false, max_length: 100)]
    #[FieldDatabase(type: 'varchar', length: 100, nullable: true, comment: 'User full name')]
    public ?string $name = null;

    /**
     * Account active status
     * 
     * @var bool
     */
    #[FieldDatabase(type: 'boolean', default: '1', comment: 'Account active status')]
    public bool $is_active = true;

    /**
     * Email verification status
     * 
     * @var bool
     */
    #[FieldDatabase(type: 'boolean', default: '0', comment: 'Email verified status')]
    public bool $email_verified = false;

    /**
     * Password reset token
     * 
     * @var string|null
     */
    #[FieldDatabase(type: 'varchar', length: 255, nullable: true, comment: 'Password reset token')]
    public ?string $reset_token = null;

    /**
     * Password reset token expiration
     * 
     * @var string|null
     */
    #[FieldDatabase(type: 'datetime', nullable: true, comment: 'Reset token expiration')]
    public ?string $reset_token_expires = null;

    /**
     * Remember me token for persistent login
     * 
     * @var string|null
     */
    #[FieldDatabase(type: 'varchar', length: 255, nullable: true, comment: 'Remember me token')]
    public ?string $remember_token = null;
}
