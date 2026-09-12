<?php

use Core\View;
use Core\Database;
use Core\Cache;
use Core\Config;

View::html(function () {
    $mysqlStatus = 'disconnected';
    $mysqlStatusStyles = "text-danger";
    $pdo = Database::getInstance();
    if ($pdo !== null) {
        $stmt = $pdo->query('SELECT 1');
        if ($stmt && $stmt->fetchColumn() == 1) {
            $mysqlStatus = 'connected';
            $mysqlStatusStyles = "text-success";
        }
    }
    $apcuTested = function_exists('apcu_enabled') && apcu_enabled();
    $apcuStatus = $apcuTested ? 'Enabled' : 'Disabled';
    $apcuStatusStyles = $apcuTested ? "text-success" : "text-danger";
    $redisStatus = 'disconnected';
    $redisStatusStyles = "text-danger";
    $redis = Cache::getRedis();
    if ($redis !== null) {
        try {
            if ($redis->ping()) {
                $redisStatus = 'connected';
            }
        } catch (\Throwable $e) {
            $redisStatus = 'error: ' . $e->getMessage();
        }
    }

    $appEnv = Config::$APP_ENV;
    $isDebug = Config::$DEBUG;

    return <<<HTML
    <!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LilaPHP Server Health</title>
    <link href="/css/styles.css" rel="stylesheet">
</head>

<body class="bg-dark text-light">
    <div class="container py-5">
        <h1 class="display-4 mb-4 text-success fw-bold">Server Health Check</h1>

        <div class="row g-4">
            <div class="col-md-6">
                <div class="card bg-dark border-secondary">
                    <div class="card-body">
                        <h5 class="card-title">PHP Native Engine</h5>
                        <div class="table-responsive">
                            <table class="table table-borderless text-light">
                                <tr>
                                    <td>Status</td>
                                    <td class="text-success"><strong class="text-success">Active</strong></td>
                                </tr>
                                <tr>
                                    <td>Environment</td>
                                    <td>"{$appEnv}"</td>
                                </tr>
                                <tr>
                                    <td>Debug Mode</td>
                                    <td>{$isDebug}</td>
                                </tr>
                                <tr>
                                    <td>Version</td>
                                    <td>8.4.0</td>
                                </tr>
                                <tr>
                                    <td>Max Execution</td>
                                    <td>"30s"</td>
                                </tr>
                                <tr>
                                    <td>Memory Limit</td>
                                    <td>"128MB"</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card bg-dark border-secondary">
                    <div class="card-body">
                        <h5 class="card-title">Core Services</h5>
                        <div class="table-responsive">
                            <table class="table table-borderless text-light">
                                <tr>
                                    <td>MySQL</td>
                                    <td class="{$mysqlStatusStyles}">{$mysqlStatus}</td>
                                </tr>
                                <tr>
                                    <td>Redis</td>
                                    <td class="{$redisStatusStyles}">{$redisStatus}</td>
                                </tr>
                                <tr>
                                    <td>APCu</td>
                                    <td class="{$apcuStatusStyles}">{"{$apcuTested}"}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="d-flex justify-content-center gap-4 mt-5">
            <a href="/" class="btn btn-outline btn-lg">Back to Home</a>
            <a href="/debug" class="btn btn-outline-warning btn-lg">View Diagnostics</a>
        </div>
    </div>
</body>

</html>
HTML;
}, ["cache" => true, "cacheTtl" => 10, "cacheKey" => "lila_health_view"]);
