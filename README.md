<p align="center">
  <h1 align="center">⚡ LilaPHP — Ultra-Fast API-First PHP 8.4+ Framework</h1>
  <p align="center">
    <strong>Performance First Architecture with Nginx File-Based Routing, OPcache Preload in RAM, Dual-Tier APCu + Redis Cache, Background Workers, and Blue-bird style CLI.</strong>
  </p>
</p>

---

# 📜 LilaPHP Documentation

[https://seip25.github.io/LilaPHP/](https://seip25.github.io/LilaPHP/)

## 🏗️ Re-Architected from the Ground Up (`Performance First`)

LilaPHP has been completely re-engineered as an **API-First, ultra-low latency engine**. We stripped out all heavy, slow dependencies (no Twig templating engine, no file-based session overhead, no expensive PHP regex routing loops). Instead, Nginx handles direct physical file routing (`backend/routes/*.php`) and returns high-speed JSON `404` & `429` errors directly at the C/reverse-proxy level!

```
LilaPHP/
├── _core/                     # ⚡ Ultra-fast Engine Core
│   ├── Config.php             # .env parser and static OPcache array exporter (`_core/cache/env.php`)
│   ├── Database.php           # PDO MySQL connection pool and prepared query execution
│   ├── Request.php            # Static O(1) HTTP method checks, memoized JSON parsing, headers & input
│   ├── Response.php           # JSON/CORS emitter with caching headers
│   ├── Cache.php              # Dual-Tier Cache (APCu shared RAM + Redis cluster persistence)
│   ├── Validate.php           # High-speed i18n validator reading $_REQUEST['lang'] (no sessions/Twig)
│   ├── Security.php           # RAM rate limiting, AES-256 encryption, and anti-XSS/SQLi sanitization
│   ├── Logger.php             # Async JSON logging (disabled by default via LOG_ENABLED=false)
│   ├── Dispatcher.php         # Express.js / Next.js style file-based router
│   ├── Upload.php             # Secure anti-malware MIME file upload engine (`finfo` checked)
│   ├── Task.php               # Background job dispatcher for Redis (`lilaphp:jobs`) & OS workers
│   ├── Http.php               # High-performance cURL client with concurrent `curl_multi_*` pooling
│   ├── bootstrap.php          # Auto-prepend compatible autoloader (`auto_prepend_file`)
│   ├── locales/               # Validation dictionary translations (`es`, `en`, `pt-br`)
│   └── cli/                   # CLI engine classes (Migrate, Seed, Optimize, KeyGen, Docker, Make, TaskWork)
├── backend/                   # 🖥️ Application Backend (PHP API)
│   ├── models/                # Lightweight JSON-serializable ORM (`User.php`, `Product.php`)
│   ├── routes/                # Physical route scripts (`index.php`, `test.php`, `ping.php`, `404.php`)
│   ├── preload.php            # Production OPcache RAM preload script compiling all core classes
│   ├── .env                   # Dynamic environment variables (`HTTP_PORT`, `DB_PORT`, `APP_KEY`)
│   └── .env_example           # Configuration template
├── frontend/                  # 🌐 Static Frontend Dashboard (Vanilla HTML / JS / CSS)
│   ├── index.html             # Modern Glassmorphism Dark Mode API testing landing page
│   ├── css/style.css          # Curated dark theme tokens, neon gradients, and micro-animations
│   └── js/app.js              # Real-time diagnostic benchmark client checking `/api/test`
├── docker/                    # 🐳 Infrastructure & Container Profiles
│   ├── php/Dockerfile.dev     # PHP 8.4 FPM/CLI for development (pdo_mysql, gd, redis, apcu)
│   ├── php/Dockerfile.prod    # PHP 8.4 FPM/CLI with permanent OPcache RAM Preload and dynamic workers
│   └── nginx/nginx.conf       # Static caching on `/`, API try_files on `/api/`, native C 404 & 429 JSON
├── docs/                      # 📚 Comprehensive documentation (`export-ignore` on release)
├── cli.php                    # 🛠️ Master Unified CLI Dispatcher (`Blue-bird` style)
├── index.php                  # 🎯 Front Controller fallback for dev server and direct PHP access
└── docker-compose.yml         # 🚀 Multi-container orchestration (Nginx, PHP 8.4, MySQL 8.0, Redis 7)
```

---

## ⚡ Key Innovations & Performance Features

### 1. Nginx Native Rate Limiter & JSON 404/429

- **C-Level Rate Limiting**: Nginx applies `limit_req_zone $binary_remote_addr zone=api_limit:10m rate=60r/s;` with `burst=30 nodelay;`. If an IP exceeds limits, Nginx returns `{"error":"Too Many Requests","code":429}` instantly in C without starting a PHP worker!
- **Zero PHP 404 Overhead**: Requests to non-existent route files return `{"error":"Endpoint not found","code":404}` directly from Nginx via `error_page 404 = @json_404;`.

### 2. Static O(1) Request & Response Handling (`Core\Request`)

In traditional frameworks, every HTTP request instantiates heavy `$request` objects. In LilaPHP, all request data is accessed via zero-allocation static helpers:

```php
use Core\Request;
use Core\Response;

// Enforce HTTP Method (emits 405 Method Not Allowed automatically if not matching)
Request::assertMethod('POST', 'PUT');

// Retrieve JSON body with O(1) RAM memoization
$body = Request::json();
$token = Request::bearerToken();
```

### 3. Clean Package Distribution (`.gitattributes export-ignore`)

When publishing releases or running `composer install --prefer-dist`, development documentation and test directories (`docs/`, `tests/`, `.github/`) are excluded automatically. Your deployment tarball cleanly includes your `_core/`, `backend/`, `docker/` cluster configurations, and entrypoints so you can launch containers instantly.

### 4. Dual-Tier Caching (`APCu` + `Redis`)

- **`Cache::api()`**: Caches data in shared worker RAM (`APCu`) with zero network round-trip latency.
- **`Cache::db()`**: Caches across your cluster in `Redis` with automatic failover to `APCu` if Redis experiences a micro-interruption.

### 5. Background Job Queues (`Task::dispatch` & `Task::work`)

Execute slow operations (sending emails, processing images, webhooks) asynchronously in background processes:

```php
use Core\Task;

// Dispatch job to Redis list (`lilaphp:jobs`) or detached OS process
Task::dispatch('send_welcome_email', ['userId' => 42]);
```

Consuming jobs via background worker command:

```bash
$ php cli.php task:work
```

### 6. Anti-Malware Secure Upload (`Upload::save`)

Ensures uploaded files (`$_FILES`) are authentic via `finfo` MIME checking, scans content bytes for embedded `<?php` or script injections, and assigns cryptographically randomized filenames (`bin2hex(random_bytes(16))`).

### 7. Concurrent Multi-cURL Client (`Http::multi`)

Fetch external APIs sequentially (`Http::get()`, `Http::post()`) or run dozens of HTTP requests concurrently in parallel (`Http::multi([...])`).

---

## 🛠️ Master CLI Commands (`cli.php`)

```bash
php cli.php help                                # Display interactive help menu
php cli.php optimize                            # Pre-cache .env settings to OPcache memory and flush APCu/Redis
php cli.php key:generate                        # Generate secure 256-bit cryptographic APP_KEY in backend/.env
php cli.php migrate                             # Synchronize all API Model table schemas with MySQL
php cli.php seed                                # Populate database tables with initial seed records
php cli.php task:work                           # Start continuous background worker consuming Redis job queues
php cli.php make model <Name>                   # Generate boilerplate API Model inside backend/models/
php cli.php make route <path>                   # Generate file-based API route inside backend/routes/
php cli.php docker [dev|prod|stop|ps|logs]      # Orchestrate Nginx, PHP, MySQL, Redis cluster
```

---

## 🚀 Quick Start with Docker Cluster

1. Copy environment configurations:
   ```bash
   cp backend/.env_example backend/.env
   ```
2. Launch the cluster in Development mode:
   ```bash
   php cli.php docker dev
   ```
3. Check running endpoints and assigned dynamic ports (`HTTP_PORT=8080`, `DB_PORT=3306`, `REDIS_PORT=6379`):
   ```bash
   php cli.php docker ps
   ```
4. Optimize for Production (`Preload in RAM + Multi-worker FPM + APCu + Redis`):
   ```bash
   php cli.php docker prod && php cli.php optimize
   ```

---

## ⚡ Nginx Configuration & Rate Limiting (`Benchmarks vs Production`)

LilaPHP uses native **C-level Nginx try_files** to serve extensionless HTML pages (`/login` -> `/login.html`) directly from kernel/RAM memory while forwarding `/api/*` routes to PHP-FPM at maximum FastCGI speed:

```nginx
# Frontend extensionless HTML & SPA routing
location / {
    try_files $uri $uri.html $uri/ $uri/index.html /index.html;
    add_header Cache-Control "public, max-age=3600, no-transform";
    add_header X-Powered-By "LilaPHP";
}
```

### 💡 Benchmarking / Load Testing Note
By default, both Nginx and PHP have defensive **Rate Limiting** active to prevent DDoS attacks in production (`HTTP 429 Too Many Requests`). When running high-concurrency stress tests (such as `k6` at 1,000+ VUs):
1. **Nginx Level:** In [`docker/nginx/nginx.conf`](file:///home/seip/Documentos/GitHub/LilaPHP/docker/nginx/nginx.conf), comment out the rate limit directives inside `location ^~ /api/`:
   ```nginx
   # limit_req zone=api_limit burst=30 nodelay;
   # limit_req_status 429;
   ```
2. **PHP Level:** In [`backend/.env`](file:///home/seip/Documentos/GitHub/LilaPHP/backend/.env), set `RATE_LIMIT=0` (or `false`) to disable application-level throttling:
   ```ini
   RATE_LIMIT=false
   ```

---

## 📖 Documentation

Detailed technical guides can be found in the [`docs/`](file:///home/seip/Documentos/GitHub/LilaPHP/docs/README.md) directory.

## 📄 License

LilaPHP is open-sourced software licensed under the [MIT license](file:///home/seip/Documentos/GitHub/LilaPHP/LICENSE).
