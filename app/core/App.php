<?php

namespace Core;

use Core\Config;
use Core\Logger;
use Core\Response;
use Core\Template;
use Core\Session;

use Throwable;


class App
{
    protected array $options = [];
    protected ?Security $security = null;
    protected array $routes = [
        'GET' => null,
        'POST' => null,
        'PUT' => null,
        'DELETE' => null
    ];
    protected array $middlewares = [
        'before' => [],
        'after' => []
    ];

    public function __construct(array $options = ['security' => []])
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
        Translate::load();
         

    }
    public function getLangDefault(): string
    {
        return Config::$LANG;
    }
    public function redirect(string $url): void{
        header(header: "Location: $url");
      
    }

    public function setSession(string $key, $value): void
    {
        Session::set($key, $value);
    }

    public function getSession(string $key, $default = null)
    {
        return Session::get($key, $default);
    }

    public function hasSession(string $key): bool
    {
        return Session::has($key);
    }

    public function removeSession(string $key): void
    {
        Session::remove($key);
    }

    public function destroySession(): void
    {
        Session::destroy();
    }

    public function addMiddlewares(array $middlewares): void
    {
        $this->middlewares = array_merge($this->middlewares, $middlewares);
    }


    public function get(callable $callback, array $middlewares = []): void
    {
        $this->routes['GET'] = [
            'callback' => $callback,
            'middlewares' => $middlewares
        ];
    }

    public function post(callable $callback, array $middlewares = [], bool $csrf = false): void
    {
        $this->routes['POST'] = [
            'callback' => $callback,
            'middlewares' => $middlewares,
            'csrf' => $csrf
        ];
    }

    public function put(callable $callback, array $middlewares = [], bool $csrf = false): void
    {
        $this->routes['PUT'] = [
            'callback' => $callback,
            'middlewares' => $middlewares,
            'csrf' => $csrf
        ];
    }

    public function delete(callable $callback, array $middlewares = [], bool $csrf = false): void
    {
        $this->routes['DELETE'] = [
            'callback' => $callback,
            'middlewares' => $middlewares,
            'csrf' => $csrf
        ];
    }


    public function run(): void
    {
        $this->dispatch();
    }
    protected function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $route = $this->routes[$method] ?? null;

        if (!is_array($route) || !is_callable($route['callback'])) {
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
        if (!$this->security->runBeforeMiddlewares($req))
            exit;

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

    public function render(string $template, array $context = [], ?string $path = null): void
    {
        try {
            $html = Template::render(template: $template, context: $context, path: $path, );
            echo $html;
        } catch (Throwable $e) {
            $this->handleRenderException($e);
        }
    }

    public function jsonResponse(array $data, int $code = 200): void
    {
        Response::JSON($data, $code);
        exit;
    }

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

    protected function registerExceptionHandler(): void
    {
        set_exception_handler(function (Throwable $exc) {
            $error = $exc->getMessage();
            $file = $exc->getFile();
            $line = $exc->getLine();
            $date = date('d/m/Y H:i:s');
            $fileName = pathinfo($file)["basename"];

            $errorDetails = "File: {$file} - Line: {$line} - Error: {$error} - Date: {$date}";
            Logger::error($errorDetails, $fileName);

            $details = Config::$DEBUG ? ["message" => $errorDetails] : [];

            if ($this->isAjaxRequest()) {
                die(Response::JSON(['error' => true] + $details, 500));
            }

            $html = Config::$DEBUG ? "<pre>{$errorDetails}</pre>" : "<h1>Error</h1>";
            die(Response::HTML($html, 500));
        });
    }

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
