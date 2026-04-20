<?php

namespace Core;

class Security
{
    protected array $options = [];

    public function __construct(array $options = [])
    {
        $this->options = array_replace_recursive([
            'logger' => false,
            'rateLimit' => 200,
            'sanitize' => true,
            'cors' => [
                'enabled' => true,
                'origins' => ['*'],
                'methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
                'headers' => ['Content-Type', 'Authorization', 'X-CSRF-Token'],
                'credentials' => false
            ],
            'payloadCheck' => true,
            'csp' => [
                'enabled' => true,
                'directives' => [
                    'default-src' => ["'self'"],
                    'script-src' => [
                        "'self'",
                        "'unsafe-inline'",
                        "'unsafe-eval'",
                        "http://localhost:5173",
                        "https://challenges.cloudflare.com",
                        "https://cdn.jsdelivr.net",
                        "https://stackpath.bootstrapcdn.com",
                        "https://cdn.tailwindcss.com",
                        "https://ajax.googleapis.com",
                        "https://static.cloudflareinsights.com",
                        "https://cdnjs.cloudflare.com"
                    ],
                    'style-src' => [
                        "'self'",
                        "'unsafe-inline'",
                        "http://localhost:5173",
                        "https://fonts.googleapis.com",
                        "https://cdn.tailwindcss.com",
                        "https://cdn.jsdelivr.net",
                        "https://stackpath.bootstrapcdn.com",
                        "https://cdnjs.cloudflare.com"
                    ],
                    'font-src' => [
                        "'self'",
                        "https://fonts.gstatic.com",
                        "https://cdn.jsdelivr.net",
                        "https://cdnjs.cloudflare.com"
                    ],
                    'img-src' => ["'self'", "data:", "https:"],
                    'frame-src' => ["'self'", "https://challenges.cloudflare.com"],
                    'connect-src' => ["'self'", "https://*", "ws://localhost:5173", "https://cloudflareinsights.com"]
                ]
            ]
        ], $options);
    }
    public function runBeforeMiddlewares(array &$req, string $method = "GET"): bool
    {
        if ($this->options['logger']) {
            if (!$this->loggerMiddleware($req))
                return false;
        }
        $this->applyGeneralSecurityHeaders();

        if ($this->options['cors'])
            $this->corsHeaders();
        if ($this->options['csp'])
            $this->cspHeaders();

        if (isset($this->options['rateLimit']) && $this->options['rateLimit'] !== false) {
            if (!$this->rateLimitMiddleware()) {
                return false;
            }
        }

        $isMutation = in_array(strtoupper($method), ['POST', 'PUT', 'DELETE']);

        if ($isMutation) {
            if ($this->options['sanitize']) {
                $this->sanitizeRequest($req);
            }

            if ($this->options['payloadCheck']) {
                if (!$this->payloadCheck($req))
                    return false;
            }
        }
        header("X-Powered-By: Lila PHP Framework");
        return true;
    }


