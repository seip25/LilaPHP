<?php

namespace Cli;

/**
 * Model Generator Command
 * 
 * Generates model files with Field and FieldDatabase attributes for validation and database schema.
 * 
 * @package Cli
 */
class Model extends Command
{
    private string $modelsDir;

    public function __construct()
    {
        parent::__construct();
        $this->modelsDir = dirname(__DIR__) . '/models';

        if (!is_dir($this->modelsDir)) {
            mkdir($this->modelsDir, 0755, true);
        }
    }

    /**
     * Execute model command
     * 
     * @param array $args Command arguments
     * @return void
     */
    public function execute(array $args): void
    {
        $this->create($args);
    }

    /**
     * Create a new model file
     * 
     * @param array $args Command arguments
     * @return void
     */
    public function create(array $args): void
    {
        $name = $args[0] ?? 'GenericModel';
        $name = ucfirst($name);

        if (str_ends_with($name, 'Model')) {
            $name = substr($name, 0, -5);
        }

        $className = $name;
        $filename = $this->modelsDir . '/' . $className . '.php';

        if (file_exists($filename)) {
            $this->error("Model {$className} already exists!");
            return;
        }

        $template = $this->getModelTemplate($className);
        file_put_contents($filename, $template);

        $this->success("Created model: {$filename}");
        $this->info("Edit the file to customize your model fields.");
        $this->info("Documentation: https://seip25.github.io/LilaPHP/#cli_models");
    }

    /**
     * Get model template
     * 
     * @param string $name Model name
     * @return string Template content
     */
    private function getModelTemplate(string $name): string
    {
        return <<<PHP
<?php

namespace Models;

use Core\\BaseModel;
use Core\\Field;
use Core\\FieldDatabase;

/**
 * {$name} Model
 * 
 * This model demonstrates both validation (Field) and database schema (FieldDatabase) attributes.
 * 
 * Field Attribute:
 * - Used for validation when processing requests via middleware
 * - Supports: required, format, min_length, max_length, min, max, messages
 * - Enables automatic validation with translations
 * 
 * FieldDatabase Attribute:
 * - Used for automatic database migrations
 * - Defines the database schema for this model
 * - Supports all major database types (SQLite, MySQL, PostgreSQL)
 * 
 * Documentation: https://seip25.github.io/LilaPHP/#cli_models
 * 
 * @package Models
 */
class {$name} extends BaseModel
{
    /**
     * Primary key with auto-increment
     * 
     * @var int
     */
    #[FieldDatabase(type: 'int', primaryKey: true, autoIncrement: true, unsigned: true)]
    protected int \$id;

    /**
     * Record creation timestamp
     * 
     * @var string
     */
    #[FieldDatabase(type: 'datetime', default: 'CURRENT_TIMESTAMP', comment: 'Record creation timestamp')]
    protected string \$created_at;

    /**
     * Record update timestamp (nullable)
     * 
     * @var string|null
     */
    #[FieldDatabase(type: 'datetime', nullable: true, comment: 'Record update timestamp')]
    protected ?string \$updated_at = null;

    // ========================================
    // EXAMPLE FIELDS - Customize as needed
    // ========================================

    /**
     * Example: String field with validation and database schema
     * 
     * @var string
     */
    // #[Field(required: true, min_length: 3, max_length: 100)]
    // #[FieldDatabase(type: 'varchar', length: 100, nullable: false, comment: 'Example string field')]
    // public string \$name;

    /**
     * Example: Email field with format validation
     * 
     * @var string
     */
    // #[Field(required: true, format: 'email', messages: ['email' => 'Please provide a valid email'])]
    // #[FieldDatabase(type: 'varchar', length: 255, unique: true, index: true, comment: 'Email address')]
    // public string \$email;

    /**
     * Example: Integer field with min/max validation
     * 
     * @var int
     */
    // #[Field(required: false, min: 0, max: 150)]
    // #[FieldDatabase(type: 'int', unsigned: true, nullable: true, comment: 'Age in years')]
    // public ?int \$age;

    /**
     * Example: Decimal field for prices
     * 
     * @var float
     */
    // #[FieldDatabase(type: 'decimal', length: 10, decimals: 2, unsigned: true, comment: 'Price amount')]
    // public float \$price;

    /**
     * Example: Boolean field
     * 
     * @var bool
     */
    // #[FieldDatabase(type: 'boolean', default: '1', comment: 'Active status')]
    // public bool \$is_active = true;

    /**
     * Example: Text field for long content
     * 
     * @var string|null
     */
    // #[Field(required: false, max_length: 5000)]
    // #[FieldDatabase(type: 'text', nullable: true, comment: 'Description or notes')]
    // public ?string \$description;

    /**
     * Example: JSON field
     * 
     * @var string|null
     */
    // #[FieldDatabase(type: 'json', nullable: true, comment: 'JSON metadata')]
    // public ?string \$metadata;

    // ========================================
    // AVAILABLE FIELD TYPES
    // ========================================
    
    /**
     * Numeric Types:
     * - int, bigint, smallint, tinyint
     * - decimal (with length and decimals parameters)
     * - float, double
     * 
     * String Types:
     * - varchar (requires length parameter)
     * - char (requires length parameter)
     * - text, mediumtext, longtext
     * 
     * Date/Time Types:
     * - date, datetime, timestamp, time
     * 
     * Other Types:
     * - boolean (stored as tinyint(1))
     * - json
     * 
     * Field Attributes:
     * - type: Column type (required)
     * - length: Column length (for varchar/char)
     * - decimals: Decimal places (for decimal type)
     * - nullable: Allow NULL values (default: false)
     * - default: Default value (use 'CURRENT_TIMESTAMP' for timestamps)
     * - primaryKey: Mark as primary key
     * - autoIncrement: Enable auto-increment
     * - unique: Add unique constraint
     * - index: Create index
     * - unsigned: For numeric types (MySQL)
     * - comment: Column comment
     * 
     * Validation Attributes (Field):
     * - required: Field is required
     * - format: Validation format (email, url, etc.)
     * - min_length: Minimum string length
     * - max_length: Maximum string length
     * - min: Minimum numeric value
     * - max: Maximum numeric value
     * - messages: Custom validation messages with translations
     */
}

PHP;
    }
}
