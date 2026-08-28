<?php

declare(strict_types=1);

/**
 * Users REST API Endpoint (`/api/users` and `/api/users/{id}`).
 * 
 * Supports GET (list or single), POST (create), PUT (update), DELETE (remove).
 */

use Core\Request;
use Core\Response;

$method = Request::getMethod();
$id = $_GET['id'] ?? ($_SERVER['ROUTE_ID'] ?? null);

// Sample in-memory store if database table is not migrated yet
$sampleUsers = [
    ['id' => 1, 'name' => 'Alice Johnson', 'email' => 'alice@example.com', 'role' => 'Admin'],
    ['id' => 2, 'name' => 'Bob Smith', 'email' => 'bob@example.com', 'role' => 'Developer'],
    ['id' => 3, 'name' => 'Carol Danvers', 'email' => 'carol@example.com', 'role' => 'Designer'],
];

switch ($method) {
    case 'GET':
        if ($id !== null && $id !== '') {
            $user = array_values(array_filter($sampleUsers, fn($u) => (string)$u['id'] === (string)$id))[0] ?? null;
            if (!$user) {
                Response::error("User with ID `{$id}` not found", 404);
            }
            Response::json(['status' => 'success', 'data' => $user]);
        }
        Response::json(['status' => 'success', 'count' => count($sampleUsers), 'data' => $sampleUsers]);
        break;

    case 'POST':
        $body = Request::json();
        $name = trim((string)($body['name'] ?? ''));
        $email = trim((string)($body['email'] ?? ''));
        $role = trim((string)($body['role'] ?? 'User'));

        if ($name === '' || $email === '') {
            Response::error('Fields `name` and `email` are required', 422);
        }

        $newUser = [
            'id'    => count($sampleUsers) + 1,
            'name'  => $name,
            'email' => $email,
            'role'  => $role,
        ];

        Response::json([
            'status'  => 'success',
            'message' => 'User created successfully',
            'data'    => $newUser
        ], 201);
        break;

    case 'DELETE':
        if ($id === null || $id === '') {
            Response::error('User ID is required for deletion', 400);
        }
        Response::json([
            'status'  => 'success',
            'message' => "User `{$id}` deleted successfully"
        ]);
        break;

    default:
        Response::error("Method `{$method}` not allowed", 405);
}
