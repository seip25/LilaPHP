# LilaPHP Official Technical Documentation (`Performance First`)

Welcome to the technical documentation for **LilaPHP**, an ultra-fast, API-First PHP 8.4+ framework engineered without template engines (`Twig`) or file-based session overhead.

---

## 🚀 Architectural Highlights

### 1. Nginx File-Based Routing & Native C-Level Rate Limiting

- **Routing Directive**: Nginx maps `/api/foo/bar` directly to the physical file `/var/www/html/backend/routes/foo/bar.php` via `try_files $uri $uri.php $uri/index.php @json_404;`.
- **Zero-PHP 404 & 429 Responses**:
  - If an endpoint file does not exist, Nginx returns `{"error":"Endpoint not found","code":404}` without spawning PHP workers.
  - Rate limiting runs natively in C inside Nginx via `limit_req_zone $binary_remote_addr zone=api_limit:10m rate=60r/s;`. When requests exceed `burst=30`, Nginx immediately returns `{"error":"Too Many Requests","code":429}`.

### 2. Clean Composer Package Releases (`.gitattributes export-ignore`)

To ensure clean package distribution when creating GitHub tags or running `composer install --prefer-dist`, the `.gitattributes` configuration excludes non-runtime assets:

```gitattributes
docs/ export-ignore
docker/ export-ignore
tests/ export-ignore
.github/ export-ignore
```

Your deployment tarball contains strictly `_core/`, `backend/`, `index.php`, and `cli.php`!

### 3. Permanent OPcache RAM Preloading

In production (`docker/php/Dockerfile.prod`), `backend/preload.php` instructs PHP-FPM to compile all framework classes (`Config`, `Database`, `Response`, `Cache`, `Validate`, `Security`, `Logger`, `Dispatcher`, `Upload`, `Task`, `Http`) directly into shared worker RAM.

### 4. Dual-Tier Caching (`APCu` + `Redis`) & Nginx Micro-Caching

- **`Cache::api()`**: Caches data inside local process RAM (`APCu`) with 0 ms network latency.
- **`Cache::db()`**: Distributed caching across your `Redis` cluster with graceful failover to `APCu` if Redis experiences a micro-outage.
- **Nginx FastCGI Micro-Caching (5s TTL)**: Public GET API endpoints are cached directly in Nginx RAM (`LILA_API_CACHE`), allowing throughput to scale beyond 30,000+ RPS. Authenticated requests (`Authorization`, `X-API-Key`, session cookies) or private endpoints via `Response::setPrivateCache()` automatically bypass the cache.

---

## 🛠️ Core Components (`_core/`)

### 📦 `Core\Upload` (Anti-Malware File Upload Engine)

Handles `$_FILES` with strict `finfo` MIME verification, screens file bytes for embedded PHP/script execution blocks (`<?php`, `<?=`), enforces maximum file sizes, and assigns cryptographically randomized filenames:

```php
use Core\Upload;

// Save verified upload to backend/uploads/
$filename = Upload::save($_FILES['avatar'], null, ['image/webp' => 'webp', 'image/png' => 'png']);
if ($filename) {
    // Returns randomized filename (e.g. a1b2c3...webp)
}
```

### ⏳ `Core\Task` (Background Job Queues & Workers)

Dispatches asynchronous tasks without blocking your API response. If Redis is active, jobs are pushed to `lilaphp:jobs`; otherwise, detached OS background processes (`proc_open`) execute the job:

```php
use Core\Task;

// Dispatch background job
Task::dispatch('example_mail_blast', ['batch_size' => 15000, 'campaign' => 'Promo 2026']);
```

**Running Background Workers via CLI**:

```bash
php cli.php task:work
```

### ⚡ `Cli\Benchmark` (Server-Side Concurrency Benchmark Engine)

Executes real OS-level load testing using `curl_multi_exec` sockets. Calculates exact P50/P95/P99 latency percentiles and syncs live snapshots to Redis (`lilaphp:benchmark:{id}`) every ~500ms for `/debug.html` integration:

```bash
# Run 1,000 concurrent connection stress test for 30 seconds
php cli.php benchmark --url=/api/init --concurrency=1000 --duration=30
```

### 🌐 `Core\Http` (Concurrent cURL Client)

A high-performance cURL client supporting custom timeouts, JSON auto-serialization, and concurrent asynchronous multi-request execution (`curl_multi_*`):

```php
use Core\Http;

// Simple GET request
$res = Http::get('https://api.github.com/users/seip25', ['User-Agent: LilaPHP/1.0']);

// Concurrent parallel requests
$batch = Http::multi([
    'req1' => ['url' => 'https://api.example.com/item/1'],
    'req2' => ['url' => 'https://api.example.com/item/2']
]);
```

### 📡 `Core\Request` (Static Route Method Handlers & Middlewares)

Define HTTP verb handlers (`Request::GET`, `Request::POST`, `Request::PUT`, `Request::DELETE`, `Request::PATCH`) directly inside route files with middleware support:

```php
use Core\Request;
use Core\Response;
use Models\User;

Request::GET(function() {
    return ['users' => User::all()];
});

Request::POST([AuthMiddleware::class], function() {
    $user = new User(Request::json());
    $user->assertValid();
    $user->save();
    return ['status' => 'created', 'data' => $user];
});
```

### 🧱 `Models\BaseModel` (Lightweight ORM)

All models inherit from `Models\BaseModel`, supporting `find()`, `all()`, `withTrashed()`, `onlyTrashed()`, `fill()`, `save()`, `delete()`, `forceDelete()`, `restore()`, `validate()`, and `assertValid()`:

