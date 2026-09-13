<p align="center">
  <h1 align="center">⚡ LilaPHP — High-Performance Full-Stack &amp; Micro-API Framework</h1>
  <p align="center">
    <strong>Zero-Dependency PHP 8.4+ Architecture with Native View Engine, Dual REST/Web Routing, Tiered Caching, and Bluebird CSS.</strong>
  </p>
</p>

<p align="center">
  <a href="https://seip25.github.io/LilaPHP/"><img src="https://img.shields.io/badge/docs-online-blue.svg" alt="Documentation"></a>
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-MIT-green.svg" alt="License"></a>
  <a href="#"><img src="https://img.shields.io/badge/PHP-8.4+-777bb4.svg" alt="PHP 8.4+"></a>
  <a href="#"><img src="https://img.shields.io/badge/Frontend-Bluebird_CSS_%26_JS-38bdf8.svg" alt="Bluebird CSS"></a>
  <a href="#"><img src="https://img.shields.io/badge/Database-MySQL_%7C_SQLite_%7C_PostgreSQL-f59e0b.svg" alt="Database"></a>
</p>

---

## 📖 Live Documentation & Interactive Demo
- **Live Documentation:** [https://seip25.github.io/LilaPHP/](https://seip25.github.io/LilaPHP/)
- **Repository:** [https://github.com/seip25/LilaPHP](https://github.com/seip25/LilaPHP)

---

## 🏗️ Architecture & Core Philosophy (KISS)

LilaPHP unifies web page rendering and JSON micro-APIs into a clean, lightning-fast architecture with **zero external composer dependencies**:

1. **Public Webroot (`public/`):**
   - High-speed static delivery (`.css`, `.js`, images, icons, fonts, `manifest.json`).
   - Served directly by **Nginx** in production with raw sendfile speed, or through the front controller in development.
   - Powered by the **Bluebird CSS** micro-framework with native dark/light modes (`[data-theme="dark"]`) and the **Bluebird JS** suite.
2. **Dual Route Dispatcher (`_core/Dispatcher.php`):**
   - **Web View Routes (`app/routes/*.php`):** Handle front-end URLs (e.g. `GET /`, `GET /about`) and render responsive HTML views with layout inheritance.
   - **REST API Endpoints (`app/routes/api/*.php`):** Handle JSON endpoints (e.g. `/api/health`, `/api/users/{id}`) with automated CORS headers.
   - **Automatic 405 Method Not Allowed:** When a request reaches a route file without matching the HTTP verb, the framework responds automatically with a standardized 405 status code and `Allow` header.
3. **Native View Engine (`_core/View.php`):**
   - Master layout wrapping (`app/views/layout.php`) with per-route layout overrides or embed mode (`['layout' => false]`).
   - Scoped heredoc components via `View::html(fn() => <<<HTML ... HTML)`.
   - Automatic CSRF protection (`csrf_token`, `csrf_input`, `csrf_meta`).
   - Automated SEO metadata (`title`, `description`, `keywords`, `canonical`, `jsonLD`) and PWA web app manifest integration.
   - Built-in live Hot Reload watcher in development mode.
4. **Tiered Caching Hierarchy (`_core/Cache.php`):**
   - Multi-layer caching prioritizing **APCu RAM** &rarr; **Redis cluster** &rarr; **Persistent File Cache**.
5. **Multi-Driver Database (`Core\Database` / `DB::*`):**
   - Out-of-the-box support for **MySQL** (default) and portable standalone **SQLite (WAL mode)**.
   - Switch drivers instantly via CLI: `php cli.php db:switch sqlite` or `php cli.php db:switch mysql`.
   - Dedicated Docker Compose standalone SQLite profile (`docker-compose.sqlite.yml`).

```text
LilaPHP/
├── .env                       # Root environment configuration
├── .env_example               # Environment variables template
├── .htaccess                  # Apache rules & public asset routing
├── index.php                  # Unified front controller & static router
├── cli.php                    # Master CLI command dispatcher
├── docker-compose.yml         # Container stack (Nginx, PHP 8.4, MySQL 8, Redis 7)
├── docker-compose.sqlite.yml  # Standalone SQLite override profile
├── public/                    # Static Webroot
│   ├── favicon.ico            # Favicon
│   ├── manifest.json          # PWA Web App Manifest
│   ├── css/
│   │   └── bluebird.css       # Semantic CSS framework
│   ├── js/
│   │   └── bluebird.js        # Bluebird UI & client library
│   └── images/                # Static brand assets
├── app/                       # Application Source
│   ├── index.php              # FastCGI entry point
│   ├── preload.php            # OPcache preloading script
│   ├── routes/                # Web View Routes
│   │   ├── index.php          # GET /
│   │   ├── about.php          # GET /about
│   │   └── api/               # REST API Routes
│   │       ├── index.php      # GET /api
│   │       ├── health.php     # GET /api/health
│   │       └── users.php      # /api/users CRUD resource
│   ├── views/                 # View Templates
│   │   ├── layout.php         # Master HTML5 layout
│   │   ├── index.php          # Home view template
│   │   ├── about.php          # About view template
│   │   ├── 404.php            # Not Found error template
│   │   └── 405.php            # Method Not Allowed template
│   ├── models/                # ORM Models (BaseModel.php, User.php)
│   ├── database/              # SQLite database storage (app.sqlite)
│   ├── services/              # Business logic services
│   └── sockets/               # WebSocket handler scripts
├── _core/                     # Framework Core Engine
│   ├── bootstrap.php          # PSR-4 autoloader, exception handler & compression
│   ├── helpers.php            # Global helpers (view, asset, e, csrf_field, json_response)
│   ├── Config.php             # Environment configuration & OPcache caching
│   ├── Dispatcher.php         # Dual Route Dispatcher & Auto 405 Engine
│   ├── View.php               # Native View Engine with tiered cache & layouts
│   ├── Database.php           # PDO multi-driver wrapper & schema indexer
│   ├── Cache.php              # Multi-tier cache manager (APCu, Redis, FileCache)
│   ├── Request.php            # HTTP request methods, JSON parsing & route matchers
│   ├── Response.php           # JSON, streaming, and CORS emitters
│   ├── Security.php           # CSRF protection, rate limiting, AES-256 encryption
│   ├── Validate.php           # Validation engine with i18n support
│   ├── AI.php                 # Universal LLM Client
│   └── cli/                   # CLI Command implementations
├── docker/                    # Docker infrastructure configs
└── docs/                      # Complete HTML Documentation (GitHub Pages)
```

---

## 🚀 Quick Start in 30 Seconds

### Option A: Local Development Server (with Hot Reload)

Zero container or composer installation required:

```bash
# 1. Clone repository
git clone https://github.com/seip25/LilaPHP.git
cd LilaPHP

# 2. Copy root environment file
cp .env_example .env

# 3. Launch dev server with Hot Reload
php cli.php dev
```

Open [http://localhost:8080](http://localhost:8080) in your browser.

---

### Option B: Docker Orchestration (Production Ready)

```bash
# 1. Build and start full stack (Nginx + PHP 8.4-FPM + MySQL + Redis)
php cli.php docker dev

# Or start standalone SQLite mode (excludes MySQL container):
php cli.php docker sqlite

# 2. Check cluster status
php cli.php docker ps
```

---

## 🎨 Web Views & Native View Engine

### 1. Defining a View Route (`app/routes/about.php`)

```php
<?php

use Core\Request;
use Core\View;

Request::GET(function () {
    View::render('about', [
        'title'       => 'About LilaPHP',
        'description' => 'Learn about our architecture.',
        'keywords'    => 'lilaphp, php framework',
    ]);
});
```

### 2. View Template with Heredoc & Context (`app/views/about.php`)

```php
<?php

use Core\View;

$title = View::escape($title ?? 'About LilaPHP');
$csrfField = $csrf_input ?? '';

echo View::html(function () use ($title, $csrfField) {
    return <<<HTML
<div class="container">
    <h1>{$title}</h1>
    <p>Rendered natively with zero dependencies.</p>
    <form method="POST" action="/submit">
        {$csrfField}
        <button type="submit" class="btn btn-primary">Submit</button>
    </form>
</div>
HTML;
});
```

### 3. Reusable Partials & Asset Cache Busting

```php
// In any template:
<?= View::partial('header', ['user' => $currentUser]) ?>

// Version-fingerprinted asset URL:
<link rel="stylesheet" href="<?= View::asset('/css/bluebird.css') ?>">
// Outputs: /css/bluebird.css?v=214c4952
```

---

## ⚙️ REST API Development

### 1. Physical REST Routing (`app/routes/api/users.php`)

```php
<?php

use Core\Request;
use Core\Response;
use Core\Validate;

$id = $_GET['id'] ?? null;

Request::GET(function () use ($id) {
    if ($id !== null) {
        $user = DB::fetch("SELECT * FROM users WHERE id = :id", ['id' => $id]);
        if (!$user) abort(404, 'User not found');
        Response::json(['data' => $user]);
    }
    Response::json(['data' => DB::fetchAll("SELECT * FROM users ORDER BY id DESC")]);
});

Request::POST(function () {
    $body = Request::json();
    $errors = Validate::check($body, [
        'name'  => 'required|min_length:2',
        'email' => 'required|email'
    ]);
    if (!empty($errors)) Response::error('Validation failed', 422, $errors);

    $id = DB::insert('users', $body);
    Response::json(['status' => 'success', 'id' => $id], 201);
});

Request::DELETE(function () use ($id) {
    DB::delete('users', 'id = :id', ['id' => $id]);
    Response::json(['status' => 'success', 'message' => 'Deleted']);
});
```

---

## 🛠️ Master CLI Dispatcher Reference

LilaPHP includes an extensive suite of CLI developer tools:

```bash
# Development server with live browser hot reload:
php cli.php dev [port]

# Switch active database engine (updates .env & refreshes OPcache):
php cli.php db:switch sqlite
php cli.php db:switch mysql

# Generate search engine files:
php cli.php sitemap   # Generates public/sitemap.xml
php cli.php robots    # Generates public/robots.txt

# Code scaffolding generators:
php cli.php make:model <Name>          # Generates model in app/models/
php cli.php make:route <path>          # Generates web route in app/routes/
php cli.php make:api <Name>            # Generates complete REST resource in app/routes/api/
php cli.php make:crud <Name> [--embed] # Generates DataTable CRUD view & route

# Diagnostics & Health:
php cli.php doctor    # Pre-flight environment, routes, and views check
php cli.php health    # Live database, Redis, and memory diagnostics
```

---

## 📄 License

LilaPHP is open-sourced software licensed under the [MIT license](LICENSE).
