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
│   ├── routes/                # Core System Endpoints (`404.php`, `debug/health.php`, `debug/metrics.php`)
│   ├── locales/               # Validation dictionary translations (`es`, `en`, `pt-br`)
│   └── cli/                   # CLI engine classes (Migrate, Seed, Optimize, KeyGen, Docker, Make, TaskWork)
├── backend/                   # 🖥️ Application Backend (PHP API)
│   ├── models/                # Lightweight JSON-serializable ORM (`User.php`, `Product.php`)
│   ├── routes/                # User API route scripts (`index.php`, `test.php`, `users.php`)
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
- **FastCGI Micro-Caching (5s TTL)**: Public `GET` requests on `/api/` are cached at the Nginx level for 5 seconds (`LILA_API_CACHE`), allowing throughput to scale up to 30,000+ RPS without invoking PHP-FPM.
- **Smart Security & Auth Bypass**: Nginx automatically bypasses caching (`$skip_api_cache`) for non-GET methods (`POST`, `PUT`, `DELETE`), authenticated requests containing `Authorization` / `X-API-Key` headers, or session cookies (`PHPSESSID`, `user_token`, `jwt`).
- **Core Private Cache Helper (`Core\Response::setPrivateCache()`)**: Endpoints can explicitly invoke `Response::setPrivateCache()` to emit `Cache-Control: private, no-store, no-cache`, ensuring real-time endpoints (like metrics or debug polling) bypass Nginx caching.

### 2. Static O(1) Request Routing & Handlers (`Core\Request`)

In LilaPHP, physical files map directly to URIs (e.g. `backend/routes/users.php` -> `/api/users`). You can handle HTTP request methods (`Request::GET`, `Request::POST`, `Request::PUT`, `Request::DELETE`, `Request::PATCH`) directly inside route files with full middleware support:

```php
use Core\Request;
use Core\Response;
use Models\User;

// Global logic / validations for all methods in this file
// (Runs before method handlers)

// GET /api/users
Request::GET(function() {
    $users = User::all("1=1 ORDER BY id DESC");
    return ['status' => 'success', 'data' => $users];
});

// POST /api/users with Middlewares
Request::POST([AuthMiddleware::class, RoleCheck::class], function() {
    $user = new User(Request::json());
    $user->assertValid(); // Emits 422 JSON response if validation fails
    $user->save();

    return ['status' => 'created', 'data' => $user];
});

// PUT /api/users
Request::PUT(AuthMiddleware::class, function() {
    $id = Request::input('id');
    $user = User::find($id);
    if (!$user) {
        Response::error('User not found', 404);
    }
    $user->fill(Request::json())->save();
    return ['status' => 'updated', 'data' => $user];
});

// DELETE /api/users
Request::DELETE(AuthMiddleware::class, function() {
    $user = User::find(Request::input('id'));
    $user?->delete();
    return ['status' => 'deleted'];
});
```

#### Zero-Allocation `Core\Request` & `Core\Response` Helpers:

```php
use Core\Request;
use Core\Response;

// --- Core\Request ---
$json = Request::json();                          // Memoized O(1) RAM JSON parser
$page = Request::input('page', 1);                 // Retrieve input from $_REQUEST or JSON with fallback
$all = Request::all();                             // Merge $_REQUEST and JSON body
$token = Request::bearerToken();                   // Extract Bearer token string from Authorization header
$auth = Request::header('Authorization');          // Retrieve header case-insensitively
$headers = Request::headers();                     // Retrieve all HTTP headers as associative array
$clientIp = Request::ip();                         // Retrieve client IP (Nginx X-Forwarded-For supported)
$method = Request::getMethod();                    // Returns uppercase method ('GET', 'POST', etc.)

// --- Core\Response ---
Response::json(['status' => 'ok'], 200);           // Emit JSON with CORS headers & exit
Response::error('Invalid input', 400, $errors);    // Emit standardized error structure & exit
Response::file('/path/to/invoice.pdf', 'Inv.pdf'); // Direct binary file delivery & exit
Response::stream(fn() => echo "chunk", 200);       // Event-Stream / SSE real-time output & exit
Response::redirect('/login', 302);                 // HTTP Location redirect & exit
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

### 8. Lightweight ORM Models (`Models\BaseModel`)

All API Models inherit from `Models\BaseModel`, providing automated CRUD operations, automatic JSON serialization, input validation via `Core\Validate`, and table schema definitions for migrations (`php cli.php migrate`).

#### Defining a Model (`backend/models/Product.php`):
```php
namespace Models;

class Product extends BaseModel
{
    protected string $table = 'products';
    protected string $primaryKey = 'id';

