<?php

namespace Services;

use Core\Config;
use Core\Request;
use Core\Response;
use Core\Security;
use Models\User;

/**
 * High-Performance Authentication & Session Management Service.
 * 
 * Provides encrypted session state management, brute-force attempt lockout,
 * and automatic 401 Unauthorized payload delivery.
 * 
 * @package Services
 */
class AuthService
{
    /**
     * Initializes secure PHP session with HTTP-only and SameSite flags.
     * 
     * @return void
     */
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            $isSecure = !Config::$DEBUG && (
                ($_SERVER['HTTPS'] ?? 'off') !== 'off' ||
                ($_SERVER['SERVER_PORT'] ?? 0) == 443 ||
                strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
            );
            session_set_cookie_params([
                'lifetime' => 86400,
                'path' => '/',
                'domain' => '',
                'secure' => $isSecure,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            session_start();
        }
    }

    /**
     * Validates current authenticated session.
     * 
     * @param bool $autoEmit401 Automatically emit 401 JSON error and halt execution if unauthenticated
     * @return array|false Returns authenticated user payload dictionary or false
     */
    public static function validateAuth(bool $autoEmit401 = true): array|false
    {
        self::startSession();

        if (empty($_SESSION['auth_user'])) {
            if ($autoEmit401) {
                Response::error('Unauthorized access', 401);
            }
            return false;
        }

        $decrypted = Security::decrypt((string) $_SESSION['auth_user']);
        if ($decrypted === false) {
            unset($_SESSION['auth_user']);
            if ($autoEmit401) {
                Response::error('Invalid or expired session state', 401);
            }
            return false;
        }

        $userData = json_decode($decrypted, true);
        if (!is_array($userData) || empty($userData['id'])) {
            unset($_SESSION['auth_user']);
            if ($autoEmit401) {
                Response::error('Corrupted session data', 401);
            }
            return false;
        }

        return $userData;
    }

    /**
     * Authenticates user credentials with brute-force lockout throttling.
     * 
     * @param string $username Username or email address
     * @param string $password Raw password string
     * @param int $maxAttempts Maximum allowed failed login attempts before lockout (default: 5)
     * @param int $lockoutSeconds Lockout duration in seconds (default: 60)
     * @return array Authentication status payload
     */
    public static function login(string $username, string $password, int $maxAttempts = 5, int $lockoutSeconds = 60): array
    {
        self::startSession();

        $attempts = (int) ($_SESSION['login_attempts'] ?? 0);
        $lastFailure = (int) ($_SESSION['last_failure'] ?? 0);

        if ($lastFailure > 0) {
            $elapsed = time() - $lastFailure;
            if ($attempts >= $maxAttempts && $elapsed < $lockoutSeconds) {
                $remaining = $lockoutSeconds - $elapsed;
                return [
                    'success' => false,
                    'locked' => true,
                    'remaining_seconds' => $remaining,
                    'message' => "Too many failed login attempts. Please wait {$remaining} seconds."
                ];
            } elseif ($elapsed >= $lockoutSeconds) {
                $_SESSION['login_attempts'] = 0;
                $attempts = 0;
            }
        }

        $users = User::all("(email = ? OR username = ?) LIMIT 1", [$username, $username]);
        $user = $users[0] ?? null;

        if ($user) {
            $userPassword = $user->password ?? '';
            $isValidPassword = password_verify($password, $userPassword) || (md5($password) === $userPassword);

            if ($isValidPassword) {
                if (password_needs_rehash($userPassword, PASSWORD_DEFAULT) || md5($password) === $userPassword) {
                    $user->password = password_hash($password, PASSWORD_DEFAULT);
                    $user->save();
                }

                $_SESSION['login_attempts'] = 0;
                unset($_SESSION['last_failure']);

                $userData = $user->toArray();
                unset($userData['password']);

                $_SESSION['auth_user'] = Security::encrypt((string) json_encode($userData));

                return [
                    'success' => true,
                    'user' => $userData
                ];
            }
        }

        $_SESSION['login_attempts'] = $attempts + 1;
        $_SESSION['last_failure'] = time();

        return [
            'success' => false,
            'locked' => false,
            'message' => 'Invalid username or password.'
        ];
    }

    /**
     * Destroys current authenticated session.
     * 
     * @return void
     */
    public static function logout(): void
    {
        self::startSession();
        unset($_SESSION['auth_user']);
        session_destroy();
    }
}
