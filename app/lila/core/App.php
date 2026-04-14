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
use ReflectionFunction;
use ReflectionMethod;

/**
 * LilaPHP Application Core Class
 * 
 * Main application class that handles routing, middleware, security, sessions,
 * and request/response lifecycle. Provides a lightweight and modular foundation
 * for building web applications.
 * 
 * @package Core
 * @author Andrés Paiva (Seip25)
 * @version 1.30
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

    /** @var array Cache for reflection objects */
    protected static array $reflectionCache = [];

    /** @var array Cache for DI parameter maps */
    protected static array $diCache = [];

    /**
     * Initialize the application with configuration options
     * 
     * Sets up the framework core including configuration loading, error handlers,
     * security middleware, session management, and translations.
     * 
     * @param array $options Configuration options
     *   - 'security' => array Security configuration (cors, sanitize, logger, csp)
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
     *         'logger' => true,
     *         'rateLimit' => 200,
     *            'csp' => [
     *               'enabled' => true,
     *               'directives' => [
     *                   'default-src' => ["'self'"],
     *                   'script-src'  => [
     *                       "'self'",
     *                       "'unsafe-inline'",
     *                       "'unsafe-eval'",
     *                       "http://localhost:5173",
     *                       "https://challenges.cloudflare.com",
     *                       "https://cdn.jsdelivr.net",
     *                       "https://stackpath.bootstrapcdn.com",
     *                       "https://cdn.tailwindcss.com",
     *                       "https://ajax.googleapis.com"
     *               ],
     *               'style-src'   => [
     *                   "'self'",
     *                   "'unsafe-inline'",
     *                   "http://localhost:5173",
     *                   "https://fonts.googleapis.com",
     *                   "https://cdn.tailwindcss.com",
     *                   "https://cdn.jsdelivr.net",
     *                   "https://stackpath.bootstrapcdn.com",
     *                   "https://cdnjs.cloudflare.com"
     *               ],
     *               'font-src'    => [
     *                   "'self'",
     *                   "https://fonts.gstatic.com",
     *                   "https://cdn.jsdelivr.net",
     *                   "https://cdnjs.cloudflare.com"
     *               ],
     *               'img-src'     => ["'self'", "data:", "https:"],
     *               'frame-src'   => ["'self'", "https://challenges.cloudflare.com"],
     *               'connect-src' => ["'self'", "https://*"]
     *           ]
     *       ]
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
        Session::start();
        if (Session::has(key: 'lang') == false) {
            $newLang = Config::$LANG;
            Session::set(key: 'lang', value: $newLang);
        }
        $this->security = new Security($options['security'] ?? []);

        if (isset($options['translate']) && $options['translate']) {
            Translate::load();
        }
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
     * Return if application is in debug mode
     * 
     */
    public function debug(): bool
    {
        return Config::$DEBUG ?? false;
    }
    /**
     * Return url project configurated in app/.env
     */
    public function urlProject(): string
    {
        return Config::$URL_PROJECT ?? "";
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
     * @param bool $useUrlProject Use URL_PROJECT from .env
     * @param bool $validateReferer Validate referer to prevent Open Redirect
     * @return void
     */
    public function redirect(string $url, bool $useUrlProject = true, bool $validateReferer = true): void
    {
        $newUrl = $url;
        $baseUrl = $this->getEnv("URL_PROJECT") ?? '';

        if ($useUrlProject && !str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            $newUrl = rtrim($baseUrl, '/') . '/' . ltrim($url, '/');
        }

        if ($validateReferer) {
            if (!str_starts_with($newUrl, '/') && !str_starts_with($newUrl, $baseUrl)) {
                $newUrl = '/';
            }
        }
        header(header: "Location: $newUrl");
        exit;
    }

    /**
     * Set a session variable
     * 
     * @param string $key Session key
     * @param mixed $value Value to store
     * @param bool $encrypt encrypt
     * @return void
     */
    public function setSession(string $key, mixed $value, bool $encrypt = false): void
    {
        Session::set($key, $value, $encrypt);
    }


    /**
     * Get a session variable
     * 
     * @param string $key Session key
     * @param mixed $default Default value if key doesn't exist
     * @return mixed Session value or default
     * @return bool $decrypt
     */
    public function getSession(string $key, $default = null, bool $decrypt = false)
    {
        return Session::get($key, $default, $decrypt);
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
     * Get translations
     * 
     * @return array Translations array
     */
    public function translations(): array
    {
        return Translate::translations();
    }
    /**
     * Translate a key
     * 
     * @param string $key Key to translate
     * @return string Translated key
     */
    public function translate(string $key): string
    {
        return Translate::t(key: $key);
    }

    /**
     * Register a GET route handler
     * 
     * @param callable|array|string $callback Route handler
     * @param array $middlewares Route-specific middleware functions
     * @return void
     */
    public function get(mixed $callback, array $middlewares = []): void
    {
        $this->registerRoute('GET', $callback, $middlewares);
    }

    /**
     * Register a POST route handler
     * 
     * @param callable|array|string $callback Route handler
     * @param array $middlewares Route-specific middleware functions
     * @param bool $csrf Enable CSRF token validation
     * @return void
     */
    public function post(mixed $callback, array $middlewares = [], bool $csrf = false): void
    {
        $this->registerRoute('POST', $callback, $middlewares, $csrf);
    }

    /**
     * Register a PUT route handler
     * 
     * @param callable|array|string $callback Route handler
     * @param array $middlewares Route-specific middleware functions
     * @param bool $csrf Enable CSRF token validation
     * @return void
     */
    public function put(mixed $callback, array $middlewares = [], bool $csrf = false): void
    {
        $this->registerRoute('PUT', $callback, $middlewares, $csrf);
    }

    /**
     * Register a DELETE route handler
     * 
     * @param callable|array|string $callback Route handler
     * @param array $middlewares Route-specific middleware functions
     * @param bool $csrf Enable CSRF token validation
     * @return void
     */
    public function delete(mixed $callback, array $middlewares = [], bool $csrf = false): void
    {
        $this->registerRoute('DELETE', $callback, $middlewares, $csrf);
    }

    /**
     * Register a route using PHP Attributes
     * 
     * @param mixed $callback Route handler with Attributes
     * @param array $middlewares Route-specific middleware functions
     * @return void
     */
    public function add(mixed $callback, array $middlewares = []): void
    {
        $reflection = $this->getReflection($callback);
        if (!$reflection)
            return;

        $methods = ['GET', 'POST', 'PUT', 'DELETE'];
        foreach ($methods as $method) {
            $attributeClass = "Core\\$method";
            if (!empty($reflection->getAttributes($attributeClass))) {
                $this->registerRoute($method, $callback, $middlewares);
            }
        }
    }

    /**
     * Helper to register routes and extract attributes
     * 
     * @param string $method HTTP method
     * @param mixed $callback Route handler
     * @param array $middlewares Initial middlewares
     * @param bool $csrf Initial CSRF status
     * @return void
     */
    protected function registerRoute(string $method, mixed $callback, array $middlewares = [], bool $csrf = false): void
    {
        include_once __DIR__ . '/Method.php';
        $reflection = $this->getReflection($callback);

        if ($reflection) {
            if (!empty($reflection->getAttributes(CSRF::class))) {
                $csrf = true;
            }

            $cacheAttr = $reflection->getAttributes(Cache::class);
            if (!empty($cacheAttr)) {
                $seconds = $cacheAttr[0]->newInstance()->seconds;
                $middlewares[] = Response::cacheResponse($seconds);
            }

            $validateAttr = $reflection->getAttributes(Validate::class);
            if (!empty($validateAttr)) {
                $instance = $validateAttr[0]->newInstance();
                $middlewares[] = $this->createValidationMiddleware($instance->modelClass, $instance->langParam);
            }

            $adminAttr = $reflection->getAttributes(Admin::class);
            if (!empty($adminAttr)) {
                $instance = $adminAttr[0]->newInstance();
                $middlewares[] = function ($req, $res) use ($instance) {
                    $admin = new \Core\AdminPortal();
                    $admin->handle($req, $res, $instance->models, $instance->options);
                };
            }

            foreach ($reflection->getAttributes(Middleware::class) as $attr) {
                $middlewares[] = $attr->newInstance()->callback;
            }
        }

        $this->routes[$method] = [
            'callback' => $callback,
            'middlewares' => $middlewares,
            'csrf' => $csrf
        ];
    }

    /**
     * Create validation middleware
     * 
     * @param string $modelClass The class name of the model
     * @param string|bool $langParam Language parameter override
     * @return callable Middleware closure
     */
    protected function createValidationMiddleware(string $modelClass, string|bool $langParam = false): callable
    {
        return function (array $req, Response $res) use ($modelClass, $langParam) {
            $lang = $langParam === false ? (Session::get('lang') ?? $this->getLangDefault()) : $langParam;
            new $modelClass(data: $req, lang: $lang, jsonResponse: true);
        };
    }


    /**
     * Get reflection object for a callback
     * 
     * @param mixed $callback
     * @return ReflectionFunction|ReflectionMethod|null
     */
    protected function getReflection(mixed $callback): mixed
    {
        $key = is_string($callback) ? $callback : (is_array($callback) ? (is_object($callback[0]) ? spl_object_hash($callback[0]) . '::' . $callback[1] : $callback[0] . '::' . $callback[1]) : (is_object($callback) ? spl_object_hash($callback) : null));

        if ($key && isset(self::$reflectionCache[$key])) {
            return self::$reflectionCache[$key];
        }

        try {
            $reflection = null;
            if (is_array($callback)) {
                $reflection = new ReflectionMethod($callback[0], $callback[1]);
            } elseif (is_string($callback) && strpos($callback, '::') !== false) {
                $reflection = new ReflectionMethod($callback);
            } elseif (is_callable($callback)) {
                $reflection = new ReflectionFunction($callback);
            }

            if ($key && $reflection) {
                self::$reflectionCache[$key] = $reflection;
            }

            return $reflection;
        } catch (Throwable $e) {
            return null;
        }
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
                'before' => [
                    function () {
                        Debug::end(http_response_code() ?: 200);
                    }
                ]
            ]);

            if ($method === 'GET' && isset($_GET['debug']) && Config::$DEBUG) {
                if (isset($_GET['clear']))
                    Debug::clear();
                elseif (isset($_GET['fetch']))
                    $this->jsonResponse(data: Debug::getRequests());
                else
                    $this->render('lila/debug', ['requests' => Debug::getRequests()]);
                exit;
            }
        }

        $route = $this->routes[$method] ?? null;

        if (!is_array($route) || !is_callable($route['callback'])) {
            if (Config::$DEBUG)
                Debug::end(http_response_code() ?: 404);
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

        if (isset($_GET['set-lang'])) {
            $newLang = $_GET["lang"] ?? $this->getLangDefault();
            $this->setSession(key: "lang", value: $newLang);

            if (isset($_GET['redirect']) && $_GET['redirect'] === 'false') {
                $this->jsonResponse(data: ["changeLang" => true, "lang" => $newLang]);
                exit;
            } else {
                http_response_code(302);
                $back = $_SERVER['HTTP_REFERER'] ?? '/';
                $this->redirect(url: $back);
                exit;
            }
        }

        $res = new Response();
        if (!$this->security->runBeforeMiddlewares($req, $method)) {
            if (Config::$DEBUG)
                Debug::end(http_response_code() ?: 403);
            exit;
        }

        foreach ($this->middlewares['before'] as $fn) {
            if (is_callable($fn)) {
                if ($fn($req, $res) === false) return;
            }
        }

        foreach ($route['middlewares'] as $fn) {
            if (is_callable($fn)) {
                if ($fn($req, $res) === false) return;
            }
        }
        $isValidRequest = true;
        if (in_array(needle: strtolower(string: $method), haystack: ['post', 'put', 'delete']) && (isset($route['csrf']) && $route['csrf'])) {
            $isValidRequest = Security::validateCsrfToken(request: $req);
        }

        if ($isValidRequest) {
            $callback = $route['callback'];
            $reflection = $this->getReflection($callback);

            if ($reflection) {
                $cbKey = is_string($callback) ? $callback : (is_array($callback) ? (is_object($callback[0]) ? spl_object_hash($callback[0]) . '::' . $callback[1] : $callback[0] . '::' . $callback[1]) : spl_object_hash($callback));

                if (!isset(self::$diCache[$cbKey])) {
                    $params = [];
                    foreach ($reflection->getParameters() as $param) {
                        $type = $param->getType();
                        $typeName = ($type instanceof \ReflectionNamedType) ? $type->getName() : null;
                        $params[] = [
                            'name' => $param->getName(),
                            'type' => $typeName
                        ];
                    }
                    self::$diCache[$cbKey] = $params;
                }

                $args = [];
                foreach (self::$diCache[$cbKey] as $paramData) {
                    $typeName = $paramData['type'];
                    $paramName = $paramData['name'];

                    if ($typeName === 'array' || $paramName === 'req') {
                        $args[] = $req;
                    } elseif ($typeName === Response::class || $typeName === 'Response' || $paramName === 'res') {
                        $args[] = $res;
                    } elseif ($typeName && class_exists($typeName)) {
                        $classRef = new \ReflectionClass($typeName);
                        if ($classRef->isInstantiable() && (!$classRef->getConstructor() || $classRef->getConstructor()->getNumberOfRequiredParameters() === 0)) {
                            $args[] = new $typeName();
                        } else {
                            $args[] = null;
                        }
                    } else {
                        $args[] = null;
                    }
                }
                $callback(...$args);
            } else {
                $callback($req, $res);
            }
        }

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
            Template::render(template: $template, context: $context, path: $path);
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
            Template::react(page: $page, props: $props, lang: $options['lang'] ?? null, title: $options['title'] ?? null, meta: $options['meta'] ?? [], scripts: $options['scripts'] ?? [], styles: $options['styles'] ?? []);
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
            if ($exc instanceof ValidationException) {
                $exc->render();
                exit;
            }

            $error = $exc->getMessage();
            $file = $exc->getFile();
            $line = $exc->getLine();
            $trace = $exc->getTraceAsString();
            $code = $exc->getCode();
            $time = date('Y-m-d H:i:s');
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

            $html = "<h1>Internal Server error</h1>";
            if (Config::$DEBUG) {
                $html = <<<HTML
    <div class="main-header">
        <h1>An unexpected error occurred</h1>
    </div>

    <div class="main-body">
        <p><strong>Message:</strong> {$error}</p>

        <div class="main-meta">
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
HTML;
            }
            die(Response::HTML(Template::templateLilaHTML($html), 500));
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
