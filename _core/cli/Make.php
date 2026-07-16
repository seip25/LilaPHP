<?php

namespace Cli;

/**
 * Code Scaffolding Generator Command (`php cli.php make:model` / `make:route`).
 * 
 * Generates boilerplate API Models or File-based Routes cleanly.
 * 
 * @package Cli
 */
class Make extends Command
{
    /**
     * Executes generation based on command type (`model` or `route`).
     * 
     * @param array $args Command arguments
     * @return int
     */
    public function run(array $args): int
    {
        $type = strtolower($args[0] ?? '');
        $name = trim($args[1] ?? '');

        if ($name === '') {
            $this->error("Please specify a name. Example: `php cli.php make model Product` or `php cli.php make route products/list`");
            return 1;
        }

        return match ($type) {
            'model' => $this->makeModel($name),
            'route' => $this->makeRoute($name),
            default => $this->printUsage()
        };
    }

    /**
     * Generates a new API Model file in `backend/models/`.
     * 
     * @param string $name Model class name
     * @return int
     */
    private function makeModel(string $name): int
    {
        $className = ucfirst(basename($name));
        $file = dirname(__DIR__, 2) . "/backend/models/{$className}.php";

        if (file_exists($file)) {
            $this->error("Model `{$className}` already exists at {$file}");
            return 1;
        }

        $table = strtolower($className) . 's';
        $code = <<<PHP
<?php

namespace Models;

/**
 * {$className} API Model.
 * 
 * @package Models
 */
class {$className} extends BaseModel
{
    protected string \$table = '{$table}';

    public ?int \$id = null;
    public string \$name = '';
    public ?string \$created_at = null;
    public ?string \$updated_at = null;

    protected array \$rules = [
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
PHP;

        file_put_contents($file, $code);
        $this->success("Created Model: `{$className}` -> `backend/models/{$className}.php`");
        return 0;
    }

    /**
     * Generates a file-based API route script in `backend/routes/`.
     * 
     * @param string $path Route file path (e.g., `products/list` or `orders`)
     * @return int
     */
    private function makeRoute(string $path): int
    {
        $cleanPath = trim($path, '/');
        $file = dirname(__DIR__, 2) . "/backend/routes/{$cleanPath}.php";
        $dir = dirname($file);

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        if (file_exists($file)) {
            $this->error("Route file already exists at {$file}");
            return 1;
        }

        $code = <<<PHP
<?php

/**
 * API Endpoint `/api/{$cleanPath}`
 */

use Core\Response;
use Core\Request;
use Core\Validate;

// Enforce allowed HTTP Methods (`GET`, `POST`, `PUT`, `DELETE`)
// Request::assertMethod('GET', 'POST');

// Sanitize & Validate Payload inputs
// \$clean = Validate::assert(\$_REQUEST, ['example_field' => 'required']);

Response::json([
    'status' => 'success',
    'endpoint' => '/api/{$cleanPath}',
    'method' => Request::getMethod(),
    'timestamp' => time()
]);
PHP;

        file_put_contents($file, $code);
        $this->success("Created API Route: `/api/{$cleanPath}` -> `backend/routes/{$cleanPath}.php`");
        return 0;
    }

    /**
     * Prints CLI help for code generator.
     * 
     * @return int
     */
    private function printUsage(): int
    {
        $this->error("Invalid make target.");
        echo "Usage:" . PHP_EOL;
        echo "  php cli.php make model Product" . PHP_EOL;
        echo "  php cli.php make route products/detail" . PHP_EOL;
        return 1;
    }
}
