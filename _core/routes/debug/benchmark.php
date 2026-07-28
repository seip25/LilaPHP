<?php

use Core\Request;
use Core\Response;
use Core\Debug;

/**
 * Debug Benchmark Dispatcher Endpoint (`/api/debug/benchmark`).
 * 
 * Handles benchmark lifecycle:
 * - `POST /api/debug/benchmark` (or `action=start`): Triggers server-side CLI benchmark
 * - `GET /api/debug/benchmark?id=bench_xxx` (or `action=status`): Polls real-time snapshot
 * - `POST /api/debug/benchmark` with `action=stop`: Sends stop signal
 */

Request::POST(function () {
    $action = Request::input('action', 'start');
    $id = Request::input('id', '');

    if ($action === 'stop' && !empty($id)) {
        $stopped = Debug::stopBenchmark((string)$id);
        Response::json([
            'status' => $stopped ? 'success' : 'error',
            'message' => $stopped ? 'Stop signal sent' : 'Failed to send stop signal'
        ]);
        return;
    }

    $route = (string) Request::input('route', '/api');
    $concurrency = (int) Request::input('concurrency', 100);
    $duration = (int) Request::input('duration', 5);

    $result = Debug::startBenchmark($route, $concurrency, $duration);
    Response::json($result);
});

Request::GET(function () {
    Response::setPrivateCache();
    $id = (string) Request::input('id', '');
    if (empty($id)) {
        Response::error('Benchmark ID (`id`) is required for status polling', 400);
        return;
    }

    $status = Debug::getBenchmarkStatus($id);
    if ($status === null) {
        Response::json([
            'status' => 'not_found',
            'id' => $id,
            'message' => 'Benchmark snapshot expired or not found'
        ]);
        return;
    }

    Response::json($status);
});
