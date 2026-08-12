<?php

use Core\Config;
use Core\Response;

$isDebug = filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN);
$isLoggingEnabled = filter_var(getenv('DEBUG_LOGGING_ENABLED') ?: 'false', FILTER_VALIDATE_BOOLEAN);

if (!$isDebug || !$isLoggingEnabled) {
    Response::error('Not Found', 404);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LilaPHP - Debug & Performance Dashboard</title>
    <meta name="description"
        content="LilaPHP Built-in High-Performance Debug Dashboard and Real-Time Concurrency Benchmark Tool">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500;600;700&family=Outfit:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <style>
        :root {
            --bg-main: #0f172a;
            --bg-card: rgba(30, 41, 59, 0.7);
            --bg-card-hover: rgba(51, 65, 85, 0.8);
            --border-color: rgba(255, 255, 255, 0.08);
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            --accent-purple: #8b5cf6;
            --accent-indigo: #6366f1;
            --accent-pink: #ec4899;
            --accent-green: #10b981;
            --accent-yellow: #f59e0b;
            --accent-red: #ef4444;
            --glow-shadow: 0 0 35px rgba(139, 92, 246, 0.25);
            --font-main: 'Outfit', -apple-system, BlinkMacSystemFont, sans-serif;
            --font-code: 'Fira Code', monospace;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: var(--bg-main);
            color: var(--text-primary);
            font-family: var(--font-main);
            line-height: 1.6;
            overflow-x: hidden;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .container {
            width: 100%;
            max-width: 1240px;
            margin: 0 auto;
            padding: 0 24px;
        }

        /* Background Glows */
        .background-glow {
            position: fixed;
            width: 500px;
            height: 500px;
            border-radius: 50%;
            filter: blur(140px);
            z-index: -1;
            opacity: 0.35;
            pointer-events: none;
        }

        .glow-1 {
            top: -150px;
            left: -100px;
            background: radial-gradient(circle, var(--accent-purple), transparent 70%);
        }

        .glow-2 {
            bottom: -150px;
            right: -100px;
            background: radial-gradient(circle, var(--accent-indigo), transparent 70%);
        }

        /* Navbar */
        .navbar {
            border-bottom: 1px solid var(--border-color);
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(16px);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .navbar-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 72px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-badge {
            background: linear-gradient(135deg, var(--accent-purple), var(--accent-indigo));
            color: white;
            font-size: 0.75rem;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 999px;
            letter-spacing: 0.5px;
            box-shadow: 0 0 15px rgba(139, 92, 246, 0.4);
        }

        .brand-title {
            font-size: 1.6rem;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .accent {
            background: linear-gradient(to right, var(--accent-purple), var(--accent-pink));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 28px;
        }

        .nav-link {
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.95rem;
            transition: color 0.2s ease;
        }

        .nav-link:hover {
            color: var(--text-primary);
        }

        .btn-glow {
            background: rgba(139, 92, 246, 0.15);
            color: #c4b5fd;
            padding: 8px 18px;
            border-radius: 8px;
            border: 1px solid rgba(139, 92, 246, 0.3);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .btn-glow:hover {
            background: var(--accent-purple);
            color: white;
            box-shadow: var(--glow-shadow);
            transform: translateY(-2px);
        }

        /* Main Hero */
        .main-content {
            flex: 1;
            padding-top: 60px;
            padding-bottom: 80px;
        }

        .hero {
            display: grid;
            grid-template-columns: 1.1f 0.9fr;
            gap: 48px;
            align-items: center;
            margin-bottom: 80px;
        }

        .tagline {
            color: var(--accent-purple);
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 16px;
        }

        .hero-title {
            font-size: 3.2rem;
            font-weight: 700;
            line-height: 1.15;
            margin-bottom: 24px;
            letter-spacing: -1px;
        }

        .gradient-text {
            background: linear-gradient(135deg, #a78bfa, #f472b6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-description {
            color: var(--text-secondary);
            font-size: 1.15rem;
            margin-bottom: 32px;
            max-width: 560px;
        }

        .hero-actions {
            display: flex;
            gap: 16px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 14px 28px;
            font-size: 1rem;
            font-weight: 600;
            border-radius: 12px;
            border: none;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--accent-purple), var(--accent-indigo));
            color: white;
            box-shadow: 0 8px 25px rgba(139, 92, 246, 0.35);
        }

        .btn-primary:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 12px 30px rgba(139, 92, 246, 0.5);
        }

        .btn-icon {
            width: 18px;
            height: 18px;
            transition: transform 0.3s ease;
        }

        .btn-primary:hover .btn-icon {
            transform: translateX(3px);
        }

        .btn-secondary {
            background: var(--bg-card);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover {
            background: var(--bg-card-hover);
            border-color: rgba(255, 255, 255, 0.2);
            transform: translateY(-3px);
        }

        /* Code Card */
        .code-preview {
            background: rgba(15, 23, 42, 0.9);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
            overflow: hidden;
            backdrop-filter: blur(20px);
        }

        .code-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 20px;
            background: rgba(30, 41, 59, 0.6);
            border-bottom: 1px solid var(--border-color);
        }

        .dots {
            display: flex;
            gap: 8px;
        }

        .dots span {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }

        .dots span:nth-child(1) {
            background: #ef4444;
        }

        .dots span:nth-child(2) {
            background: #f59e0b;
        }

        .dots span:nth-child(3) {
            background: #10b981;
        }

        .code-filename {
            font-family: var(--font-code);
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        .code-content {
            padding: 24px;
            font-family: var(--font-code);
            font-size: 0.9rem;
            overflow-x: auto;
            line-height: 1.6;
        }

        .comment {
            color: #64748b;
            font-style: italic;
        }

        .keyword {
            color: #c084fc;
            font-weight: 600;
        }

        .type {
            color: #38bdf8;
        }

        .string {
            color: #34d399;
        }

        .variable {
            color: #f472b6;
        }

        .number {
            color: #fbbf24;
        }

        /* Diagnostics Section */
        .diagnostics-section {
            margin-bottom: 90px;
            background: rgba(15, 23, 42, 0.4);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 40px;
            backdrop-filter: blur(12px);
        }

        .section-header {
            margin-bottom: 32px;
        }

        .section-header h3 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .section-header p {
            color: var(--text-secondary);
            font-size: 1rem;
        }

        .inline-code {
            font-family: var(--font-code);
            background: rgba(255, 255, 255, 0.08);
            padding: 2px 8px;
            border-radius: 6px;
            color: #a78bfa;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 20px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 24px;
            display: flex;
            align-items: flex-start;
            gap: 16px;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            border-color: rgba(139, 92, 246, 0.4);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
        }

        .stat-icon {
            font-size: 2rem;
            background: rgba(255, 255, 255, 0.05);
            width: 54px;
            height: 54px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .stat-label {
            display: block;
            color: var(--text-secondary);
            font-size: 0.85rem;
            font-weight: 500;
            margin-bottom: 4px;
        }

        .stat-value {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.2;
        }

        .stat-sub {
            display: inline-block;
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 6px;
        }

        .status-green {
            color: var(--accent-green);
            font-weight: 600;
        }

        .status-yellow {
            color: var(--accent-yellow);
            font-weight: 600;
        }

        .status-red {
            color: var(--accent-red);
            font-weight: 600;
        }

        .terminal-box {
            background: #090d16;
            border: 1px solid var(--border-color);
            border-radius: 16px;
            overflow: hidden;
        }

        .terminal-header {
            background: rgba(30, 41, 59, 0.8);
            padding: 12px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-family: var(--font-code);
            font-size: 0.85rem;
            color: var(--text-secondary);
            border-bottom: 1px solid var(--border-color);
        }

        .badge-live {
            background: var(--accent-green);
            color: #042f2c;
            font-weight: 700;
            font-size: 0.7rem;
            padding: 2px 8px;
            border-radius: 4px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                opacity: 1;
            }

            50% {
                opacity: 0.5;
            }

            100% {
                opacity: 1;
            }
        }

        .terminal-body {
            padding: 24px;
            font-family: var(--font-code);
            font-size: 0.85rem;
            color: #38bdf8;
            max-height: 320px;
            overflow-y: auto;
            white-space: pre-wrap;
            line-height: 1.5;
        }

        /* Features Grid */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 24px;
        }

        .feature-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 18px;
            padding: 30px;
            transition: all 0.3s ease;
        }

        .feature-card:hover {
            background: var(--bg-card-hover);
            transform: translateY(-5px);
            border-color: rgba(139, 92, 246, 0.3);
        }

        .feature-icon {
            font-size: 2.2rem;
            margin-bottom: 20px;
        }

        .feature-card h4 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 12px;
        }

        .feature-card p {
            color: var(--text-secondary);
            font-size: 0.95rem;
        }

        /* Footer */
        .footer {
            border-top: 1px solid var(--border-color);
            padding: 36px 0;
            background: rgba(15, 23, 42, 0.9);
        }

        .footer-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .footer-meta {
            font-family: var(--font-code);
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        @media (max-width: 900px) {
            .hero {
                grid-template-columns: 1fr;
                gap: 36px;
            }

            .hero-title {
                font-size: 2.4rem;
            }

            .navbar-content {
                flex-wrap: wrap;
            }

            .nav-links {
                display: none;
            }
        }

        /* ==========================================================================
   Debug & Performance Dashboard Styles
   ========================================================================== */

        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .metric-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            backdrop-filter: blur(12px);
            transition: transform 0.2s ease, border-color 0.2s ease;
        }

        .metric-card:hover {
            border-color: rgba(139, 92, 246, 0.4);
            transform: translateY(-2px);
        }

        .metric-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .metric-title {
            font-size: 0.85rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .metric-val {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-primary);
            font-family: var(--font-code);
        }

        .metric-sub {
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        /* Status Pills */
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-pill.online {
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .status-pill.offline {
            background: rgba(239, 68, 68, 0.15);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .status-pill.degraded {
            background: rgba(245, 158, 11, 0.15);
            color: #fbbf24;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: currentColor;
            box-shadow: 0 0 8px currentColor;
        }

        /* Dashboard Controls & Inputs */
        .controls-row {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            align-items: flex-end;
            margin-bottom: 24px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
            flex: 1;
            min-width: 180px;
        }

        .form-label {
            font-size: 0.85rem;
            color: var(--text-secondary);
            font-weight: 600;
        }

        .form-select,
        .form-input {
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            padding: 10px 14px;
            border-radius: 10px;
            font-family: var(--font-main);
            font-size: 0.95rem;
            outline: none;
            transition: border-color 0.2s ease;
        }

        .form-select:focus,
        .form-input:focus {
            border-color: var(--accent-purple);
            box-shadow: 0 0 10px rgba(139, 92, 246, 0.2);
        }

        .btn {
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--accent-purple), var(--accent-indigo));
            color: white;
            box-shadow: 0 0 15px rgba(139, 92, 246, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 0 20px rgba(139, 92, 246, 0.5);
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            box-shadow: 0 0 15px rgba(239, 68, 68, 0.3);
        }

        .btn-danger:hover {
            transform: translateY(-1px);
            box-shadow: 0 0 20px rgba(239, 68, 68, 0.5);
        }

        .btn-secondary {
            background: rgba(51, 65, 85, 0.8);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover {
            background: rgba(71, 85, 105, 0.9);
        }

        /* Data Tables */
        .table-container {
            width: 100%;
            overflow-x: auto;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            backdrop-filter: blur(12px);
        }

        .debug-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 0.9rem;
        }

        .debug-table th {
            background: rgba(15, 23, 42, 0.6);
            padding: 14px 18px;
            color: var(--text-secondary);
            font-weight: 600;
            border-bottom: 1px solid var(--border-color);
            white-space: nowrap;
        }

        .debug-table td {
            padding: 14px 18px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }

        .debug-table tr:last-child td {
            border-bottom: none;
        }

        .debug-table tr:hover td {
            background: rgba(51, 65, 85, 0.3);
        }

        .badge-method {
            font-family: var(--font-code);
            font-weight: 700;
            font-size: 0.75rem;
            padding: 3px 8px;
            border-radius: 6px;
            text-transform: uppercase;
        }

        .badge-method.GET {
            background: rgba(59, 130, 246, 0.2);
            color: #60a5fa;
        }

        .badge-method.POST {
            background: rgba(16, 185, 129, 0.2);
            color: #34d399;
        }

        .badge-method.PUT {
            background: rgba(245, 158, 11, 0.2);
            color: #fbbf24;
        }

        .badge-method.DELETE {
            background: rgba(239, 68, 68, 0.2);
            color: #f87171;
        }

        .badge-status {
            font-family: var(--font-code);
            font-weight: 700;
            font-size: 0.8rem;
        }

        .badge-status.s2xx {
            color: #34d399;
        }

        .badge-status.s3xx {
            color: #60a5fa;
        }

        .badge-status.s4xx {
            color: #fbbf24;
        }

        .badge-status.s5xx {
            color: #f87171;
        }

        .code-pill {
            font-family: var(--font-code);
            background: rgba(15, 23, 42, 0.9);
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 0.8rem;
            color: #a78bfa;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        /* Progress bar */
        .progress-bar-container {
            width: 100%;
            height: 8px;
            background: rgba(15, 23, 42, 0.8);
            border-radius: 999px;
            overflow: hidden;
            margin-top: 8px;
        }

        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--accent-purple), var(--accent-pink));
            border-radius: 999px;
            transition: width 0.3s ease;
        }
    </style>
</head>

<body>
    <div class="background-glow glow-1"></div>
    <div class="background-glow glow-2"></div>

    <nav class="navbar">
        <div class="container navbar-content">
            <div class="brand">
                <span
                    style="font-size: 1.5rem; font-weight: 800; background: linear-gradient(135deg, #a78bfa, #f472b6); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">⚡
                    LilaPHP</span>
                <span class="brand-badge">DEBUG & MONITORING</span>
            </div>
            <div class="nav-links" style="display: flex; gap: 16px; align-items: center;">
                <a href="/" class="btn btn-secondary" style="font-size: 0.85rem; padding: 6px 14px;">← Back to Home</a>
                <button id="btn-purge-cache" class="btn btn-secondary" style="font-size: 0.85rem; padding: 6px 14px;">🧹
                    Purge Cache</button>
            </div>
        </div>
    </nav>

    <main class="container" style="padding-top: 30px; padding-bottom: 60px;">

        <!-- Section 1: System Health Grid -->
        <h2
            style="font-size: 1.4rem; font-weight: 700; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
            🩺 System Health & Service Status
        </h2>

        <div class="metrics-grid">
            <!-- Redis Card -->
            <div class="metric-card">
                <div class="metric-header">
                    <span class="metric-title">Redis Cache Engine</span>
                    <span id="pill-redis" class="status-pill offline"><span class="status-dot"></span> Offline</span>
                </div>
                <div id="val-redis-mem" class="metric-val">--</div>
                <div id="sub-redis" class="metric-sub">Latency: -- ms | Keys: --</div>
            </div>

            <!-- MySQL Card -->
            <div class="metric-card">
                <div class="metric-header">
                    <span class="metric-title">MySQL Database</span>
                    <span id="pill-db" class="status-pill offline"><span class="status-dot"></span> Offline</span>
                </div>
                <div id="val-db-ping" class="metric-val">-- ms</div>
                <div id="sub-db" class="metric-sub">Driver: mysql | Version: --</div>
            </div>

            <!-- PHP-FPM Card -->
            <div class="metric-card">
                <div class="metric-header">
                    <span class="metric-title">PHP-FPM / OPcache</span>
                    <span class="status-pill online"><span class="status-dot"></span> Active</span>
                </div>
                <div id="val-php-mem" class="metric-val">-- MB</div>
                <div id="sub-php" class="metric-sub">PHP v-- | Memory Peak: -- MB</div>
            </div>

            <!-- System CPU/Disk Card -->
            <div class="metric-card">
                <div class="metric-header">
                    <span class="metric-title">System & Host Specs</span>
                    <span id="pill-container" class="status-pill online"><span class="status-dot"></span> Running</span>
                </div>
                <div id="val-cpu-load" class="metric-val">-- Load</div>
                <div id="sub-disk" class="metric-sub">Free Disk: -- GB | Used: --%</div>
            </div>
        </div>

        <!-- Section 1.5: Live Docker Container Stats (Nginx, PHP, Redis, MySQL) -->
        <div id="docker-stats-container"
            style="display: none; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 20px; backdrop-filter: blur(14px); margin-bottom: 30px;">
            <div
                style="font-size: 0.95rem; font-weight: 700; color: var(--text-primary); margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                🐳 Docker Container Cluster Live Resource Stats
            </div>
            <div id="docker-stats-grid"
                style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px;">
            </div>
        </div>

        <!-- Section 2: Interactive Concurrency Benchmark Tool -->
        <div
            style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 28px; backdrop-filter: blur(16px); margin-bottom: 36px; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
            <div
                style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
                <div>
                    <h3
                        style="font-size: 1.3rem; font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 10px;">
                        ⚡ Server-Side Concurrency & Stress Test Runner
                        <span
                            style="font-size: 0.75rem; background: rgba(167, 139, 250, 0.15); color: #a78bfa; border: 1px solid rgba(167, 139, 250, 0.3); padding: 2px 8px; border-radius: 12px; font-weight: 600;">curl_multi
                            engine</span>
                    </h3>
                    <p style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 4px;">
                        Execute OS-level concurrent HTTP load tests via PHP CLI worker (`curl_multi`) against any
                        LilaPHP route.
                    </p>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button id="bench-start" class="btn btn-primary">🚀 Start Benchmark</button>
                    <button id="bench-stop" class="btn btn-danger" disabled style="opacity: 0.5;">🛑 Stop</button>
                </div>
            </div>

            <div class="controls-row">
                <div class="form-group" style="flex: 2; min-width: 250px;">
                    <label class="form-label" for="bench-route-select">Target Route / Endpoint:</label>
                    <select id="bench-route-select" class="form-select">
                        <option value="/api">Loading available routes...</option>
                    </select>
                </div>

                <div class="form-group" style="flex: 1; min-width: 140px;">
                    <label class="form-label" for="bench-concurrency">Concurrency Level:</label>
                    <select id="bench-concurrency" class="form-select">
                        <option value="1">1 Connection</option>
                        <option value="10">10 Connections</option>
                        <option value="100" selected>100 Connections</option>
                        <option value="200">200 Connections</option>
                        <option value="300">300 Connections</option>
                        <option value="500">500 Connections</option>
                        <option value="800">800 Connections</option>
                        <option value="1000">1000 Connections</option>
                        <option value="1200">1200 Connections</option>
                        <option value="1500">1500 Connections</option>
                    </select>
                </div>

                <div class="form-group" style="flex: 1; min-width: 130px;">
                    <label class="form-label" for="bench-duration">Duration (sec):</label>
                    <input id="bench-duration" type="number" class="form-input" value="5" min="1" max="60">
                </div>
            </div>

            <!-- Benchmark Real-time Metrics Panel -->
            <div id="bench-results-panel"
                style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 16px; margin-top: 20px; background: rgba(15, 23, 42, 0.6); padding: 18px; border-radius: 14px; border: 1px solid var(--border-color);">
                <div>
                    <div style="font-size: 0.8rem; color: var(--text-secondary); font-weight: 600;">REQ / SECOND</div>
                    <div id="bench-val-rps"
                        style="font-size: 1.6rem; font-weight: 700; color: #a78bfa; font-family: var(--font-code);">0
                    </div>
                </div>
                <div>
                    <div style="font-size: 0.8rem; color: var(--text-secondary); font-weight: 600;">AVG LATENCY</div>
                    <div id="bench-val-latency"
                        style="font-size: 1.6rem; font-weight: 700; color: #38bdf8; font-family: var(--font-code);">0 ms
                    </div>
                </div>
                <div>
                    <div style="font-size: 0.8rem; color: var(--text-secondary); font-weight: 600;">P50 LATENCY</div>
                    <div id="bench-val-p50"
                        style="font-size: 1.6rem; font-weight: 700; color: #facc15; font-family: var(--font-code);">0 ms
                    </div>
                </div>
                <div>
                    <div style="font-size: 0.8rem; color: var(--text-secondary); font-weight: 600;">P95 / P99</div>
                    <div id="bench-val-p95p99"
                        style="font-size: 1.3rem; font-weight: 700; color: #fb923c; font-family: var(--font-code); line-height: 1.3;">
                        0 / 0 ms
                    </div>
                </div>
                <div>
                    <div style="font-size: 0.8rem; color: var(--text-secondary); font-weight: 600;">TOTAL REQUESTS</div>
                    <div id="bench-val-total"
                        style="font-size: 1.6rem; font-weight: 700; color: var(--text-primary); font-family: var(--font-code);">
                        0</div>
                </div>
                <div>
                    <div style="font-size: 0.8rem; color: var(--text-secondary); font-weight: 600;">SUCCESS (2XX)</div>
                    <div id="bench-val-success"
                        style="font-size: 1.6rem; font-weight: 700; color: #34d399; font-family: var(--font-code);">0
                        (100%)</div>
                </div>
                <div>
                    <div style="font-size: 0.8rem; color: var(--text-secondary); font-weight: 600;">ERRORS (4XX/5XX)
                    </div>
                    <div id="bench-val-errors"
                        style="font-size: 1.6rem; font-weight: 700; color: #f87171; font-family: var(--font-code);">0
                    </div>
                </div>
            </div>

            <div class="progress-bar-container" style="margin-top: 16px;">
                <div id="bench-progress-bar" class="progress-bar-fill" style="width: 0%;"></div>
            </div>
        </div>

        <!-- Section 3: Live Redis Request Log Feed -->
        <div
            style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
            <div>
                <h3
                    style="font-size: 1.3rem; font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 10px;">
                    📝 Live Request Feed (Redis Stream Log)
                </h3>
                <p style="color: var(--text-secondary); font-size: 0.85rem;">
                    Real-time request stream logged in Redis (<span id="log-status-msg"
                        style="color: #a78bfa; font-weight: 600;">DEBUG_LOGGING_ENABLED</span>).
                </p>
            </div>
            <div style="display: flex; gap: 10px; align-items: center;">
                <label
                    style="font-size: 0.85rem; color: var(--text-secondary); display: flex; align-items: center; gap: 6px; cursor: pointer;">
                    <input type="checkbox" id="chk-auto-refresh" checked style="accent-color: var(--accent-purple);">
                    Auto-Refresh (2s)
                </label>
                <button id="btn-clear-logs" class="btn btn-secondary" style="font-size: 0.85rem; padding: 6px 12px;">🗑️
                    Clear Logs</button>
            </div>
        </div>

        <!-- Log Search & Filters Toolbar -->
        <div
            style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 14px; padding: 16px; margin-bottom: 16px; display: flex; flex-wrap: wrap; gap: 12px; align-items: center; backdrop-filter: blur(12px);">
            <div style="flex: 2; min-width: 200px;">
                <input type="text" id="filter-uri" class="form-input"
                    placeholder="🔍 Search by Route / URI (e.g. /api/init)..." style="width: 100%;">
            </div>
            <div style="flex: 1; min-width: 130px;">
                <select id="filter-method" class="form-select" style="width: 100%;">
                    <option value="">Method: All</option>
                    <option value="GET">GET</option>
                    <option value="POST">POST</option>
                    <option value="PUT">PUT</option>
                    <option value="DELETE">DELETE</option>
                </select>
            </div>
            <div style="flex: 1; min-width: 130px;">
                <select id="filter-status" class="form-select" style="width: 100%;">
                    <option value="">Status: All</option>
                    <option value="2xx">2xx (Success)</option>
                    <option value="3xx">3xx (Redirect)</option>
                    <option value="4xx">4xx (Client Error)</option>
                    <option value="5xx">5xx (Server Error)</option>
                </select>
            </div>
            <div style="flex: 1; min-width: 140px;">
                <input type="text" id="filter-ip" class="form-input" placeholder="🔍 Search by IP..."
                    style="width: 100%;">
            </div>
        </div>

        <div class="table-container">
            <table class="debug-table">
                <thead>
                    <tr>
                        <th data-sort="timestamp" style="cursor: pointer; user-select: none;">Time <span
                                id="sort-icon-timestamp">▼</span></th>
                        <th data-sort="method" style="cursor: pointer; user-select: none;">Method <span
                                id="sort-icon-method">↕</span></th>
                        <th data-sort="uri" style="cursor: pointer; user-select: none;">Route / URI <span
                                id="sort-icon-uri">↕</span></th>
                        <th data-sort="duration_ms" style="cursor: pointer; user-select: none;">Duration <span
                                id="sort-icon-duration_ms">↕</span></th>
                        <th data-sort="memory_mb" style="cursor: pointer; user-select: none;">Peak RAM <span
                                id="sort-icon-memory_mb">↕</span></th>
                        <th data-sort="status" style="cursor: pointer; user-select: none;">Status <span
                                id="sort-icon-status">↕</span></th>
                        <th data-sort="ip" style="cursor: pointer; user-select: none;">Client IP <span
                                id="sort-icon-ip">↕</span></th>
                    </tr>
                </thead>
                <tbody id="logs-tbody">
                    <tr>
                        <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 30px;">
                            Loading recent request logs from Redis...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination Controls -->
        <div
            style="display: flex; justify-content: space-between; align-items: center; margin-top: 16px; flex-wrap: wrap; gap: 12px; font-size: 0.9rem; color: var(--text-secondary);">
            <div id="pagination-info">Showing 0 - 0 of 0 requests</div>

            <div style="display: flex; align-items: center; gap: 12px;">
                <label style="display: flex; align-items: center; gap: 6px;">
                    Per page:
                    <select id="select-page-size" class="form-select" style="padding: 4px 8px; font-size: 0.85rem;">
                        <option value="10" selected>10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </label>

                <div style="display: flex; gap: 6px;">
                    <button id="btn-page-prev" class="btn btn-secondary" style="padding: 4px 12px; font-size: 0.85rem;"
                        disabled>◀ Previous</button>
                    <span id="pagination-page-num"
                        style="padding: 4px 8px; font-family: var(--font-code); font-weight: 600; color: #a78bfa;">Page
                        1/1</span>
                    <button id="btn-page-next" class="btn btn-secondary" style="padding: 4px 12px; font-size: 0.85rem;"
                        disabled>Next ▶</button>
                </div>
            </div>
        </div>

    </main>

    <footer class="footer">
        <div class="container footer-content">
            <div>⚡ LilaPHP Framework Debugger - High-Performance PHP Engine</div>
            <div class="footer-meta">OPcache + APCu + Redis Powered</div>
        </div>
    </footer>

    <script>
        const API_HEALTH = '/api/debug/health';
        const API_METRICS = '/api/debug/metrics';
        const API_ROUTES = '/api/debug/routes';
        const API_CLEAR = '/api/debug/clear';

        let isBenchmarking = false;
        let benchAbortController = null;
        let healthTimer = null;

        let rawLogRequests = [];
        let filteredLogRequests = [];
        let currentPage = 1;
        let pageSize = 10;
        let sortColumn = 'timestamp';
        let sortDirection = 'desc';

        document.addEventListener('DOMContentLoaded', () => {
            fetchHealth();
            fetchMetrics();
            fetchRoutes();

            healthTimer = setInterval(() => {
                fetchHealth();
                if (document.getElementById('chk-auto-refresh').checked) {
                    fetchMetrics();
                }
            }, 2500);

            document.getElementById('bench-start').addEventListener('click', startBenchmark);
            document.getElementById('bench-stop').addEventListener('click', stopBenchmark);
            document.getElementById('btn-clear-logs').addEventListener('click', clearLogs);
            document.getElementById('btn-purge-cache').addEventListener('click', purgeCache);

            document.getElementById('filter-uri').addEventListener('input', applyLogFilters);
            document.getElementById('filter-method').addEventListener('change', applyLogFilters);
            document.getElementById('filter-status').addEventListener('change', applyLogFilters);
            document.getElementById('filter-ip').addEventListener('input', applyLogFilters);

            document.querySelectorAll('.debug-table th[data-sort]').forEach(th => {
                th.addEventListener('click', () => {
                    const col = th.getAttribute('data-sort');
                    handleSort(col);
                });
            });

            document.getElementById('select-page-size').addEventListener('change', (e) => {
                pageSize = parseInt(e.target.value);
                currentPage = 1;
                renderLogsTable();
            });

            document.getElementById('btn-page-prev').addEventListener('click', () => {
                if (currentPage > 1) {
                    currentPage--;
                    renderLogsTable();
                }
            });

            document.getElementById('btn-page-next').addEventListener('click', () => {
                const totalPages = Math.ceil(filteredLogRequests.length / pageSize) || 1;
                if (currentPage < totalPages) {
                    currentPage++;
                    renderLogsTable();
                }
            });
        });

        function handleSort(col) {
            if (sortColumn === col) {
                sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                sortColumn = col;
                sortDirection = (col === 'duration_ms' || col === 'memory_mb' || col === 'status') ? 'desc' : 'asc';
            }
            updateSortHeaderIcons();
            applyLogFilters();
        }

        function updateSortHeaderIcons() {
            const cols = ['timestamp', 'method', 'uri', 'duration_ms', 'memory_mb', 'status', 'ip'];
            cols.forEach(col => {
                const el = document.getElementById(`sort-icon-${col}`);
                if (el) {
                    if (sortColumn === col) {
                        el.textContent = sortDirection === 'asc' ? '▲' : '▼';
                        el.style.color = '#a78bfa';
                    } else {
                        el.textContent = '↕';
                        el.style.color = 'var(--text-muted)';
                    }
                }
            });
        }

        async function fetchHealth() {
            try {
                const res = await fetch(API_HEALTH);
                if (!res.ok) return;
                const data = await res.json();

                const statusMsg = document.getElementById('log-status-msg');
                if (data.debug_logging_enabled) {
                    statusMsg.textContent = 'DEBUG_LOGGING_ENABLED=true';
                    statusMsg.style.color = '#34d399';
                } else {
                    statusMsg.textContent = 'DEBUG_LOGGING_ENABLED=false (Enable in backend/.env to log requests)';
                    statusMsg.style.color = '#f87171';
                }

                const pillRedis = document.getElementById('pill-redis');
                const valRedisMem = document.getElementById('val-redis-mem');
                const subRedis = document.getElementById('sub-redis');
                if (data.redis.status === 'online') {
                    pillRedis.className = 'status-pill online';
                    pillRedis.innerHTML = '<span class="status-dot"></span> Online';
                    valRedisMem.textContent = data.redis.used_memory_human || 'OK';
                    subRedis.textContent = `Latency: ${data.redis.ping_ms} ms | Keys: ${data.redis.keys_count}`;
                } else {
                    pillRedis.className = 'status-pill offline';
                    pillRedis.innerHTML = '<span class="status-dot"></span> Offline';
                    valRedisMem.textContent = 'N/A';
                    subRedis.textContent = 'Redis disconnected';
                }

                const pillDb = document.getElementById('pill-db');
                const valDbPing = document.getElementById('val-db-ping');
                const subDb = document.getElementById('sub-db');
                if (data.database.status === 'online') {
                    pillDb.className = 'status-pill online';
                    pillDb.innerHTML = '<span class="status-dot"></span> Online';
                    valDbPing.textContent = `${data.database.ping_ms} ms`;
                    subDb.textContent = `Driver: ${data.database.driver} v${data.database.version}`;
                } else {
                    pillDb.className = 'status-pill offline';
                    pillDb.innerHTML = '<span class="status-dot"></span> Offline';
                    valDbPing.textContent = 'N/A';
                    subDb.textContent = 'MySQL disconnected';
                }

                document.getElementById('val-php-mem').textContent = `${data.php.memory_used_mb} MB`;
                document.getElementById('sub-php').textContent = `PHP v${data.php.version} | Peak: ${data.php.peak_memory_mb} MB`;

                const pillContainer = document.getElementById('pill-container');
                const valCpuLoad = document.getElementById('val-cpu-load');
                const subDisk = document.getElementById('sub-disk');

                if (data.system.is_container && data.system.container_memory) {
                    pillContainer.className = 'status-pill online';
                    pillContainer.innerHTML = '<span class="status-dot"></span> Docker';
                    valCpuLoad.textContent = `${data.system.container_memory.used_mb} MB`;
                    subDisk.textContent = `Docker RAM Limit: ${data.system.container_memory.limit_mb} | CPU Load: ${data.system.cpu_load_1m}`;
                } else {
                    pillContainer.className = 'status-pill online';
                    pillContainer.innerHTML = '<span class="status-dot"></span> System';
                    valCpuLoad.textContent = `${data.system.cpu_load_1m} Load`;
                    subDisk.textContent = `Free Disk: ${data.system.disk_free_gb} GB | Used: ${data.system.disk_used_percent}%`;
                }

                const dockerContainer = document.getElementById('docker-stats-container');
                const dockerGrid = document.getElementById('docker-stats-grid');
                if (data.system.containers && data.system.containers.length > 0) {
                    dockerContainer.style.display = 'block';
                    dockerGrid.innerHTML = data.system.containers.map(c => `
                        <div style="background: rgba(15, 23, 42, 0.8); padding: 12px 16px; border-radius: 10px; border: 1px solid var(--border-color);">
                            <div style="font-size: 0.8rem; font-weight: 700; color: #a78bfa; font-family: var(--font-code);">${escapeHtml(c.name)}</div>
                            <div style="display: flex; justify-content: space-between; margin-top: 6px; font-size: 0.85rem; font-family: var(--font-code);">
                                <span style="color: #38bdf8;">CPU: ${c.cpu}</span>
                                <span style="color: #34d399;">RAM: ${c.mem}</span>
                            </div>
                        </div>
                    `).join('');
                } else {
                    dockerContainer.style.display = 'none';
                }

            } catch (err) {
                console.error('Error fetching health:', err);
            }
        }

        async function fetchMetrics() {
            try {
                const res = await fetch(`${API_METRICS}?limit=200`);
                if (!res.ok) return;
                const data = await res.json();

                rawLogRequests = data.requests || [];
                applyLogFilters();
            } catch (err) {
                console.error('Error fetching metrics:', err);
            }
        }

        function applyLogFilters() {
            const filterUri = document.getElementById('filter-uri').value.toLowerCase().trim();
            const filterMethod = document.getElementById('filter-method').value;
            const filterStatus = document.getElementById('filter-status').value;
            const filterIp = document.getElementById('filter-ip').value.toLowerCase().trim();

            filteredLogRequests = rawLogRequests.filter(req => {
                if (filterUri && !(req.uri || '').toLowerCase().includes(filterUri)) return false;
                if (filterMethod && req.method !== filterMethod) return false;
                if (filterIp && !(req.ip || '').toLowerCase().includes(filterIp)) return false;

                if (filterStatus) {
                    const st = parseInt(req.status || 200);
                    if (filterStatus === '2xx' && (st < 200 || st >= 300)) return false;
                    if (filterStatus === '3xx' && (st < 300 || st >= 400)) return false;
                    if (filterStatus === '4xx' && (st < 400 || st >= 500)) return false;
                    if (filterStatus === '5xx' && st < 500) return false;
                }
                return true;
            });

            filteredLogRequests.sort((a, b) => {
                let valA = a[sortColumn];
                let valB = b[sortColumn];

                if (sortColumn === 'timestamp' || sortColumn === 'microtime') {
                    valA = a.microtime || a.timestamp || '';
                    valB = b.microtime || b.timestamp || '';
                }

                if (typeof valA === 'number' && typeof valB === 'number') {
                    return sortDirection === 'asc' ? valA - valB : valB - valA;
                }

                valA = String(valA || '').toLowerCase();
                valB = String(valB || '').toLowerCase();
                if (valA < valB) return sortDirection === 'asc' ? -1 : 1;
                if (valA > valB) return sortDirection === 'asc' ? 1 : -1;
                return 0;
            });

            currentPage = 1;
            renderLogsTable();
        }

        function renderLogsTable() {
            const tbody = document.getElementById('logs-tbody');
            const total = filteredLogRequests.length;

            if (total === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 30px;">
                            ${rawLogRequests.length === 0 ? 'No requests logged yet. Trigger API requests to view live logs.' : 'No requests matching the applied filters.'}
                        </td>
                    </tr>`;
                updatePaginationControls(0, 0, 0, 1, 1);
                return;
            }

            const totalPages = Math.ceil(total / pageSize) || 1;
            if (currentPage > totalPages) currentPage = totalPages;

            const startIdx = (currentPage - 1) * pageSize;
            const endIdx = Math.min(startIdx + pageSize, total);
            const pageItems = filteredLogRequests.slice(startIdx, endIdx);

            tbody.innerHTML = pageItems.map(req => {
                const stClass = req.status >= 200 && req.status < 300 ? 's2xx' : (req.status >= 400 && req.status < 500 ? 's4xx' : 's5xx');
                return `
                    <tr>
                        <td style="font-family: var(--font-code); color: var(--text-secondary); font-size: 0.8rem;">${req.timestamp ? (req.timestamp.split(' ')[1] || req.timestamp) : '--'}</td>
                        <td><span class="badge-method ${req.method}">${req.method}</span></td>
                        <td style="font-family: var(--font-code); font-weight: 600; color: #a78bfa;">${escapeHtml(req.uri)}</td>
                        <td style="font-family: var(--font-code); font-weight: 700; color: #38bdf8;">${req.duration_ms} ms</td>
                        <td style="font-family: var(--font-code);">${req.memory_mb} MB</td>
                        <td><span class="badge-status ${stClass}">${req.status}</span></td>
                        <td style="font-family: var(--font-code); color: var(--text-muted); font-size: 0.8rem;">${req.ip}</td>
                    </tr>`;
            }).join('');

            updatePaginationControls(startIdx + 1, endIdx, total, currentPage, totalPages);
        }

        function updatePaginationControls(from, to, total, page, totalPages) {
            document.getElementById('pagination-info').textContent = `Showing ${from} - ${to} of ${total} requests`;
            document.getElementById('pagination-page-num').textContent = `Page ${page}/${totalPages}`;

            document.getElementById('btn-page-prev').disabled = (page <= 1);
            document.getElementById('btn-page-prev').style.opacity = (page <= 1) ? '0.5' : '1';

            document.getElementById('btn-page-next').disabled = (page >= totalPages);
            document.getElementById('btn-page-next').style.opacity = (page >= totalPages) ? '0.5' : '1';
        }

        async function fetchRoutes() {
            try {
                const res = await fetch(API_ROUTES);
                if (!res.ok) return;
                const data = await res.json();
                const select = document.getElementById('bench-route-select');

                if (data.routes && data.routes.length > 0) {
                    select.innerHTML = data.routes.map(r =>
                        `<option value="${r.path}">${r.name}</option>`
                    ).join('');
                }
            } catch (err) {
                console.error('Error fetching routes:', err);
            }
        }

        const API_BENCHMARK = '/api/debug/benchmark';
        let currentBenchId = null;
        let benchPollTimer = null;

        async function startBenchmark() {
            if (isBenchmarking) return;

            const selectedRoute = document.getElementById('bench-route-select').value;
            const concurrency = parseInt(document.getElementById('bench-concurrency').value);
            const durationSec = parseInt(document.getElementById('bench-duration').value);

            try {
                const res = await fetch(API_BENCHMARK, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        route: selectedRoute,
                        concurrency: concurrency,
                        duration: durationSec
                    })
                });

                if (!res.ok) {
                    alert('Failed to start server-side benchmark.');
                    return;
                }

                const data = await res.json();
                if (!data.id) {
                    alert('Invalid benchmark response.');
                    return;
                }

                isBenchmarking = true;
                currentBenchId = data.id;

                document.getElementById('bench-start').disabled = true;
                document.getElementById('bench-start').style.opacity = '0.5';
                document.getElementById('bench-stop').disabled = false;
                document.getElementById('bench-stop').style.opacity = '1';

                const progressBar = document.getElementById('bench-progress-bar');
                progressBar.style.width = '0%';

                benchPollTimer = setInterval(pollBenchmarkStatus, 400);

            } catch (err) {
                console.error('Error starting benchmark:', err);
                alert('Error connecting to benchmark endpoint.');
            }
        }

        async function pollBenchmarkStatus() {
            if (!isBenchmarking || !currentBenchId) return;

            try {
                const res = await fetch(`${API_BENCHMARK}?id=${encodeURIComponent(currentBenchId)}`);
                if (!res.ok) return;

                const data = await res.json();
                if (data.status === 'not_found') return;

                const progressBar = document.getElementById('bench-progress-bar');
                const pct = Math.min(100, data.progress_pct || 0);
                progressBar.style.width = `${pct}%`;

                document.getElementById('bench-val-rps').textContent = (data.rps || 0).toLocaleString();
                document.getElementById('bench-val-latency').textContent = `${data.avg_ms || 0} ms`;
                document.getElementById('bench-val-p50').textContent = `${data.p50_ms || 0} ms`;
                document.getElementById('bench-val-p95p99').textContent = `${data.p95_ms || 0} / ${data.p99_ms || 0} ms`;
                document.getElementById('bench-val-total').textContent = (data.total_requests || 0).toLocaleString();

                const totalReqs = data.total_requests || 0;
                const successCount = data.success_count || 0;
                const pctSuccess = totalReqs > 0 ? Math.round((successCount / totalReqs) * 100) : 100;

                document.getElementById('bench-val-success').textContent = `${successCount.toLocaleString()} (${pctSuccess}%)`;
                document.getElementById('bench-val-errors').textContent = (data.error_count || 0).toLocaleString();

                if (data.status === 'completed') {
                    resetBenchmarkUI();
                }
            } catch (err) {
                console.error('Error polling benchmark status:', err);
            }
        }

        async function stopBenchmark() {
            if (!isBenchmarking || !currentBenchId) return;

            try {
                await fetch(API_BENCHMARK, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'stop',
                        id: currentBenchId
                    })
                });
            } catch (e) {
                console.error('Error sending stop signal:', e);
            }

            resetBenchmarkUI();
        }

        function resetBenchmarkUI() {
            isBenchmarking = false;
            if (benchPollTimer) {
                clearInterval(benchPollTimer);
                benchPollTimer = null;
            }

            document.getElementById('bench-start').disabled = false;
            document.getElementById('bench-start').style.opacity = '1';
            document.getElementById('bench-stop').disabled = true;
            document.getElementById('bench-stop').style.opacity = '0.5';
            document.getElementById('bench-progress-bar').style.width = '100%';

            setTimeout(fetchMetrics, 500);
        }

        async function clearLogs() {
            if (!confirm('Do you want to clear request log history from Redis?')) return;
            try {
                await fetch(`${API_CLEAR}?target=logs`);
                fetchMetrics();
            } catch (e) {
                alert('Error clearing logs.');
            }
        }

        async function purgeCache() {
            if (!confirm('Do you want to purge all APCu and Redis cache?')) return;
            try {
                await fetch(`${API_CLEAR}?target=cache`);
                alert('Cache purged successfully.');
                fetchHealth();
            } catch (e) {
                alert('Error purging cache.');
            }
        }

        function escapeHtml(str) {
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }
    </script>
</body>

</html>