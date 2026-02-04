<?php

namespace Core;

use Core\Config;
use Core\Logger;
use Core\Debug;
use Core\Response;
use Core\Template;
use Core\Session;
use PDO;
use Throwable;

/**
 * LilaPHP Application Core Class
 * 
 * Main application class that handles routing, middleware, security, sessions,
 * and request/response lifecycle. Provides a lightweight and modular foundation
 * for building web applications.
 * 
 * @package Core
 * @author Andrés Paiva (Seip25)
 * @version 1.0.0
 */
class App
{
    /** @var array Application configuration options */
    protected array $options = [];

    /** @var Security|null Security handler instance */
    protected ?Security $security = null;

    /** @var array Registered routes for each HTTP method */
    protected array $routes = [
        'GET' => null,
        'POST' => null,
        'PUT' => null,
        'DELETE' => null
    ];

    /** @var array Middleware functions to run before and after route handlers */
    protected array $middlewares = [
        'before' => [],
        'after' => []
    ];

    /**
     * Initialize the application with configuration options
     * 
     * Sets up the framework core including configuration loading, error handlers,
     * security middleware, session management, and translations.
     * 
     * @param array $options Configuration options
     *   - 'security' => array Security configuration (cors, sanitize, logger)
     *   - 'translate' => bool Enable/disable translation system (default: true)
     * 
     * @example
     * ```php
     * // Default configuration
     * $app = new App();
     * 
     * // Custom configuration
     * $app = new App([
     *     'security' => [
     *         'cors' => false,
     *         'sanitize' => true,
     *         'logger' => true
     *     ],
     *     'translate' => false
     * ]);
     * ```
     */
    public function __construct(array $options = ['security' => [], 'translate' => true])
    {
        $this->options = $options;
        Config::load();
        $this->registerErrorHandler();
        $this->registerExceptionHandler();
        $this->security = new Security(array_merge($options['security'], [
            'logger' => true,
            'sanitize' => true,
            'cors' => true
        ]));
        Session::start();
        if (Session::has(key: 'lang') == false) {
            $newLang = Config::$LANG;
            Session::set(key: 'lang', value: $newLang);
        }
        if ($options['translate'])
            Translate::load();
    }

    /**
     * Get environment variable value
     * 
     * @param string $key Environment variable name
     * @return string|null Variable value or null if not found
     */
    public function getEnv(string $key): string|null
    {
        return Config::Env(key: $key);
    }
    /**
     * Get database connection using PDO
     * 
     * Creates and returns a PDO database connection. If no parameters are provided,
     * uses values from environment variables (.env file).
     * 
     * @param string|null $provider Database provider (mysql, pgsql, sqlite)
     * @param string|null $host Database host
     * @param string|null $dbUser Database username
     * @param string|null $dbPassword Database password
     * @param string|null $dbName Database name
     * @param int|null $port Database port
     * @return PDO|null PDO connection instance or null on failure
     * 
     * @example
     * ```php
     * // Default connection from .env
     * $db = $app->getDatabaseConnection();
     * 
     * // Custom connection
     * $db = $app->getDatabaseConnection(
     *     provider: 'pgsql',
     *     host: 'localhost',
     *     dbUser: 'postgres',
     *     dbPassword: 'secret',
     *     dbName: 'mydb',
     *     port: 5432
     * );
     * ```
     */
    public function getDatabaseConnection(
        ?string $provider = null,
        ?string $host = null,
        ?string $dbUser = null,
        ?string $dbPassword = null,
        ?string $dbName = null,
        ?int $port = null
    ): ?PDO {
        $provider = $provider ?? Config::Env(key: "DB_PROVIDER");
        $host = $host ?? Config::Env(key: "DB_HOST");
        $dbUser = $dbUser ?? Config::Env(key: "DB_USER");
        $dbPassword = $dbPassword ?? Config::Env(key: "DB_PASSWORD");
        $dbName = $dbName ?? Config::Env(key: "DB_NAME");
        $port = $port ?? (int) Config::Env(key: "DB_PORT");

        $db = new \Core\Database(
            provider: $provider,
            host: $host,
            dbUser: $dbUser,
            dbPassword: $dbPassword,
            dbName: $dbName,
            port: $port
        );

        return $db->getConnection();
    }

