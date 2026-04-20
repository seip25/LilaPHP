<?php

namespace Models;

use Core\BaseModel;
use Core\FieldDatabase;

/**
 * Job Model
 * 
 * Manages background queue jobs for LilaPHP Worker.
 * 
 * @package Models
 */
class JobModel extends BaseModel
{
    #[FieldDatabase(type: 'int', primaryKey: true, autoIncrement: true, unsigned: true)]
    protected int $id;

    #[FieldDatabase(type: 'varchar', length: 255, comment: 'The class name of the job handler')]
    public string $handler;

    #[FieldDatabase(type: 'text', comment: 'JSON encoded payload for the job')]
    public string $payload;

    #[FieldDatabase(type: 'int', default: 0, unsigned: true, comment: 'Number of attempts')]
    public int $attempts;

    #[FieldDatabase(type: 'int', default: 0, unsigned: true, index: true, comment: 'Reserved state for preventing concurrent processing (0=Pending, 1=Processing, 2=Failed)')]
    public int $status = 0;

    #[FieldDatabase(type: 'text', nullable: true, comment: 'Error payload from last failure')]
    public ?string $error = null;

    #[FieldDatabase(type: 'datetime', default: 'CURRENT_TIMESTAMP')]
    protected string $created_at;

    #[FieldDatabase(type: 'datetime', nullable: true)]
    protected ?string $updated_at = null;
}