```php
use Models\Product;
use Core\Database;

// Find & Fetch (soft-deleted excluded by default)
$product = Product::find(1);
$products = Product::all("stock > 0 ORDER BY price DESC");
$allProducts = Product::withTrashed();
$trashedProducts = Product::onlyTrashed();

// Fill & Save
$product = new Product();
$product->fill(['name' => 'Wireless Keyboard', 'price' => 49.99])->save();

// Soft Delete, Restore & Hard Delete
$product->delete(); // Soft delete
$product->restore(); // Restore
$product->forceDelete(); // Hard delete

// Database Transactions
Database::transaction(function () use ($senderId, $receiverId) {
    Database::update('accounts', ['balance' => 900], 'id = ?', [$senderId]);
    Database::update('accounts', ['balance' => 1100], 'id = ?', [$receiverId]);
});
```

### 📢 `Core\Response` (High-Speed JSON & File Emitter)

```php
use Core\Response;

Response::json(['status' => 'success', 'data' => $payload], 200);
Response::error('Validation failure', 422, $errors);
Response::file('/path/to/report.pdf', 'report.pdf');
Response::stream(fn() => echo "chunk", 200);
Response::redirect('/login');
```

### 🔒 `Services\AuthService` (Encrypted Session & Lockout Protection)

Provides AES-256 encrypted session storage (`Core\Security::encrypt`) and brute-force attempt lockout:

```php
use Services\AuthService;

// Validate authenticated session
$user = AuthService::validateAuth(true); // Auto-emits 401 if unauthenticated

// Login credentials with brute-force throttling
$result = AuthService::login($username, $password);

// Destroy session
AuthService::logout();
```

### 🔇 Logging Disabled by Default (`Performance First`)

To prevent disk I/O bottlenecks under high concurrency (thousands of req/s), file logging is disabled by default (`LOG_ENABLED=false` in `.env` and `Config::$LOG_ENABLED = false`). Log entries are only written to disk when explicitly enabled.

---

## 🛠️ Built-in Debug & Performance Dashboard (`/debug`)

LilaPHP includes an interactive, zero-overhead **Debug & Performance Monitoring Dashboard** accessible at `/debug` or `/debug.html`.

- **Live Redis Request Stream**: Captures Method, URI, Query Params, Duration (ms), Memory Peak (MB), Status Code, and Client IP into Redis (`lilaphp:debug:requests`).
- **System Health Profiler**: Real-time status checks for Redis, MySQL, PHP-FPM, CPU Load, RAM Usage, and Disk Free space.
- **Interactive Concurrency Benchmark Tool**: Run browser-based client-side stress tests with concurrencies from **1 to 4,000** on any autodetected route.
- **Filters & Search**: Filter logs by URI, Method (GET, POST, PUT, DELETE), Status (2xx, 3xx, 4xx, 5xx), or IP with client-side pagination.

### ⚙️ Debug Environment Configuration (`backend/.env`)

Controlled via `DEBUG_LOGGING_ENABLED` in `backend/.env`:

```env
# Enable/disable Redis request logging (default: false for zero overhead)
DEBUG_LOGGING_ENABLED=false
```

When deploying to production via `php cli.php docker prod`, if `DEBUG_LOGGING_ENABLED` is `true`, the CLI will display an interactive warning asking for confirmation before proceeding.

### 🚀 How to Run Unlimited Concurrency & Benchmark Tests (Disabling Rate Limits)

To perform load testing or benchmark runs at maximum concurrencies (100, 1000, 2000, 3000, 4000) without hitting rate limiters, temporarily disable PHP and Nginx rate limits:

1. **Disable PHP Rate Limiting in `backend/.env`**:
   ```env
   RATE_LIMIT=0
   ```
2. **Comment out Nginx C-Level Rate Limiting in `docker/nginx/nginx.conf`**:
   ```nginx
   # limit_req_zone $binary_remote_addr zone=api_limit:10m rate=60r/s;

   location ^~ /api/ {
       # limit_req zone=api_limit burst=30 nodelay;
       # limit_req_status 429;

       include fastcgi_params;
       fastcgi_pass php:9000;
       ...
   }
   ```
3. Restart containers or reload Nginx (`docker compose exec nginx nginx -s reload` or `php cli.php docker dev`).

---

## ⚡ Master CLI (`cli.php`)

| Command                    | Description                                                                                |
| :------------------------- | :----------------------------------------------------------------------------------------- | ---------------------------------------------- | ----- | ----------------------------------------------------------------------- |
| `php cli.php optimize`     | Pre-compile `.env` settings to `_core/cache/env.php`, flush APCu/Redis, and reset OPcache. |
| `php cli.php key:generate` | Generate secure 256-bit `APP_KEY` in `backend/.env`.                                       |
| `php cli.php migrate`      | Scan models in `backend/models/` and synchronize MySQL schemas automatically.              |
| `php cli.php seed`         | Populate initial database records and check default accounts (`admin@lilaphp.dev`).        |
| `php cli.php task:work`    | Start continuous background worker consuming Redis job queues.                             |
| `php cli.php docker [action]` | Orchestrate Nginx, PHP 8.4, MySQL, and Redis cluster (`dev`, `prod`, `stop`, `ps`, `stats`, `logs`, `clean`). Also provides container access (`exec-php`, `exec-mysql`, `exec-redis`) and smart query/command shortcuts (`docker mysql`, `docker redis`). |
| `php cli.php make model \| route <Name>` | Scaffold API models and route files instantly. |