    /**
     * Get default language code
     * 
     * @return string Language code (e.g., 'eng', 'esp', 'bra', 'por')
     */
    public function getLangDefault(): string
    {
        return Config::$LANG;
    }

    /**
     * Redirect to a URL
     * 
     * @param string $url Target URL for redirection
     * @return void
     */
    public function redirect(string $url): void
    {
        header(header: "Location: $url");
    }

    /**
     * Set a session variable
     * 
     * @param string $key Session key
     * @param mixed $value Value to store
     * @return void
     */
    public function setSession(string $key, $value): void
    {
        Session::set($key, $value);
    }

    /**
     * Get a session variable
     * 
     * @param string $key Session key
     * @param mixed $default Default value if key doesn't exist
     * @return mixed Session value or default
     */
    public function getSession(string $key, $default = null)
    {
        return Session::get($key, $default);
    }

    /**
     * Check if a session variable exists
     * 
     * @param string $key Session key
     * @return bool True if exists, false otherwise
     */
    public function hasSession(string $key): bool
    {
        return Session::has($key);
    }

    /**
     * Remove a session variable
     * 
     * @param string $key Session key to remove
     * @return void
     */
    public function removeSession(string $key): void
    {
        Session::remove($key);
    }

    /**
     * Destroy the entire session
     * 
     * @return void
     */
    public function destroySession(): void
    {
        Session::destroy();
    }

    /**
     * Add global middleware functions
     * 
     * @param array $middlewares Array of middleware functions with 'before' and 'after' keys
     * @return void
     * 
     * @example
     * ```php
     * $app->addMiddlewares([
     *     'before' => [fn($req, $res) => Logger::info('Request started')],
     *     'after' => [fn($req, $res) => Logger::info('Request completed')]
     * ]);
     * ```
     */
    public function addMiddlewares(array $middlewares): void
    {
        $this->middlewares = array_merge($this->middlewares, $middlewares);
    }

    /**
     * Generate a CSRF token
     * 
     * @return string CSRF token
     */
    public function generateCSRF(): string
    {
        return Security::generateCsrfToken();
    }
    /**
     * Validate a CSRF token
     * 
     * @param array $request CSRF token to validate
     * @return void
     */
    public function validateCSRF(array $request): void
    {
        Security::validateCsrfToken(request: $request);
    }


    /**
     * Register a GET route handler
     * 
     * @param callable $callback Route handler function(req, res)
     * @param array $middlewares Route-specific middleware functions
     * @return void
     */
    public function get(callable $callback, array $middlewares = []): void
    {
        $this->routes['GET'] = [
            'callback' => $callback,
            'middlewares' => $middlewares
        ];
    }

    /**
     * Register a POST route handler
     * 
     * @param callable $callback Route handler function(req, res)
     * @param array $middlewares Route-specific middleware functions
     * @param bool $csrf Enable CSRF token validation
     * @return void
     */
    public function post(callable $callback, array $middlewares = [], bool $csrf = false): void
    {
        $this->routes['POST'] = [
            'callback' => $callback,
            'middlewares' => $middlewares,
            'csrf' => $csrf
        ];
    }

    /**
     * Register a PUT route handler
     * 
     * @param callable $callback Route handler function(req, res)
     * @param array $middlewares Route-specific middleware functions
     * @param bool $csrf Enable CSRF token validation
     * @return void
     */
    public function put(callable $callback, array $middlewares = [], bool $csrf = false): void
    {
        $this->routes['PUT'] = [
            'callback' => $callback,
            'middlewares' => $middlewares,
            'csrf' => $csrf
        ];
    }

    /**
     * Register a DELETE route handler
     * 
     * @param callable $callback Route handler function(req, res)
     * @param array $middlewares Route-specific middleware functions
     * @param bool $csrf Enable CSRF token validation
     * @return void
     */
    public function delete(callable $callback, array $middlewares = [], bool $csrf = false): void
    {
        $this->routes['DELETE'] = [
            'callback' => $callback,
            'middlewares' => $middlewares,
            'csrf' => $csrf
        ];
    }


