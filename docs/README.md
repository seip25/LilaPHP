# LilaPHP Technical Architecture & Reference Guide

Welcome to the technical reference for **LilaPHP**, an ultra-fast, zero-dependency PHP 8.4+ full-stack framework engineered for high concurrency, unified server rendering, and high-throughput REST APIs.

---

## 🚀 Architectural Highlights

### 1. Dual File-Based Routing & Native Dispatcher
- **Web Views**: Files in `app/routes/*.php` map directly to URI paths (e.g., `GET /` &rarr; `app/routes/index.php`, `GET /about` &rarr; `app/routes/about.php`), rendering templates via `Core\View`.
- **REST APIs**: Files in `app/routes/api/*.php` map directly to `/api/*` endpoints (e.g., `GET /api/users` &rarr; `app/routes/api/users.php`), outputting pure JSON via `Core\Response`.
- **Automatic 405 Method Not Allowed**: LilaPHP dynamically tracks declared HTTP verbs (`Request::GET`, `Request::POST`, etc.). If an unhandled method is requested, it automatically emits `405 Method Not Allowed` with an `Allow` header and renders `app/views/405.php` or JSON error response without requiring manual fallback routes.

### 2. Native View Engine (`Core\View`) & Bluebird CSS
- **Zero-Compiler Overhead**: Native PHP templates in `app/views/` executed directly by OPcache with output buffering.
- **Layout Inheritance**: Templates are wrapped in a shared layout (`app/views/layout.php`) or custom layouts per route. Embed mode (`['layout' => false]`) supports iframe integration.
- **Heredoc Execution**: Render closures or heredoc templates with `View::html()`.
- **Reusable Partials**: Component includes via `View::partial($name, $data)`.
- **Tiered Multi-Driver Caching**: APCu RAM &rarr; Redis cluster &rarr; File system storage (`_core/cache/views/`).
- **Asset Versioning**: Cache busting with automatic file modification fingerprinting via `View::asset('/css/bluebird.css')`.
- **SEO & PWA**: Automated title, description, keywords, canonical tags, OpenGraph metadata, JSON-LD structured schemas, and PWA install manifests.
- **Built-in CSRF**: Auto-injected `$csrf_input`, `$csrf_token`, and `$csrf_meta` in all templates.
- **Development Hot Reload**: In-browser live refresh on view or style modifications in debug mode.

### 3. Multi-Driver Database Pool & Switching
- Supports **MySQL** and **SQLite** out of the box via `Core\Database` and `Core\DB`.
- Instant CLI switching via `php cli.php db:switch <sqlite|mysql>`, which updates the root `.env` and clears cached configurations.
- Standalone SQLite deployment profile: `docker-compose.sqlite.yml` runs without MySQL containers for minimal RAM footprint.

### 4. Zero-Dependency Core & OPcache Preloading
- In production, `app/preload.php` compiles all framework classes (`Config`, `Database`, `Response`, `Cache`, `Validate`, `Security`, `Logger`, `Dispatcher`, `Upload`, `Task`, `Http`, `View`) directly into shared worker RAM.
- No heavy third-party vendor dependencies required to boot or execute requests.

---

## 📂 Repository Layout

```text
LilaPHP/
├── .env                       # Root environment configuration
├── .env_example               # Environment configuration template
├── index.php                  # Local development front controller & static router
├── cli.php                    # Master CLI dispatcher
├── docker-compose.yml         # Standard container stack (Nginx, PHP-FPM, MySQL, Redis)
├── docker-compose.sqlite.yml  # Low-RAM SQLite standalone container stack
├── public/                    # Static Webroot
│   ├── css/
│   │   └── bluebird.css       # Semantic CSS micro-framework
│   ├── manifest.json          # PWA manifest
│   ├── sitemap.xml            # Generated XML sitemap
│   └── robots.txt             # Generated crawler directives
├── app/                       # Application Core
│   ├── index.php              # FastCGI entry point
│   ├── preload.php            # OPcache preloading script
│   ├── routes/                # Web view route handlers
│   │   ├── index.php          # GET /
│   │   ├── about.php          # GET /about
│   │   └── api/               # REST API endpoints
│   │       ├── index.php      # GET /api
│   │       ├── health.php     # GET /api/health
│   │       └── users.php      # /api/users
│   ├── views/                 # Native PHP view templates
│   │   ├── layout.php         # Default document layout
│   │   ├── index.php          # Home view template
│   │   ├── about.php          # About view template
│   │   ├── 404.php            # Not Found error template
│   │   └── 405.php            # Method Not Allowed error template
│   ├── models/                # ORM entities and migration schemas
│   └── database/              # SQLite database storage (app.sqlite)
├── _core/                     # Zero-dependency Core Framework Engine
│   ├── bootstrap.php          # PSR-4 autoloader and runtime initializer
│   ├── helpers.php            # Global helper functions
│   ├── Config.php             # Environment loader and static config cache
│   ├── Dispatcher.php         # Dual route dispatcher and 404/405 handler
│   ├── View.php               # Native template engine
│   ├── Database.php           # PDO multi-driver connection pool
│   ├── Request.php            # HTTP request inspector and method router
│   ├── Response.php           # Response emitter with CORS and headers
│   ├── Security.php           # CSRF, rate limiter, encryption
│   ├── Validate.php           # Multilingual payload validation
│   └── cli/                   # Modular CLI command implementations
└── docs/                      # Technical GitHub Pages documentation
```