    public ?int $id = null;
    public string $name = '';
    public float $price = 0.0;
    public int $stock = 0;
    public string $sku = '';

    // Validation rules (Core\Validate syntax)
    protected array $rules = [
        'name' => 'required|min_length:2|max_length:255',
        'price' => 'required|numeric',
        'stock' => 'numeric',
        'sku' => 'max_length:50'
    ];

    // Schema definition for `php cli.php migrate`
    public static function getSchema(): array
    {
        return [
            'id' => ['type' => 'int', 'unsigned' => true, 'autoIncrement' => true, 'primaryKey' => true],
            'name' => ['type' => 'string', 'length' => 255, 'nullable' => false],
            'price' => ['type' => 'decimal', 'length' => '10,2', 'default' => '0.00'],
            'stock' => ['type' => 'int', 'default' => 0],
            'sku' => ['type' => 'string', 'length' => 50, 'nullable' => true]
        ];
    }
}
```

#### CRUD & Validation Operations:
```php
use Models\Product;

// 1. Find by ID (excludes soft-deleted records by default)
$product = Product::find(1);

// 2. Fetch all with optional SQL WHERE conditions
$products = Product::all("stock > ? ORDER BY price DESC", [0]);

// 3. Include or fetch only soft-deleted records
$allProducts = Product::withTrashed();
$trashedProducts = Product::onlyTrashed();

// 4. Create or Populate instance attributes
$product = new Product();
$product->fill([
    'name' => 'Wireless Keyboard',
    'price' => 49.99,
    'stock' => 100,
    'sku' => 'KB-WL-01'
]);
$product->price = 45.00;

// 5. Validate Model attributes
$errors = $product->validate();

// 6. Validate & Halt automatically (Emits HTTP 422 JSON error if invalid)
$product->assertValid();

// 7. Save (Inserts if new record, Updates if ID exists)
$product->save();

// 8. Soft delete (updates deleted_at timestamp)
$product->delete();

// 9. Restore soft-deleted record or permanently force delete
$product->restore();
$product->forceDelete(); // or $product->delete(true);

// 10. Paginate with soft-delete filtering
$paginated = Product::paginate(['stock' => 10], 60, false);
```

#### Database Transactions (`Core\Database`):
```php
use Core\Database;
use Core\Response;

// Automatic transaction block (auto commit / rollback on exception)
Database::transaction(function () use ($senderId, $receiverId, $amount) {
    Database::update('accounts', ['balance' => 'balance - ' . $amount], 'id = ?', [$senderId]);
    Database::update('accounts', ['balance' => 'balance + ' . $amount], 'id = ?', [$receiverId]);
});

// Manual transaction control
try {
    Database::beginTransaction();
    Database::update('accounts', ['balance' => 900], 'id = ?', [$senderId]);
    Database::update('accounts', ['balance' => 1100], 'id = ?', [$receiverId]);
    Database::commit();
} catch (\Throwable $e) {
    Database::rollBack();
    Response::json(['error' => $e->getMessage()], 500);
}
```


### 9. Encrypted Session Authentication (`Services\AuthService`)

For web applications, dashboards, or session-based APIs, `Services\AuthService` provides secure session management with AES-256 encryption (`Core\Security::encrypt`), HTTP-only cookies, and automated brute-force attempt lockout throttling:

```php
use Core\Request;
use Core\Response;
use Services\AuthService;

// GET /api/auth -> Check authentication state
Request::GET(function () {
    $user = AuthService::validateAuth(false);
    return [
        'status' => 'success',
        'authenticated' => $user !== false,
        'user' => $user ?: null
    ];
});

// POST /api/auth -> Login / Logout handler
Request::POST(function () {
    $action = Request::input('action', 'login');

    if ($action === 'logout') {
        AuthService::logout();
        return ['status' => 'success', 'message' => 'Logged out successfully'];
    }

    $username = trim((string) Request::input('username', ''));
    $password = (string) Request::input('password', '');

    if (empty($username) || empty($password)) {
        Response::error('Username and password are required', 400);
    }

    $result = AuthService::login($username, $password);
    if ($result['success']) {
        return ['status' => 'success', 'user' => $result['user']];
    }

    Response::error($result['message'], 401, [
        'locked' => $result['locked'] ?? false,
        'remaining_seconds' => $result['remaining_seconds'] ?? 0
    ]);
});
```

### 10. Zero-Dependency JWT Engine (`Core\Jwt`)

Generate and verify HMAC-SHA256 (HS256) JSON Web Tokens without external Composer packages:

```php
use Core\Jwt;

// Encode claims into a signed JWT token (24-hour default TTL)
$token = Jwt::encode(['user_id' => 42, 'role' => 'admin']);

