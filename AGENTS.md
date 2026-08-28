# 🤖 LilaPHP — Agent & Developer Architecture Guide

This document provides a comprehensive operational guide for AI agents and human developers working on **LilaPHP**.

---

## 🎯 Core Architectural Philosophy (KISS)

1. **100% Decoupled Frontend / REST API:**
   - **Frontend (`frontend/`):** Pure static files (`.html`, `.css`, `.js`, `.ts`, images). Served directly by Nginx with high-speed asset caching. Zero SSR latency.
   - **Backend (`backend/` under `/api/`):** Pure REST API in PHP 8.4+. Dispatched via Nginx FastCGI targeting `backend/index.php`.
2. **Default Frontend Stack (`Lila.js` + `Bluebird CSS`):**
   - **`frontend/js/lila.js` (with `lila.ts` & `lila.d.ts`):** Lightweight reactive SPA engine featuring `html` tagged templates, client hash routing (`route`, `beforeRoute`, `start`), reactive signals (`state`), API client (`api.get`, `post`, etc.), and UI components (toasts, dialog modals, theme switcher).
   - **`frontend/css/bluebird.css`:** Semantic CSS micro-framework with native dark/light mode (`[data-theme="dark"]`).
   - **Framework Freedom:** Developers can use Astro, React, Vue, Svelte, or static HTML; `Lila.js` is the default zero-dependency solution.
3. **Multi-Driver Database (`Core\Database` / `Core\DB`):**
   - Default driver: **MySQL** (`DB_TYPE=mysql`).
   - Alternative: **SQLite** (`DB_TYPE=sqlite`, `DB_FILE=backend/database/app.sqlite`) with WAL mode.
   - Simplified static helpers: `DB::query()`, `DB::fetch()`, `DB::fetchAll()`, `DB::insert()`, `DB::update()`, `DB::delete()`, `DB::transaction()`.
   - Automatic indexing on unique columns, primary keys, and foreign keys/search columns (`idx_*`, `uniq_*`).

---

## 📂 Repository Layout

```text
LilaPHP/
├── nginx.conf                 # Bare-metal / Reverse-Proxy Nginx configuration
├── index.php                  # Local development front controller & static router
├── cli.php                    # CLI Dispatcher (migrate, seed, optimize, make, keygen)
├── docker-compose.yml         # Container stack (Nginx, PHP 8.4-FPM, low-RAM MySQL 8, Redis 7)
├── frontend/                  # Static Frontend Webroot
│   ├── index.html             # Landing page & interactive API console
│   ├── dashboard.html         # Full-featured interactive SPA with Lila.js
│   ├── about.html             # Static sub-page
│   ├── css/
│   │   └── bluebird.css       # Semantic CSS micro-framework & UI utilities
│   └── js/
│       ├── lila.js            # Unified reactive SPA engine & Bluebird UI suite
│       ├── lila.ts            # TypeScript source with strict interfaces
│       └── lila.d.ts          # Type declarations for IDE autocompletion
├── backend/                   # PHP 8.x REST API Engine
│   ├── index.php              # FastCGI API entry point
│   ├── routes/                # File-based REST endpoints (/api, /api/health, /api/users)
│   ├── models/                # Micro-models & ORM entities (User.php, Product.php)
│   ├── database/              # SQLite database storage (app.sqlite)
│   ├── .env                   # Active environment variables
│   └── .env_example           # Environment template
├── _core/                     # Zero-dependency Core Framework Engine
│   ├── bootstrap.php          # PSR-4 autoloader, exception handler & session manager
│   ├── helpers.php            # Global helpers (json_response, sanitize, input, csrf_*, abort)
│   ├── Config.php             # Environment loader with OPcache array caching
│   ├── Dispatcher.php         # REST Router & Dynamic Parameter Resolver
│   ├── Database.php           # PDO multi-driver wrapper & schema indexer
│   ├── Request.php            # HTTP method checks, headers & memoized JSON body
│   ├── Response.php           # JSON response emitter with CORS headers
│   ├── Security.php           # CSRF tokens, rate limiting, AES-256-GCM encryption & XSS filters
│   └── Validate.php           # i18n validation engine
├── docs/                      # Technical HTML documentation
└── tests/                     # Automated Test Suites
    ├── Runner.php             # Core PHP unit test runner
    ├── BrowserIntegrationTest.mjs # Live Node.js HTTP integration test suite
    └── ComprehensiveSuite.mjs # Headless Chrome CDP & DOM test suite
```

---

## ⚡ Key Coding Conventions

1. **Global Helpers:**
   - Always return JSON from `/api/*` endpoints via `json_response($data, $status, $headers)`.
   - Access query/body input securely with `input('key', 'default')`.
   - Clean incoming data with `sanitize($value)`.
   - Abort with HTTP error codes using `abort(404, 'Message')`.
2. **Database Queries:**
   - Use `DB::fetchAll("SELECT * FROM table WHERE col = :val", ['val' => $val])`.
   - Use `DB::insert('table', ['name' => $name])` which returns the created ID.
3. **Frontend with `Lila.js`:**
   - Tagged templates: `const template = (state) => Lila.html\`<div>${state.title}</div>\`;`
   - Route registration: `Lila.route('/path', { state: () => ({ ... }), template, onMount, actions });`
   - Route guards: `Lila.beforeRoute(async (path) => { return checkAuth(); });`
   - Start SPA: `Lila.start('#app');`
4. **Testing:**
   - Run unit tests: `php tests/Runner.php`
   - Run live server integration tests: `node tests/BrowserIntegrationTest.mjs`