    protected function loggerMiddleware(array $req): bool
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? '';
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (array_key_exists("password", $req)) {
            $req['password'] = "********";
        }
        if (array_key_exists("_csrf", $req)) {
            $req['_csrf'] = "********";
        }
        if (array_key_exists("email", $req) && is_string($req['email']) && trim($req['email']) !== "") {
            $pos = strpos($req['email'], "@");
            if ($pos !== false && $pos > 0) {
                $maskPos = $pos > 3 ? $pos - 2 : $pos - 1;
                $req['email'][0] = "*";
                if (isset($req['email'][1]) && isset($req['email'][2]) && isset($req['email'][3])) {
                    $req['email'][1] = "*";
                    $req['email'][2] = "*";
                    $req['email'][3] = "*";
                }
                $req['email'] = substr($req['email'], 0, $maskPos) . "********";
            } else {
                $req['email'] = "********";
            }
        }
        Logger::info("{$method} {$uri} | IP={$ip} | Params=" . json_encode($req));
        return true;
    }



    protected function sanitizeRequest(array &$req): void
    {
        array_walk_recursive($req, function (&$value, $key) {
            if (is_string($value)) {
                $value = trim($value);
                $value = str_replace(chr(0), '', $value);
            }
        });
    }

    protected function payloadCheck(array $req): bool
    {
        $found = false;
        array_walk_recursive($req, function ($value) use (&$found) {
            if ($found || !is_string($value))
                return;

            if (preg_match('/<script\b[^>]*>|\bonerror\s*=\s*|\bonload\s*=\s*|javascript:/i', $value)) {
                $found = true;
            }
        });

        if ($found) {
            Response::JSON(['error' => 'Invalid payload detected'], 400);
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

    protected function cspHeaders(): void
    {
        $csp = $this->options['csp'];
        if (empty($csp['enabled']))
            return;

        $directives = $csp['directives'] ?? [];
        $policy = '';
        foreach ($directives as $directive => $sources) {
            $policy .= $directive . ' ' . implode(' ', $sources) . '; ';
        }

        if (!empty($policy)) {
            header("Content-Security-Policy: " . rtrim($policy));
        }
    }

    /**
     * Apply general security headers to every response.
     * These headers are independent of CSP and provide baseline protection.
     * 
     * @return void
     */
    protected function applyGeneralSecurityHeaders(): void
    {
        header("X-Frame-Options: SAMEORIGIN");

        header("X-Content-Type-Options: nosniff");

        header("X-XSS-Protection: 0");

        header("Cross-Origin-Opener-Policy: same-origin-allow-popups");

        if (!Config::$DEBUG) {
            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                ($_SERVER['SERVER_PORT'] == 443) ||
                (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

            if ($isHttps) {
                header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
            }
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

    /**
     * @param array $request
     * @return bool
     */
    public static function validateCsrfToken(array $request): bool
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET')
            return true;
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
            return false;
        }
        return true;
    }
    /**
     * Rate Limiting Middleware using Sessions
     *
     * @return bool True if allowed, false if rejected
     */
    protected function rateLimitMiddleware(): bool
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            return true;
        }

        $limit = $this->options['rateLimit'] ?? 200;
        $limit = ($limit === true || $limit === 1) ? 200 : (int) $limit;

        if (session_status() !== PHP_SESSION_ACTIVE) {
            Session::start();
        }

        $now = time();
        $rateData = Session::get('__rate_limit', [
            'count' => 0,
            'start' => $now
        ]);

        if ($now - $rateData['start'] >= 60) {
            $rateData['start'] = $now;
            $rateData['count'] = 0;
        }

        $rateData['count']++;
        Session::set('__rate_limit', $rateData);

        $remaining = max(0, $limit - $rateData['count']);
        $reset = max(0, 60 - ($now - $rateData['start']));

        header("X-RateLimit-Limit: $limit");
        header("X-RateLimit-Remaining: $remaining");
        header("X-RateLimit-Reset: $reset");

        if ($rateData['count'] > $limit) {
            header("Retry-After: $reset");
            $isContentTypeJsonOrFetchOrAjax = isset($_SERVER['CONTENT_TYPE']) && (strtolower($_SERVER['CONTENT_TYPE']) === 'application/json'
                || strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false
                || strpos($_SERVER['HTTP_ACCEPT'], 'text/html') !== false
                || strpos($_SERVER['HTTP_ACCEPT'], 'text/plain') !== false);
            if ($isContentTypeJsonOrFetchOrAjax) {
                Response::JSON(data: [
                    'error' => 'Too Many Requests',
                    'message' => 'Rate limit exceeded. Try again in ' . $reset . ' seconds.'
                ], status: 429);
            } else {
                $html = <<<HTML
    <div class="main-header ">
        <h1>Too Many Requests</h1>
    </div>

HTML;
                Response::HTML(html: $html, status: 429);
            }
            return false;
        }

        return true;
    }
}
