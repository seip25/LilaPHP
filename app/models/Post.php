<?php

namespace Models;

use Core\BaseModel;
use Core\Field;
use Core\FieldDatabase;

/**
 * Post Model
 * 
 * Example model for blog posts.
 * 
 * @package Models
 */
class Post extends BaseModel
{
    #[FieldDatabase(type: 'bigint', primaryKey: true, autoIncrement: true, unsigned: true)]
    protected int $id;

    #[Field(required: true, min_length: 3, max_length: 255)]
    #[FieldDatabase(type: 'varchar', length: 255, comment: 'Post title')]
    public string $title;

    #[Field(required: true, min_length: 10)]
    #[FieldDatabase(type: 'text', comment: 'Post content')]
    public string $content;

    #[Field(required: false, max_length: 100)]
    #[FieldDatabase(type: 'varchar', length: 100, nullable: true, comment: 'Post slug for URL')]
    public ?string $slug;

    #[FieldDatabase(type: 'int', unsigned: true, nullable: true, comment: 'Author user ID')]
    public ?int $user_id;

    #[FieldDatabase(type: 'boolean', default: '0', comment: 'Published status')]
    public bool $is_published = false;

    #[FieldDatabase(type: 'datetime', default: 'CURRENT_TIMESTAMP')]
    protected string $created_at;

    #[FieldDatabase(type: 'datetime', nullable: true)]
    protected ?string $updated_at;

    #[FieldDatabase(type: 'datetime', nullable: true, comment: 'Publication date')]
    public ?string $published_at;
}
