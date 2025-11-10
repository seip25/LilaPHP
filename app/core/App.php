<?php

namespace Core;

use Core\Config;
use Core\Logger;
use Core\Response;
use Core\Template;
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
    protected array $middleware = [
        'before' => [],
        'after'  => []
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
            'csrf' => false,
            'cors' => true
        ]));
    }

    public function middleware(array $middlewares): void
    {
        $this->middleware = array_merge($this->middleware, $middlewares);
    }

    public function get(callable $callback): void
    {
        $this->routes['GET'] = $callback;
    }

    public function post(callable $callback): void
    {
        $this->routes['POST'] = $callback;
    }

    public function put(callable $callback): void
    {
        $this->routes['PUT'] = $callback;
    }

    public function delete(callable $callback): void
    {
        $this->routes['DELETE'] = $callback;
    }

    public function run(): void
    {
        $this->dispatch();
    }
    protected function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $callback = $this->routes[$method] ?? null;
        if (!is_callable($callback)) {
            http_response_code(404);
            exit("404 Not Found");
        }
        $req = $_REQUEST;
        $res = new Response();
        if (!$this->security->runBeforeMiddlewares($req)) exit;
        foreach ($this->middleware['before'] as $fn) {
            if (is_callable($fn)) $fn($req, $res);
        }

        $callback($req, $res);
        foreach ($this->middleware['after'] as $fn) {
            if (is_callable($fn)) $fn($req, $res);
        }

        exit;
    }

    public function render(string $template, array $context = [], ?string $path = null): void
    {
        try {
            $html = Template::render($template, $context, $path);
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