    /**
     * Run the application and dispatch the request
     * 
     * @return void
     */
    public function run(): void
    {
        $this->dispatch();
    }
    /**
     * Dispatch the current request to the appropriate route handler
     * 
     * @return void
     */
    protected function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if (Config::$DEBUG) {
            Debug::init();
            Debug::start();
            $this->addMiddlewares([
                'before' => [function () {
                    Debug::end(http_response_code() ?: 200);
                }]
            ]);

            if ($method === 'GET' && isset($_GET['debug'])   && Config::$DEBUG) {
                if (isset($_GET['clear'])) Debug::clear();
                elseif (isset($_GET['fetch'])) echo json_encode(Debug::getRequests());
                else $this->render('lila/debug', ['requests' => Debug::getRequests()]);
                exit;
            }
        }

        $route = $this->routes[$method] ?? null;

        if (!is_array($route) || !is_callable($route['callback'])) {
            if (Config::$DEBUG) Debug::end(http_response_code() ?: 404);
            http_response_code(404);
            exit("404 Not Found");
        }

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        $data = [];

        if (stripos($contentType, 'application/json') !== false) {
            $json = file_get_contents('php://input');
            $data = json_decode($json, true) ?? [];
        }

        $req = array_merge($_GET, $_POST, $data, $_FILES);

        $res = new Response();
        if (!$this->security->runBeforeMiddlewares($req)) {
            if (Config::$DEBUG) Debug::end(http_response_code() ?: 403);
            exit;
        }

        foreach ($this->middlewares['before'] as $fn) {
            if (is_callable($fn))
                $fn($req, $res);
        }

        foreach ($route['middlewares'] as $fn) {
            if (is_callable($fn))
                $fn($req, $res);
        }
        if (in_array(needle: strtolower(string: $method), haystack: ['post', 'put', 'delete']) && (isset($route['csrf']) && $route['csrf'])) {
            Security::validateCsrfToken(request: $req);
        }

        $route['callback']($req, $res);

        foreach ($this->middlewares['after'] as $fn) {
            if (is_callable($fn))
                $fn($req, $res);
        }

