<?php

namespace Core;

use Core\Response;
use Core\Session;
use Core\Config;
use Models\Admin as AdminModel;
use PDO;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use ReflectionClass;

/**
 * Admin Portal Handler
 * 
 * Manages admin authentication, model auto-discovery, and dashboard rendering.
 * 
 * @package Core
 */
class AdminPortal
{
    /**
     * Handle the admin portal logic
     * 
     * @param array $req Request data
     * @param Response $res Response object
     * @param array $models Specific models to manage
     * @param array $options Additional options
     */
    public function handle(array $req, Response $res, array $models = [], array $options = []): void
    {
        // Handle Logout
        if (isset($req['logout']) && $req['logout'] === 'true') {
            Session::remove('admin_logged_in');
            Session::remove('admin_user');
            $res->redirect('Admin/');
            return;
        }

        // Check Authentication
        if (!Session::get('admin_logged_in')) {
            $this->handleLogin($req, $res);
            return;
        }

        // Handle Dashboard
        $discoveredModels = empty($models) ? $this->discoverModels() : $models;
        $activeModel = $req['model'] ?? (isset($discoveredModels[0]) ? $discoveredModels[0] : null);

        $q = $req['q'] ?? '';
        $page = (int) ($req['page'] ?? 1);
        $perPage = 6;

        $data = [];
        $pagination = [
            'total' => 0,
            'pages' => 0,
            'current' => $page,
            'q' => $q
        ];

        if ($activeModel && class_exists($activeModel)) {
            // Determine searchable columns (non-sensitive fields)
            $allFields = $activeModel::getDatabaseFields();
            $searchableColumns = [];
            $sensitivePatterns = ['password', 'token', 'hash', 'secret', 'key', 'auth'];

            foreach ($allFields as $field) {
                $isSensitive = false;
                foreach ($sensitivePatterns as $pattern) {
                    if (stripos($field, $pattern) !== false) {
                        $isSensitive = true;
                        break;
                    }
                }
                if (!$isSensitive) {
                    $searchableColumns[] = $field;
                }
            }

            $total = $activeModel::count(search: $q, searchColumns: $searchableColumns, activeOnly: false);
            $totalPages = ceil($total / $perPage);

            $results = $activeModel::paginate(page: $page, perPage: $perPage, search: $q, searchColumns: $searchableColumns, activeOnly: false);
            $data = $this->filterSensitiveData($results);

            $pagination['total'] = $total;
            $pagination['pages'] = $totalPages;
        }

        $res->render('lila/admin/dashboard', [
            'models' => $discoveredModels,
            'activeModel' => $activeModel,
            'data' => $data,
            'pagination' => $pagination,
            'debug' => Config::$DEBUG,
            'user' => Session::get('admin_user')
        ]);
    }

    /**
     * Handle admin login logic
     */
    private function handleLogin(array $req, Response $res): void
    {
        $error = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($req['username'], $req['password'])) {
            $admin = AdminModel::where('username', '=', $req['username'], activeOnly: false);

            if (!empty($admin)) {
                $user = $admin[0];
                if (password_verify($req['password'], $user->password)) {
                    Session::set('admin_logged_in', true);
                    Session::set('admin_user', $user->username);
                    $res->redirect('Admin/');
                    return;
                }
            }
            $error = "Invalid username or password";
        }

        $res->render('lila/admin/login', [
            'error' => $error,
            'debug' => Config::$DEBUG
        ]);
    }

    /**
     * Discover all models that extend BaseModel
     */
    private function discoverModels(): array
    {
        $models = [];
        $appDir = Config::$DIR_PROJECT; // app/ folder

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($appDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $content = file_get_contents($file->getPathname());

                if (preg_match('/class\s+(\w+)\s+extends\s+BaseModel/', $content, $matches)) {
                    $namespace = '';
                    if (preg_match('/namespace\s+([\w\\\\]+);/', $content, $nsMatches)) {
                        $namespace = $nsMatches[1] . '\\';
                    }

                    $className = $namespace . $matches[1];

                    if (class_exists($className)) {

                        if ($className === AdminModel::class || $className === 'Models\\Admin') {
                            continue;
                        }
                        $models[] = $className;
                    }
                }
            }
        }

        return $models;
    }

    /**
     * Filter out sensitive fields from the data
     */
    private function filterSensitiveData(array $data): array
    {
        $sensitivePatterns = ['password', 'token', 'hash', 'secret', 'key', 'auth'];

        return array_map(function ($row) use ($sensitivePatterns) {
            $filtered = (array) $row;
            foreach ($filtered as $key => $value) {
                foreach ($sensitivePatterns as $pattern) {
                    if (stripos($key, $pattern) !== false) {
                        unset($filtered[$key]);
                        break;
                    }
                }
            }
            return $filtered;
        }, $data);
    }
}
