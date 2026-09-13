<?php

declare(strict_types=1);

use Core\Request;
use Core\Response;
use Core\Validate;

$id = $_GET['id'] ?? ($_SERVER['ROUTE_ID'] ?? null);

$sampleUsers = [
    ['id' => 1, 'name' => 'Alice Johnson', 'email' => 'alice@example.com', 'role' => 'Admin'],
    ['id' => 2, 'name' => 'Bob Smith', 'email' => 'bob@example.com', 'role' => 'Developer'],
    ['id' => 3, 'name' => 'Carol Danvers', 'email' => 'carol@example.com', 'role' => 'Designer'],
];

Request::GET(function () use ($id, $sampleUsers) {
    if ($id !== null && $id !== '') {
        $user = array_values(array_filter($sampleUsers, fn($u) => (string)$u['id'] === (string)$id))[0] ?? null;
        if (!$user) {
            Response::error("User with ID `{$id}` not found", 404);
        }
        Response::json(['status' => 'success', 'data' => $user]);
    }
    Response::json(['status' => 'success', 'count' => count($sampleUsers), 'data' => $sampleUsers]);
});

Request::POST(function () use ($sampleUsers) {
    $body = Request::json();
    $errors = Validate::check($body, [
        'name'  => 'required|min_length:2',
        'email' => 'required|email',
        'role'  => 'in:Admin,Developer,Designer,User,Computer Scientist'
    ]);

    if (!empty($errors)) {
        Response::error('Validation failed', 422, $errors);
    }

    $newUser = [
        'id'    => count($sampleUsers) + 1,
        'name'  => trim((string)$body['name']),
        'email' => trim((string)$body['email']),
        'role'  => trim((string)($body['role'] ?? 'User')),
    ];

    Response::json([
        'status'  => 'success',
        'message' => 'User created successfully',
        'data'    => $newUser
    ], 201);
});

Request::DELETE(function () use ($id) {
    if ($id === null || $id === '') {
        Response::error('User ID is required for deletion', 400);
    }
    Response::json([
        'status'  => 'success',
        'message' => "User `{$id}` deleted successfully"
    ]);
});
