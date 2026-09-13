<?php

declare(strict_types=1);

namespace Cli;

use Core\Config;

/**
 * Code Scaffolding Generator Command.
 * 
 * Supports generating Models, REST API Resources, Web Routes, and CRUD Views with Bluebird UI.
 * 
 * @package Cli
 */
class Make extends Command
{
    /**
     * Executes code generation.
     * 
     * @param array $args Command arguments
     * @return int
     */
    public function run(array $args): int
    {
        $type = strtolower($args[0] ?? '');
        $name = trim($args[1] ?? '');
        $flags = array_slice($args, 2);

        if ($name === '') {
            return $this->printUsage();
        }

        return match ($type) {
            'model' => $this->makeModel($name),
            'route' => $this->makeRoute($name),
            'api'   => $this->makeApi($name),
            'crud'  => $this->makeCrud($name, in_array('--embed', $flags, true)),
            default => $this->printUsage()
        };
    }

    /**
     * Generates a new Model class in app/models/.
     * 
     * @param string $name
     * @return int
     */
    public function makeModel(string $name): int
    {
        $className = ucfirst(basename($name));
        $file = Config::$DIR_APP . "/models/{$className}.php";

        if (file_exists($file)) {
            $this->error("Model `{$className}` already exists at {$file}");
            return 1;
        }

        $table = strtolower($className) . 's';
        $code = <<<PHP
<?php

declare(strict_types=1);

namespace Models;

/**
 * {$className} Model.
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
        $this->success("Created Model: `{$className}` -> `app/models/{$className}.php`");
        return 0;
    }

    /**
     * Generates a file-based Web Route in app/routes/.
     * 
     * @param string $path
     * @return int
     */
    public function makeRoute(string $path): int
    {
        $cleanPath = trim($path, '/');
        $file = Config::$DIR_APP . "/routes/{$cleanPath}.php";
        $dir = dirname($file);

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        if (file_exists($file)) {
            $this->error("Route file already exists at {$file}");
            return 1;
        }

        $viewName = $cleanPath;
        $code = <<<PHP
<?php

declare(strict_types=1);

use Core\Request;
use Core\View;

Request::GET(function () {
    View::render('{$viewName}', [
        'title' => '{$cleanPath}',
    ]);
});
PHP;

        file_put_contents($file, $code);
        $this->success("Created Web Route: `/{$cleanPath}` -> `app/routes/{$cleanPath}.php`");
        return 0;
    }

    /**
     * Generates a complete REST API resource in app/routes/api/.
     * 
     * @param string $name
     * @return int
     */
    public function makeApi(string $name): int
    {
        $className = ucfirst(basename($name));
        $resource = strtolower($className) . 's';
        $file = Config::$DIR_APP . "/routes/api/{$resource}.php";
        $dir = dirname($file);

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        if (file_exists($file)) {
            $this->error("API resource already exists at {$file}");
            return 1;
        }

        $code = <<<PHP
<?php

declare(strict_types=1);

use Core\Request;
use Core\Response;
use Core\Validate;
use Models\\{$className};

\$id = \$_GET['id'] ?? (\$_SERVER['ROUTE_ID'] ?? null);

Request::GET(function () use (\$id) {
    if (\$id !== null && \$id !== '') {
        \$item = {$className}::find((int)\$id);
        if (!\$item) {
            Response::error("Resource with ID `{\$id}` not found", 404);
        }
        Response::json(['status' => 'success', 'data' => \$item]);
    }

    \$limit = (int)(\$_GET['limit'] ?? 25);
    \$page = (int)(\$_GET['page'] ?? 1);
    \$items = {$className}::paginate(\$page, \$limit);

    Response::json([
        'status' => 'success',
        'data'   => \$items['data'] ?? [],
        'meta'   => \$items['meta'] ?? []
    ]);
});

Request::POST(function () {
    \$body = Request::json();
    \$instance = new {$className}();
    \$rules = \$instance->getRules();

    if (!empty(\$rules)) {
        \$errors = Validate::check(\$body, \$rules);
        if (!empty(\$errors)) {
            Response::error('Validation failed', 422, \$errors);
        }
    }

    \$created = {$className}::create(\$body);
    Response::json([
        'status'  => 'success',
        'message' => 'Resource created successfully',
        'data'    => \$created
    ], 201);
});

Request::PUT(function () use (\$id) {
    if (\$id === null || \$id === '') {
        Response::error('ID is required for update', 400);
    }

    \$item = {$className}::find((int)\$id);
    if (!\$item) {
        Response::error("Resource with ID `{\$id}` not found", 404);
    }

    \$body = Request::json();
    \$instance = new {$className}();
    \$rules = \$instance->getRules();

    if (!empty(\$rules)) {
        \$errors = Validate::check(\$body, \$rules);
        if (!empty(\$errors)) {
            Response::error('Validation failed', 422, \$errors);
        }
    }

    \$item->fill(\$body);
    \$item->save();

    Response::json([
        'status'  => 'success',
        'message' => 'Resource updated successfully',
        'data'    => \$item
    ]);
});

Request::DELETE(function () use (\$id) {
    if (\$id === null || \$id === '') {
        Response::error('ID is required for deletion', 400);
    }

    \$item = {$className}::find((int)\$id);
    if (!\$item) {
        Response::error("Resource with ID `{\$id}` not found", 404);
    }

    \$item->delete();
    Response::json([
        'status'  => 'success',
        'message' => "Resource `{\$id}` deleted successfully"
    ]);
});
PHP;

        file_put_contents($file, $code);
        $this->success("Created REST API Resource: `/api/{$resource}` -> `app/routes/api/{$resource}.php`");
        return 0;
    }

    /**
     * Generates a CRUD DataTable view and web route using Bluebird UI.
     * 
     * @param string $name
     * @param bool $embed Whether to omit standard layout wrapper for iframe embedding
     * @return int
     */
    public function makeCrud(string $name, bool $embed = false): int
    {
        $className = ucfirst(basename($name));
        $resource = strtolower($className) . 's';

        $routeFile = Config::$DIR_APP . "/routes/{$resource}.php";
        $viewFile = Config::$DIR_APP . "/views/{$resource}.php";

        $layoutOption = $embed ? "'layout' => false," : "";

        $routeCode = <<<PHP
<?php

declare(strict_types=1);

use Core\Request;
use Core\View;

Request::GET(function () {
    View::render('{$resource}', [
        'title' => '{$className} Management',
        {$layoutOption}
    ]);
});
PHP;

        if (!file_exists($routeFile)) {
            file_put_contents($routeFile, $routeCode);
            $this->success("Created Route: `/{$resource}` -> `app/routes/{$resource}.php`");
        }

        $embedHeader = $embed ? '<link rel="stylesheet" href="/css/bluebird.css">' : '';

        $viewCode = <<<PHP
<?php

declare(strict_types=1);

use Core\View;

\$title = View::escape(\$title ?? '{$className} Management');
\$csrfField = \$csrf_input ?? '';

echo View::html(function () use (\$title, \$csrfField) {
    return <<<HTML
{$embedHeader}
<div class="crud-container" style="padding: 1.5rem 0;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h1 style="font-size: 1.75rem; font-weight: 700; margin: 0; color: #f8fafc;">{\$title}</h1>
        <button id="btn-create" class="btn btn-primary" style="padding: 0.5rem 1rem; background: #0284c7; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">+ New {$className}</button>
    </div>

    <div style="background: #1e293b; border: 1px solid #334155; border-radius: 8px; overflow: hidden;">
        <table class="table" style="width: 100%; border-collapse: collapse; text-align: left;">
            <thead>
                <tr style="background: #0f172a; border-bottom: 1px solid #334155; color: #94a3b8; font-size: 0.875rem;">
                    <th style="padding: 0.75rem 1rem;">ID</th>
                    <th style="padding: 0.75rem 1rem;">Name</th>
                    <th style="padding: 0.75rem 1rem;">Created At</th>
                    <th style="padding: 0.75rem 1rem; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody id="datatable-body" style="color: #cbd5e1; font-size: 0.9rem;">
                <tr>
                    <td colspan="4" style="padding: 2rem; text-align: center; color: #64748b;">Loading data from /api/{$resource}...</td>
                </tr>
            </tbody>
        </table>
    </div>

    <form id="csrf-form" style="display: none;">
        {\$csrfField}
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', async () => {
    try {
        const res = await fetch('/api/{$resource}');
        if (res.ok) {
            const result = await res.json();
            const items = result.data || [];
            const tbody = document.getElementById('datatable-body');
            if (items.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" style="padding: 2rem; text-align: center; color: #64748b;">No records found.</td></tr>';
                return;
            }
            tbody.innerHTML = items.map(item => `
                <tr style="border-bottom: 1px solid #334155;">
                    <td style="padding: 0.75rem 1rem;">\${item.id}</td>
                    <td style="padding: 0.75rem 1rem; font-weight: 600; color: #f8fafc;">\${item.name || ''}</td>
                    <td style="padding: 0.75rem 1rem; color: #94a3b8;">\${item.created_at || 'N/A'}</td>
                    <td style="padding: 0.75rem 1rem; text-align: right;">
                        <button onclick="deleteRecord(\${item.id})" style="padding: 0.25rem 0.6rem; background: #dc2626; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-size: 0.8rem;">Delete</button>
                    </td>
                </tr>
            `).join('');
        }
    } catch (e) {}
});

async function deleteRecord(id) {
    if (!confirm('Are you sure you want to delete record #' + id + '?')) return;
    const csrfToken = document.getElementById('_csrf')?.value || '';
    const res = await fetch('/api/{$resource}/' + id, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': csrfToken }
    });
    if (res.ok) {
        location.reload();
    } else {
        alert('Failed to delete record.');
    }
}
</script>
HTML;
});
PHP;

        file_put_contents($viewFile, $viewCode);
        $this->success("Created CRUD View: `{$resource}` -> `app/views/{$resource}.php`" . ($embed ? " (Embed Mode)" : ""));
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
        echo "  php cli.php make model <Name>        Generate model in app/models/" . PHP_EOL;
        echo "  php cli.php make route <path>        Generate web route in app/routes/" . PHP_EOL;
        echo "  php cli.php make api <Name>          Generate REST API resource in app/routes/api/" . PHP_EOL;
        echo "  php cli.php make crud <Name> [--embed] Generate DataTable CRUD view & route" . PHP_EOL;
        return 1;
    }
}