// Decode & verify signature / expiration
$claims = Jwt::decode($token); // Returns array payload or false if expired/invalid
```

### 11. Event Emitter with Redis Pub/Sub (`Core\Event`)

Listen and dispatch internal application events in memory, automatically broadcasting across Redis cluster workers if connected:

```php
use Core\Event;

// Register event listener
Event::listen('user.created', function($payload) {
    // Process async tasks or notifications
});

// Dispatch event locally and broadcast via Redis Pub/Sub
Event::dispatch('user.created', ['user_id' => 42, 'email' => 'user@example.com']);
```

### 12. Lila.js — Zero-Dependency Frontend Engine & SPA Router

`lila.js` (`frontend/js/lila.js`) is an optional ultra-lightweight (~350 lines, zero dependencies) frontend reactive engine and hash router designed specifically for LilaPHP.

- **Island Reactivity**: Mount reactive components anywhere on static HTML pages (`Lila.mount('#target', config)`).
- **Hash SPA Router**: Hash-based routing (`#/`, `#/products`) with built-in CSS fade transitions and skeleton loaders.
- **Deep State Proxy & DOM Morphing**: Fine-grained reactive state tracking with automatic DOM diffing (Morphing). Write pure ES6 literal templates (JSX style) without losing input focus.
- **Global Reactive Stores**: Shared state across independent components (`Lila.store('name', initial)`).
- **Built-in API Helpers**: Native wrappers like `Lila.fetch` for API calls, and `Lila.Validator` for client-side form validation.
- **Auth Guards & PWA Installer**: Global routing middleware (`Lila.beforeRoute`) for auth checks, and an automated PWA Service Worker installer (`Lila.start({ pwa: ... })`).

```javascript
const { html } = Lila;

// Register SPA routes using pure ES6 Literals
Lila.route('/', {
    state: () => ({ count: 0 }),
    template: (s) => html`
        <div>
            <h2>Count: <span>${s.count}</span></h2>
            <button data-action="inc">+</button>
        </div>
    `,
    actions: {
        inc: ({ state }) => state.count++
    }
});

Lila.start('#app');
```

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

## 🛠️ Master CLI Commands (`cli.php`)

```bash
php cli.php help                                # Display interactive help menu
php cli.php health                              # Microsecond system health & service diagnostic check
php cli.php benchmark --url=/api --concurrency=1000 --duration=30 # Real server-side OS-level load test (curl_multi)
php cli.php optimize                            # Pre-cache .env settings to OPcache memory and flush APCu/Redis
php cli.php key:generate                        # Generate secure 256-bit cryptographic APP_KEY in backend/.env
php cli.php migrate                             # Synchronize all API Model table schemas with MySQL
php cli.php migrate --refresh                   # Drop all database tables and re-run migrations from scratch
php cli.php seed                                # Populate database tables with initial seed records
php cli.php task:work                           # Start continuous background worker consuming Redis job queues
php cli.php ws:serve 8001 -d                    # Start Workerman WebSocket server in background daemon mode (-d)
php cli.php ws:serve stop 8001                  # Stop running background WebSocket daemon
php cli.php ws:serve restart 8001 -d            # Gracefully restart background WebSocket daemon
php cli.php make model <Name>                   # Generate boilerplate API Model inside backend/models/
php cli.php make route <path>                   # Generate file-based API route inside backend/routes/
php cli.php docker dev|prod|stop|ps|stats|logs|clean # Orchestrate Nginx, PHP, MySQL, Redis cluster & stream project stats
php cli.php docker stats                        # Stream real-time CPU/RAM stats filtered for project containers
php cli.php docker exec-php                     # Open interactive bash inside PHP container
php cli.php docker exec-mysql [query]           # Open interactive MySQL CLI or execute raw SQL query
php cli.php docker exec-redis [command]         # Open interactive Redis CLI or execute raw Redis command
php cli.php docker mysql <shortcut>              # Smart MySQL queries without SQL (tables, find, count, where, last...)
php cli.php docker redis <shortcut>              # Smart Redis commands (keys, get, set, logs, jobs, monitor, flush...)
```

---

## ⚡ Server-Side Concurrency Benchmark Engine (`curl_multi`)

LilaPHP includes an OS-level server-side load test runner built directly into the CLI and synchronized in real time with the web dashboard (`/debug.html`).

