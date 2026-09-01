<p align="center">
  <h1 align="center">⚡ LilaPHP — High-Performance Decoupled Micro-API Framework</h1>
  <p align="center">
    <strong>KISS Architecture (Zero Heavy Dependencies) with Pure Static Frontend (Lila.js SPA &amp; Bluebird CSS) &amp; Ultra-Fast PHP 8.x REST Backend.</strong>
  </p>
</p>

<p align="center">
  <a href="https://seip25.github.io/LilaPHP/"><img src="https://img.shields.io/badge/docs-online-blue.svg" alt="Documentation"></a>
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-MIT-green.svg" alt="License"></a>
  <a href="#"><img src="https://img.shields.io/badge/PHP-8.4+-777bb4.svg" alt="PHP 8.4+"></a>
  <a href="#"><img src="https://img.shields.io/badge/Frontend-Lila.js_%26_Bluebird_CSS-38bdf8.svg" alt="Lila.js"></a>
  <a href="#"><img src="https://img.shields.io/badge/Database-MySQL_%7C_SQLite_%7C_PostgreSQL-f59e0b.svg" alt="Database"></a>
</p>

---

## 📖 Live Documentation & Interactive Demo
- **Live Documentation:** [https://seip25.github.io/LilaPHP/](https://seip25.github.io/LilaPHP/)
- **Repository:** [https://github.com/seip25/LilaPHP](https://github.com/seip25/LilaPHP)

---

## 🏗️ 100% Decoupled Architecture (KISS Principle)

LilaPHP strictly separates the **Frontend User Interface** and the **Backend REST API**:

1. **Frontend (`frontend/`):**
   - Pure static assets (`.html`, `.css`, `.js`, `.ts`, images, fonts) served directly by **Nginx** at maximum raw speed.
   - Styled with the **Bluebird CSS** micro-framework (semantic HTML tags, native CSS variables, auto/manual dark/light mode).
   - Powered by **Lila.js** (Vanilla reactive SPA engine with `html` tagged templates, client routing, route guards, and signals) with complete **TypeScript** definitions (`lila.ts` & `lila.d.ts`) for flawless IDE IntelliSense.
   - **Framework Freedom:** Use Astro, React, Vue, Svelte, or static HTML as you like; `Lila.js` is the default zero-dependency solution.
2. **Backend API (`backend/` under `/api/`):**
   - Every request under `/api/*` is dispatched to the lightweight PHP 8.x REST engine via FastCGI.
   - REST endpoints with dynamic parameter mapping (e.g. `/api/users/{id}`), zero-dependency PSR-4 autoloader, and global helpers.
3. **Plug & Play Multi-Database:**
   - Out-of-the-box support for **MySQL** (default), portable **SQLite (WAL mode)**, and **PostgreSQL** via unified `Core\Database` and `DB::*` static helpers with automated schema indexing.

```text
LilaPHP/
├── nginx.conf                 # ⚡ Nginx Decoupled Configuration (Frontend root + /api/ pass)
├── index.php                  # 🎯 Unified Front Controller & Development Server Router
├── cli.php                    # 🛠️ Master CLI Dispatcher (Migrate, Seed, Optimize, Make, KeyGen)
├── docker-compose.yml         # 🐳 Docker Compose (Nginx, PHP 8.4-FPM, low-RAM MySQL 8.0, Redis 7)
├── frontend/                  # 🌐 Pure Static Frontend
│   ├── index.html             # Application home & interactive API playground
│   ├── dashboard.html         # Interactive SPA dashboard with Lila.js
│   ├── about.html             # Static sub-page example
│   ├── css/
│   │   └── bluebird.css       # Semantic CSS micro-framework with native dark/light mode
│   └── js/
│       ├── lila.js            # Reactive SPA engine, HTTP client & Bluebird UI suite
│       ├── lila.ts            # Strict TypeScript source & interfaces
│       └── lila.d.ts          # TypeScript declarations for VS Code & PhpStorm
├── backend/                   # 🖥️ Lightweight PHP 8.x REST API
│   ├── index.php              # FastCGI API entry point
│   ├── routes/                # File-based REST endpoints (/api/health, /api/users, etc.)
│   ├── models/                # Micro-models & ORM entities (User.php, Product.php)
│   ├── database/              # SQLite database storage (app.sqlite)
│   ├── .env                   # Environment configuration (DB_TYPE=mysql, APP_KEY, etc.)
│   └── .env_example           # Configuration template
├── _core/                     # ⚡ Zero-Dependency Core Engine
│   ├── bootstrap.php          # PSR-4 Autoloader, Exception Handler & Session Manager
│   ├── helpers.php            # Global helpers (json_response, sanitize, input, csrf_*, abort)
│   ├── Config.php             # .env parser with OPcache array caching
│   ├── Dispatcher.php         # REST Router & Dynamic Parameter Resolver
│   ├── Database.php           # PDO Multi-Driver DB Wrapper & Schema Indexer
│   ├── Request.php            # Static O(1) HTTP method checks, headers & memoized JSON body
│   ├── Response.php           # JSON response emitter with CORS & status handling
│   ├── Security.php           # CSRF tokens, rate limiting, AES-256-GCM encryption & XSS filters
│   ├── Cache.php              # Dual-tier cache (APCu RAM + Redis cluster)
│   └── Validate.php           # Multi-language validation engine (i18n)
├── docs/                      # 📚 Complete HTML Documentation (GitHub Pages)
└── tests/                     # 🧪 Automated Test Suites (PHP Runner, Live HTTP, Chrome CDP)
```

---

## 🚀 Quick Start in 30 Seconds

### Option A: Local Development (PHP Built-in Server)

No Docker or Composer required!

```bash
# 1. Clone the repository
git clone https://github.com/seip25/LilaPHP.git
cd LilaPHP

# 2. Copy the environment file
cp backend/.env_example backend/.env

# 3. Start the built-in development server
php -S localhost:8080 index.php
```
Open [http://localhost:8080](http://localhost:8080) in your browser.

---

### Option B: Docker Orchestration (Production Ready)

```bash
# 1. Build and start containers (Nginx + PHP 8.4-FPM + MySQL + Redis)
docker compose --env-file ./backend/.env up -d --build

# 2. Check container status
docker compose ps

# 3. Open application
# Frontend: http://localhost:8080
# SPA Dashboard: http://localhost:8080/dashboard.html
# Health API: http://localhost:8080/api/health
```

---

## 🎨 Frontend: Lila.js SPA & Bluebird CSS

### 1. Declarative SPA Routing & Tagged Templates

```html
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <link rel="stylesheet" href="/css/bluebird.css" />
    <script src="/js/lila.js"></script>
</head>
<body>
    <nav>
        <a href="#/" data-link>Overview</a>
        <a href="#/users" data-link>Users</a>
    </nav>

    <div id="app"></div>

    <script>
        const { html, route, start, beforeRoute, fetch: http, escapeHtml } = Lila;

        // Session & Route Guard
        beforeRoute(async (path) => {
            const auth = await http('/api/auth');
            if (auth.authenticated) return true;
            window.location.href = '/login';
            return false;
        });

        // Overview Route
        route('/', {
            state: () => ({ usersCount: 0 }),
            template: (s) => html`
                <article>
                    <h2>Dashboard</h2>
                    <p>Total Users: ${s.usersCount}</p>
                </article>
            `,
            onMount: async (state) => {
                const res = await http('/api/users');
                state.usersCount = res.data.length;
            }
        });

        start('#app');
    </script>
</body>
</html>
```

### 2. UI Components & Reactive Signals

```javascript
// 1. Reactive State Signals
const counter = Lila.state(0);
counter.subscribe(val => {
    document.getElementById('counter-display').textContent = val;
});
counter.set(c => c + 1);

// 2. UI Component Helpers
Lila.toast({ title: 'Success', description: 'User saved!', type: 'success' });
Lila.modal('my-modal', 'open');
Lila.drawer('my-drawer', 'toggle');
Lila.theme('toggle');
```

---

## ⚙️ Backend: REST API Development

### 1. Physical REST Routing (`backend/routes/*.php`)

```php
<?php
use Core\Request;
use Core\Response;

$method = Request::getMethod();
$id = $_GET['id'] ?? null;

switch ($method) {
    case 'GET':
        if ($id) {
            $user = DB::fetch("SELECT * FROM users WHERE id = :id", ['id' => $id]);
            if (!$user) abort(404, 'User not found');
            json_response(['data' => $user]);
        }
        json_response(['data' => DB::fetchAll("SELECT * FROM users ORDER BY id DESC")]);
        break;

    case 'POST':
        $data = Request::json();
        $newId = DB::insert('users', [
            'name'  => sanitize($data['name'] ?? ''),
            'email' => sanitize($data['email'] ?? ''),
            'role'  => sanitize($data['role'] ?? 'user'),
        ]);
        json_response(['status' => 'success', 'id' => $newId], 201);
        break;

    case 'DELETE':
        DB::delete('users', 'id = :id', ['id' => $id]);
        json_response(['status' => 'success', 'message' => 'Deleted']);
        break;
}
```

### 2. Multi-Driver Database Helpers (`DB::*`)

```php
// MySQL (default), SQLite, or PostgreSQL
$userId = DB::insert('users', ['name' => 'John Doe', 'email' => 'john@example.com']);
$user = DB::fetch("SELECT * FROM users WHERE id = :id", ['id' => $userId]);
$allUsers = DB::fetchAll("SELECT * FROM users ORDER BY id DESC");
DB::update('users', ['name' => 'Jane Doe'], 'id = :id', ['id' => $userId]);
DB::delete('users', 'id = :id', ['id' => $userId]);

// Atomic Transactions
DB::transaction(function($pdo) {
    DB::insert('accounts', ['balance' => 1000]);
    DB::insert('logs', ['action' => 'account_created']);
});
```

### 3. Universal AI Engine (`Core\AI` & `ai()`)

```php
use Core\AI;

// 1. Quick text generation (DeepSeek, Gemini, OpenAI, Claude, Ollama)
$reply = AI::text("Explain quantum computing in one sentence.");

// 2. Global helper shortcut
$haiku = ai("Write a haiku about high-performance PHP APIs.");

// 3. DeepSeek with automated model fallback
$deepseekRes = AI::deepseek("Explain Object Oriented PHP.", [
    'model' => 'deepseek-v4-flash',
    'fallback_model' => 'deepseek-chat',
    'system' => 'You are an expert PHP programmer.'
]);

// 4. Structured JSON extraction
$profile = AI::json("Generate user data with name, email, and role.");
// Output: ['name' => 'Ada Lovelace', 'email' => 'ada@computing.org', 'role' => 'Admin']

// 5. Built-in Rate Limiter for AI endpoints
$limit = AI::checkRateLimit(Request::ip(), limitPerMinute: 6, limitPerDay: 30);
if (!$limit['allowed']) {
    abort(429, $limit['error']);
}
```

### 4. System Doctor & Pre-Flight Diagnostics

```bash
# Verify ports, extensions, APP_KEY, permissions, DB, and Redis
php cli.php doctor
```

---

## 🧪 Automated Testing Suite

```bash
# 1. PHP Core Unit Tests
php tests/Runner.php

# 2. Live HTTP Server Integration Tests
node tests/BrowserIntegrationTest.mjs

# 3. Headless Chrome CDP & DOM Tests
node tests/ComprehensiveSuite.mjs
```

---

## 📄 License

LilaPHP is open-sourced software licensed under the [MIT license](LICENSE).
