<?php

namespace Core;

class Security
{
    protected array $options = [];

    public function __construct(array $options = [])
    {
        $this->options = array_merge([
            'logger' => true,
            'rateLimit' => true,
            'sanitize' => true,
            'csrf' => true,
            'cors' => [
                'enabled' => true,
                'origins' => ['*'],
                'methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
                'headers' => ['Content-Type', 'Authorization', 'X-CSRF-Token'],
                'credentials' => false
            ],
            'payloadCheck' => true
        ], $options);
    }
    public function runBeforeMiddlewares(array &$req): bool
    {
        if ($this->options['logger']) if (!$this->loggerMiddleware($req))
            return false;
        if ($this->options['cors'])
            $this->corsHeaders();
        if ($this->options['csrf'])
            $this->validateCsrfToken();
        if ($this->options['sanitize'])
            $this->sanitizeRequest($req);
        if ($this->options['payloadCheck']) if (!$this->payloadCheck($req))
            return false;

        header("X-Powered-By: Lila Framework");
        return true;
    }


    protected function loggerMiddleware(array $req): bool
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? '';
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        Logger::info("{$method} {$uri} | IP={$ip} | Params=" . json_encode($req));
        return true;
    }



    protected function sanitizeRequest(array &$req): void
    {
        array_walk_recursive($req, function (&$value) {
            $value = trim($value);
            $value = strip_tags($value);
            $value = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        });
    }

    protected function payloadCheck(array $req): bool
    {
        $payload = json_encode($req);
        if (preg_match('/<script|onerror=|onload=|javascript:/i', $payload)) {
            Response::JSON(['error' => 'Invalid payload'], 400);
            return false;
        }
        return true;
    }

    protected function corsHeaders(): void
    {
        $cors = $this->options['cors'];
        if (empty($cors['enabled']))
            return;
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
        $allowedOrigins = $cors['origins'] ?? ['*'];
        if (in_array('*', $allowedOrigins) || in_array($origin, $allowedOrigins)) {
            header("Access-Control-Allow-Origin: " . (in_array('*', $allowedOrigins) ? '*' : $origin));
        }
        header("Access-Control-Allow-Methods: " . implode(', ', $cors['methods'] ?? ['GET', 'POST']));
        header("Access-Control-Allow-Headers: " . implode(', ', $cors['headers'] ?? ['Content-Type']));
        if (!empty($cors['credentials'])) {
            header("Access-Control-Allow-Credentials: true");
        }
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }


    public static function generateCsrfToken(): string
    {
        Session::start();
        $token = bin2hex(random_bytes(32));
        Session::set('_csrf', $token);
        return $token;
    }

    protected function validateCsrfToken(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET')
            return;

        Session::start();

        $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_REQUEST['_csrf'] ?? '');
        $sessionToken = Session::get('_csrf');

        if (!$headerToken || !$sessionToken || !hash_equals($sessionToken, $headerToken)) {
            Response::JSON(['error' => 'Invalid CSRF token'], 403);
            exit;
        }
    }

}