Unlike client-side browser tests (which are restricted by JavaScript's single-threaded event loop and browser socket connection limits), LilaPHP's benchmark runner uses native asynchronous `curl_multi_exec` sockets.

### CLI Usage:
```bash
# Run 1,000 concurrent connection stress test for 30 seconds
php cli.php benchmark --url=/api/init --concurrency=1000 --duration=30
```

### Key Metrics Recorded:
- **Req/Sec (RPS)**: Exact throughput handled by the engine.
- **Latency Percentiles**: Calculates exact **P50, P95, and P99** latency distributions.
- **Real-Time Redis Sync**: The CLI worker writes snapshots into Redis every ~500ms (`lilaphp:benchmark:{id}`), allowing `/debug.html` to visualize live load test metrics without interrupting execution.
- **Zero-Error Execution**: Measures 2xx successes vs 4xx/5xx errors under extreme socket concurrency.

---

## ⚡ Real-Time WebSockets (`Socket.IO Style with Workerman & Redis`)

LilaPHP includes a real-time event broadcasting engine (`Core\Ws`) bridged with **Workerman** (`composer require workerman/workerman`) and **Redis Pub/Sub**.

### 1. Backend Event Broadcasting (`Core\Ws`)
Inside any `backend/routes/*.php` or background job (e.g. when an item is updated or created):
```php
\Core\Ws::publish('item_updated', ['id' => 104, 'status' => 'shipped'], 'orders_room');
```

### 2. Security & Room Authentication Interceptor (`backend/sockets/Handler.php`)
You can intercept incoming connections, authenticate room join requests (verify passwords/JWT tokens), and handle custom incoming websocket messages before they are broadcasted by writing your logic in `backend/sockets/Handler.php`:

```php
namespace Sockets;

class Handler
{
    // Intercept client connections (assign unique socket IDs or check headers)
    public static function onConnect(object $connection): void
    {
        $connection->socket_id = 'user_' . bin2hex(random_bytes(4));
    }

    // Intercept room join attempts (`ws.join('admin_room', { password: 'secret' })`)
    public static function onJoin(object $connection, string $room, array $payload = []): bool
    {
        if ($room === 'admin_room') {
            if (($payload['password'] ?? '') !== 'secret123') {
                $connection->send(json_encode(['event' => 'error', 'message' => 'Unauthorized access']));
                return false; // Reject join
            }
        }
        return true; // Allow join
    }

    // Intercept client emits/broadcasts before Workerman retransmits to the room
    public static function onMessage(object $connection, string $event, array $payload, ?string $room, object $wsWorker): bool
    {
        if ($event === 'chat_message') {
            // Save to database, sanitize text, or log event
        }
        return true; // Return true to allow automatic broadcast to room members
    }

    public static function onClose(object $connection): void {}
}
```

### 3. Frontend Client (`js/ws.js`)
Include `/js/ws.js` on your frontend to connect via Nginx reverse proxy (`/ws` -> port `8001`). Packets sent while connecting are automatically buffered in memory (`sendQueue`) and flushed right after the handshake:

```javascript
const ws = new LilaWS('/ws');
ws.join('orders_room', { password: 'secret_if_required' });

// Listen for incoming live events
ws.on('item_updated', (data, room) => console.log(`Update in [${room}]:`, data));

// Emit to other clients in the room (excludes sender socket)
ws.emit('chat_message', { text: 'Hello others!' }, 'orders_room');

// Emit to ALL clients in the room (including the sender socket)
ws.broadcastAll('chat_message', { text: 'Hello everyone including me!' }, 'orders_room');
```

### 4. Workerman WebSocket CLI Management
You can control the Workerman WebSocket daemon directly using `cli.php`. Action commands can be passed before or after the port number:
```bash
# Start WebSocket server in background daemon mode (-d)
php cli.php ws:serve 8001 -d

# Stop the running WebSocket daemon
php cli.php ws:serve stop 8001
# Or: php cli.php ws:serve 8001 stop

# Gracefully reload/restart daemon without dropping active connections
php cli.php ws:serve restart 8001 -d

# Check live connections and worker status
php cli.php ws:serve status 8001
```

---

## 🔄 Running Workers & WebSockets Permanently in Background (`docker-compose.yml`)

To keep your background task workers and WebSocket server running continuously 24/7 in production without stopping when you close the terminal, add these permanent services inside your `docker-compose.yml`:

```yaml
services:
  # ... (existing php, nginx, mysql, redis containers) ...

  # Continuous background worker consuming Redis job queues (`lilaphp:jobs`)
  worker:
    build:
      context: .
      dockerfile: docker/php/Dockerfile.prod
    command: php cli.php task:work
    restart: always
    environment:
      - APP_ENV=production
    depends_on:
      - redis
      - mysql

  # Real-time Workerman WebSocket server daemon
  websocket:
    build:
      context: .
      dockerfile: docker/php/Dockerfile.prod
    command: php cli.php ws:serve 8001
    restart: always
    environment:
      - APP_ENV=production
    depends_on:
      - redis
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