---

## 🛠️ Key Components & Usage

### 🎨 Rendering Views (`Core\View`)

```php
use Core\Request;
use Core\View;

Request::GET(function () {
    View::render('about', [
        'team' => ['Alice', 'Bob']
    ], [
        'title'       => 'About Us — LilaPHP',
        'description' => 'Learn about our engineering philosophy.',
        'keywords'    => 'php, framework, performance',
        'canonical'   => 'https://example.com/about',
        'cache'       => 300
    ]);
});
```

### 📡 API Route Handlers (`Core\Request`)

```php
use Core\Request;
use Core\Response;
use Models\User;

Request::GET(function () {
    return Response::json(['users' => User::all()]);
});

Request::POST([AuthMiddleware::class], function () {
    $user = new User(Request::json());
    $user->assertValid();
    $user->save();
    return Response::json(['status' => 'created', 'data' => $user], 201);
});
```

### 🛡️ CSRF Form Protection in Views

```php
<form method="POST" action="/contact">
    <?= $csrf_input ?>
    <input type="email" name="email" required>
    <button type="submit">Submit</button>
</form>

<?php
use Core\Request;
use Core\Security;

Request::POST(function () {
    if (!Security::csrfVerify(Request::input('_csrf'))) {
        abort(403, 'Invalid CSRF token');
    }
});
```

### 📦 Anti-Malware File Uploads (`Core\Upload`)

```php
use Core\Upload;
use Core\Response;

$saved = Upload::save($_FILES['avatar'], 5242880, [
    'image/png'  => 'png',
    'image/webp' => 'webp'
]);

if (!$saved) {
    Response::error('Invalid file upload or malware detected', 422);
}

Response::json(['filename' => $saved]);
```

### ⏳ Asynchronous Background Tasks (`Core\Task`)

```php
use Core\Task;

Task::dispatch('send_invoice_email', [
    'user_id' => 123,
    'invoice_id' => 'INV-8891'
]);
```

---

## ⚡ Master CLI Command Reference (`cli.php`)

| Command | Description |
| :--- | :--- |
| `php cli.php dev [port]` | Start local development server with live Hot Reload on views/css |
| `php cli.php db:switch <sqlite\|mysql>` | Switch active database provider, update `.env`, and clear OPcache |
| `php cli.php make:api <Name>` | Scaffold complete REST API resource controller in `app/routes/api/` |
| `php cli.php make:crud <Name> [--embed]` | Scaffold Model, API route, View template, and Web route |
| `php cli.php make:route <path>` | Scaffold new web route in `app/routes/` |
| `php cli.php make:model <Name>` | Scaffold new database model in `app/models/` |
| `php cli.php sitemap` | Generate `public/sitemap.xml` scanning routes (respects `$noIndex`) |
| `php cli.php robots` | Generate `public/robots.txt` with Sitemap directive and Disallow paths |
| `php cli.php doctor` | Run comprehensive pre-flight verification across views, config, and DB |
| `php cli.php health` | Microsecond system health checks (DB, Redis, APCu, storage) |
| `php cli.php migrate [--refresh]` | Synchronize database tables automatically from `app/models/` |
| `php cli.php seed` | Execute database seeders |
| `php cli.php task:work` | Run continuous background Redis queue worker |
| `php cli.php optimize` | Pre-compile `.env` configuration to OPcache and flush cache pools |
| `php cli.php key:generate` | Generate cryptographically secure 256-bit `APP_KEY` in `.env` |
| `php cli.php docker dev` | Launch development Docker container cluster |
| `php cli.php docker sqlite` | Launch low-RAM standalone SQLite Docker cluster |
| `php cli.php docker prod` | Launch production Docker container cluster |
| `php cli.php docker stats` | Stream live CPU and memory utilization of LilaPHP containers |