        exit;
    }

    /**
     * Render a Twig template
     * 
     * @param string $template Template name (without .twig extension)
     * @param array $context Variables to pass to the template
     * @param string|null $path Custom template directory path
     * @return void
     * 
     * @example
     * ```php
     * $app->render('home', ['title' => 'Welcome', 'user' => $userData]);
     * ```
     */
    public function render(string $template, array $context = [], ?string $path = null): void
    {
        try {
            $html = Template::render(template: $template, context: $context, path: $path,);
            echo $html;
        } catch (Throwable $e) {
            $this->handleRenderException($e);
        }
    }

    /**
     * Render a React full page
     * 
     * @param string $page Page React name
     * @param array $props Props to pass to the island
     * @param string|null $title Page title
     * @param array $meta Meta tags
     * @param array $scripts Scripts to include
     * @param array $styles Styles to include
     * @return void
     * 
     * @example
     * ```php
     * $app->renderReact('my-page-or-component', ['name' => 'John']);
     * ```
     */

    public function renderReact(string $page, array $props = [], array $options = ["lang" => null, "title" => null, "meta" => [], "scripts" => [], "styles" => []]): void
    {
        try {
            $html = Template::react(island: $page, props: $props, lang: $options['lang'] ?? null, title: $options['title'] ?? null, meta: $options['meta'] ?? [], scripts: $options['scripts'] ?? [], styles: $options['styles'] ?? []);
            $html = Template::minifyHtml(buffer: $html);
            echo $html;
        } catch (Throwable $e) {
            $this->handleRenderException($e);
        }
    }

    /**
     * Send a JSON response
     * 
     * @param array $data Data to encode as JSON
     * @param int $code HTTP status code
     * @return void
     * 
     * @example
     * ```php
     * $app->jsonResponse(['success' => true, 'data' => $result]);
     * ```
     */
    public function jsonResponse(array $data, int $code = 200): void
    {
        Response::JSON($data, $code);
        exit;
    }

    /**
     * Handle template rendering exceptions
     * 
     * @param Throwable $e Exception that occurred during rendering
     * @return void
     */
    protected function handleRenderException(Throwable $e): void
    {
        $errorDetails = sprintf(
            "Error: %s in %s line %d",
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        );

        Logger::error($errorDetails, pathinfo($e->getFile(), PATHINFO_BASENAME));

        if ($this->isAjaxRequest()) {
            Response::JSON(['error' => true, 'message' => $e->getMessage()], 500);
        } else {
            $html = Config::$DEBUG ? "<pre>{$errorDetails}</pre>" : "<h1>Error</h1>";
            Response::HTML($html, 500);
        }

        exit;
    }

    /**
     * Register custom error handler
     * 
     * @return void
     */
    protected function registerErrorHandler(): void
    {
        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            $date = date('d/m/Y H:i:s');
            $errorDetails = "File: {$errfile} - Line: {$errline} - Error: {$errno} - {$errstr} - Date: {$date}";
            $fileName = pathinfo($errfile)["basename"];

            Logger::error($errorDetails, $fileName);

            $details = Config::$DEBUG ? [
                'message' => $errstr,
                'file' => $errfile,
                'line' => $errline
            ] : [];

            if ($this->isAjaxRequest()) {
                die(Response::JSON(['error' => true] + $details, 500));
            }

            $html = Config::$DEBUG ? "<pre>{$errorDetails}</pre>" : "<h1>Error</h1>";
            die(Response::HTML($html, 500));
        });
    }

    /**
     * Register custom exception handler
     * 
     * @return void
     */
    protected function registerExceptionHandler(): void
    {
        set_exception_handler(function (Throwable $exc) {

            $error   = $exc->getMessage();
            $file    = $exc->getFile();
            $line    = $exc->getLine();
            $trace   = $exc->getTraceAsString();
            $code    = $exc->getCode();
            $time    = date('Y-m-d H:i:s');
            $fileName = pathinfo($file, PATHINFO_BASENAME);

            $errorDetails = "{$file}({$line})\nError: {$error}\n\n{$trace}\n";
            Logger::error($errorDetails, $fileName);

            $details = Config::$DEBUG ?
                [
                    "message" => $error,
                    "file" => $file,
                    "line" => $line,
                    "trace" => $trace,
                    "code" => $code,
                ]
                : [];

            if ($this->isAjaxRequest()) {
                die(Response::JSON(['error' => true] + $details, 500));
            }

            $htmlDebug = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head> 
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="icon" type="image/ico" href="favicon.ico" />
    <title>Application Error</title>
    <style>
        body {
            background: #f6f7f9;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            padding: 40px;
            color: #333;
        }
        .error-container {
            max-width: 900px;
            margin: auto;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0,0,0,.08);
            overflow: hidden;
        }
        .error-header {
            background: #ff4d4f;
            color: #fff;
            padding: 20px;
        }
        .error-header h1 {
            margin: 0;
            font-size: 22px;
        }
        .error-body {
            padding: 20px;
        }
        .error-meta {
            font-size: 13px;
            color: #666;
            margin-bottom: 15px;
        }
        pre {
            background: #f4f4f4;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 13px;
            line-height: 1.4;
        }
        
        .footer {
            padding: 15px;
            font-size: 12px;
            color: #999;
            background: #fafafa;
            border-top: 1px solid #eee;
            text-align: right;
        }
    </style>
</head>
<body>

<div class="error-container">
    <div class="error-header">
        <h1>An unexpected error occurred</h1>
    </div>

    <div class="error-body">
        <p><strong>Message:</strong> {$error}</p>

        <div class="error-meta">
            <div><strong>File:</strong> {$file}</div>
            <div><strong>Line:</strong> {$line}</div>
            <div><strong>Time:</strong> {$time}</div>
        </div>

        <div>
             <pre>{$trace}</pre>
        </div>
    </div>

    <div class="footer">
        Debug mode enabled
    </div>
</div>

</body>
</html>
HTML;

            $html = Config::$DEBUG
                ? $htmlDebug
                : "<h1>Internal Server Error</h1>";

            die(Response::HTML($html, 500));
        });
    }

    /**
     * Check if the current request is an AJAX request
     * 
     * @return bool True if AJAX request, false otherwise
     */
    protected function isAjaxRequest(): bool
    {
        if (
            !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
        ) {
            return true;
        }

        if (
            !empty($_SERVER['HTTP_ACCEPT']) &&
            strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false
        ) {
            return true;
        }

        return false;
    }
}
