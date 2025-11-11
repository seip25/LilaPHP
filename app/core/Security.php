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
        if (session_status() === PHP_SESSION_ACTIVE)
            Session::start();
        if (Session::has(key: '_csrf'))
            return Session::get(key: '_csrf');

        $token = bin2hex(string: random_bytes(length: 32));
        Session::set(key: '_csrf', value: $token);
        return $token;
    }

    public static function validateCsrfToken(array $request): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET')
            return;
        if (session_status() === PHP_SESSION_ACTIVE)
            Session::start();
        $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($request['_csrf'] ?? '');
        $sessionToken = Session::get(key: '_csrf');

        if (!$headerToken || !$sessionToken || !hash_equals(known_string: $sessionToken, user_string: $headerToken)) {
            $data = Config::$DEBUG ? [
                "error" => true,
                "message" => "<p class='text-red-500 mt-4 text-center'>Invalid CSRF token</p>",
                "session" => $sessionToken,
                "request" => $request,
                "_SESSION" => $_SESSION
            ] : [
                "error" => true,
                "message" => "<p class='text-red-500 mt-4 text-center'>Session error</p>",
            ];
            Response::JSON(data: $data, status: 403);
            exit;
        }
    }

}
