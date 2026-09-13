# 🤖 LilaPHP — Agent & Developer Architecture Guide

This document provides a comprehensive operational guide for AI agents and human developers working on **LilaPHP**.

---

## 🎯 Core Architectural Philosophy (KISS & Performance First)

1. **Decoupled Architecture with Unified Dispatching:**
   - **Public Webroot (`public/`):** Pure static files (`.css`, `.js`, images, icons, fonts, `manifest.json`). Served directly by Nginx in production or the PHP front controller in development with aggressive caching and fingerprinting (`View::asset()`).
   - **Application Core (`app/`):**
     - `app/routes/`: Web View routes returning rendered HTML pages via `View::render()`.
     - `app/routes/api/`: REST API endpoints returning strict JSON responses via `Response::json()`.
     - `app/views/`: Native PHP templates with layout inheritance, partials, tiered caching, and XSS sanitization.
     - `app/models/`: Micro-models with automated schema indexing and validation rules.
     - `app/database/`: SQLite database storage (`app.sqlite`) with WAL mode.
   - **Root Environment (`.env` and `.env_example`):** Located at project root for unified multi-container and standalone configuration.
2. **Dual Routing & Automatic 405 Method Handling:**
   - Requests to `/api/*` are routed to `app/routes/api/*.php`.
   - Requests to `/` or any web URL are routed to `app/routes/*.php`.
   - Automatic 405 Method Not Allowed detection: When an endpoint does not match the incoming HTTP verb, the framework responds automatically with a standardized 405 status and `Allow` header without requiring manual route fallback handlers.
3. **Native View Engine (`Core\View`):**
   - **Layouts & Partials:** Automatic layout wrapping (`app/views/layout.php`), custom layout selection, and reusable sub-components via `View::partial()`.
   - **Context & Security:** Automatic injection of CSRF tokens (`csrf_token`, `csrf_input`, `csrf_meta`) and app metadata.
   - **Tiered Caching:** Multi-layer cache hierarchy prioritizing APCu RAM, Redis cluster, and persistent file caching (`_core/cache/data/`).
   - **SEO & PWA:** Built-in options for `title`, `description`, `keywords`, `canonical`, `jsonLD`, and automatic PWA manifest injection.
   - **Development Hot Reload:** Integrated file modification watcher injected during debug mode for instant browser reloads on template or route edits.
4. **Multi-Driver Database (`Core\Database` / `Core\DB`):**
   - Default driver: **MySQL** (`DB_TYPE=mysql`).
   - Alternative: **SQLite** (`DB_TYPE=sqlite`, `DB_FILE=app/database/app.sqlite`) with WAL mode.
   - Dynamic CLI switching: `php cli.php db:switch sqlite` and `php cli.php db:switch mysql`.
   - Docker SQLite profile: `docker compose -f docker-compose.yml -f docker-compose.sqlite.yml up -d`.
5. **Frontend Suite (`Bluebird CSS` + `Bluebird JS`):**
   - **`public/css/bluebird.css`:** Modern semantic CSS framework with native dark/light mode support (`[data-theme="dark"]`).
   - **`public/js/bluebird.js`:** Lightweight client utility library with toasts, dialogs, HTTP client, and responsive DataTables.

---

## 📂 Repository Layout

```text
LilaPHP/
├── .env                       # Active root environment variables
├── .env_example               # Environment template
├── .htaccess                  # Apache rewrite rules & public asset routing
├── index.php                  # Front controller & static asset router
├── cli.php                    # Master CLI Dispatcher
├── docker-compose.yml         # Container stack (Nginx, PHP 8.4-FPM, MySQL 8, Redis 7)
├── docker-compose.sqlite.yml  # Standalone SQLite override profile (excludes MySQL)
├── public/                    # Static Webroot
│   ├── favicon.ico            # Favicon
│   ├── manifest.json          # PWA Web App Manifest
│   ├── css/
│   │   └── bluebird.css       # Semantic CSS framework
│   ├── js/
│   │   └── bluebird.js        # Bluebird UI & client library
│   └── images/                # Static images & logos
├── app/                       # Application Source
│   ├── index.php              # FastCGI entry point
│   ├── preload.php            # OPcache preloading script
│   ├── routes/                # Web View Routes
│   │   ├── index.php          # GET /
│   │   ├── about.php          # GET /about
│   │   └── api/               # REST API Routes
│   │       ├── index.php      # GET /api
│   │       ├── health.php     # GET /api/health
│   │       └── users.php      # /api/users resource
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
│   ├── helpers.php            # Global helpers (json_response, view, asset, e, csrf_field)
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
│   ├── nginx/nginx.conf       # Container Nginx configuration
│   └── php/                   # PHP Dockerfiles (dev, prod)
└── docs/                      # Technical documentation (GitHub Pages)
```

---

## ⚡ Key Coding Conventions

1. **API Endpoints (`app/routes/api/*`):**
   - Always return JSON using `Response::json($data, $status)` or `json_response($data, $status)`.
   - Access input safely with `Request::json()`, `Request::input('key')`, or helper `input('key')`.
   - Validate payloads with `Validate::check($body, $rules)`.
   - Automatic 405: Do not declare catch-all `Request::any()` for 405 errors; Dispatcher handles this automatically.
2. **Web View Routes (`app/routes/*`):**
   - Render templates using `View::render('viewname', $data, $options)` or helper `view('viewname', $data, $options)`.
   - Use `View::html(function() use ($data) { return <<<HTML ... HTML; })` for XSS-safe scoped template output.
   - Reference static assets using `View::asset('/css/file.css')` or helper `asset('/css/file.css')` for automatic cache busting.
   - Include CSRF fields in forms using `<?= csrf_field() ?>` or the injected `$csrf_input`.
3. **Database Queries:**
   - Use `DB::fetchAll("SELECT * FROM table WHERE col = :val", ['val' => $val])`.
   - Use `DB::insert('table', ['name' => $name])` which returns the created record ID.
   - Use `DB::transaction(function() { ... })` for atomic operations.
4. **CLI Orchestration:**
   - Development server: `php cli.php dev [port]`
   - Switch database: `php cli.php db:switch <sqlite|mysql>`
   - Generate SEO sitemap: `php cli.php sitemap`
   - Generate robots rules: `php cli.php robots`
   - Scaffolding:
     - `php cli.php make:model <Name>`
     - `php cli.php make:api <Name>`
     - `php cli.php make:crud <Name> [--embed]`
     - `php cli.php make:route <path>`
   - Diagnostics: `php cli.php doctor` and `php cli.php health`
