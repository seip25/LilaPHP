# LilaPHP — AI Assistant Guidelines

> **Version:** 1.42 | **Author:** Andrés Paiva (Seip25)  
> This file provides context and rules for AI assistants working on the **LilaPHP** codebase.

---

## 🏗️ Architecture Overview

LilaPHP is a **lightweight PHP micro-framework** using PHP 8+, Twig, Dotenv, and React (via Vite).

### Core Principles

- **Micro-App Pattern**: Each endpoint directory has its own `index.php` that includes the root `index.php` (bootstrap) and instantiates `Core\App`. There is **NO single global router file**. Each endpoint independently defines its GET/POST/PUT/DELETE handlers.
- **Middleware Flow**: Script execution must flow through `before` → route middlewares → handler → `after` callbacks. **DO NOT** use `exit;` or `die()` inside helpers like `jsonResponse` or model constructors. Let the dispatch loop complete to execute `after` callbacks properly.
- **PHP 8 Attributes**: Routing, CSRF, caching, validation, and SEO are configured via PHP Attributes (`#[GET]`, `#[POST]`, `#[CSRF]`, `#[Cache]`, `#[Validate]`, `#[Middleware]`, `#[SEO]`).
- **Virtual Language Routing**: The framework supports URL-based language detection (e.g., `/es/dashboard`). This automatically sets the session language and routes to the appropriate endpoint without physical subdirectories. **Can be disabled** globally via `.env` or per-instance via `App` options.
- **Dual Registration**: Routes can be registered either via closures (`$app->get(...)`, `$app->post(...)`) or via named functions with Attributes and `$app->add('functionName')`.

### File Layout

```
project-root/
├── AGENTS.md
├── index.php              ← Main bootstrap (autoload + Core\App)
├── cli.php                ← CLI entry point: php cli.php <command>
├── composer.json
├── package.json           ← Node dependencies (React, Vite)
├── vite.config.js         ← Vite + React HMR config
├── .env                   ← Environment configuration (Dotenv)
├── vendor/
├── lila/                  ← Internal: core framework and cache
│   ├── core/              ← Framework core classes (namespace: Core)
│   ├── cli/               ← CLI commands (namespace: Cli)
│   ├── cache/             ← Cache directory (env, routes, models)
│   ├── logs/              ← Log files
│   └── scaffold/          ← Initial templates and assets
├── models/                ← Application models (namespace: Models)
├── locales/               ← Translation files (en.php, es.php, etc.)
├── resources/             ← Frontend source files (Vite root)
│   ├── js/                ← React logic
│   └── templates/         ← Twig templates
├── assets/                ← Public static files (build output)
├── login/index.php        ← Scaffolded endpoint
└── dashboard/index.php    ← Scaffolded endpoint
```

---

## 🔀 Routing

### Closure-based (inline)

```php
include_once "./index.php";
use Core\App;
use Core\Response;

$app = new App();
/*
Example with logger and cors
$app = new App([
    'security' => [
        'cors' => false,
        'sanitize' => true,
        'logger' => true,
        'rateLimit' => 200
    ],
    'translate' => false
]);
*/
$app->get(callback: function(array $req, Response $res) {
    return $res->render("home", ['title' => 'Welcome']);
});

$app->post(callback: function(array $req, Response $res) {
    return $app->jsonResponse(["success" => true]);
}, csrf: true, middlewares: [fn($req, $res) => new LoginModel(data: $req)]);
```

### Attribute-based (named functions)

```php
use Core\{GET, POST, CSRF, Validate, Cache, Response};

#[GET]
function showPage(array $req, Response $res) {
    return $res->render("page");
}
$app->add('showPage');

#[POST]
#[CSRF]
#[Validate(LoginModel::class)]
function handleSubmit(array $req, Response $res) {
    return $res->jsonResponse(["success" => true]);
}
$app->add('handleSubmit');
```

### Available Attributes

| `#[GET]`, `#[POST]`, `#[PUT]`, `#[DELETE]` | HTTP method binding                 |
| `#[CSRF]`                                  | Enable CSRF token validation        |
| `#[Cache(seconds: 60)]`                    | Response caching middleware         |
| `#[Validate(ModelClass::class)]`           | Auto-validate request against model |
| `#[Middleware(callable)]`                  | Attach middleware to route          |
| `#[Admin]`                                 | Protect route with Admin Portal     |
| `#[SEO(key: "...")]`                       | Define page metadata. Resolves automatically from `locales/seo.php` based on session language |
| `#[AUTH(key: "auth", decrypt: true, redirect: "/login")]` | Verify session existence before executing route. Redirects to /login or returns 401 if failed |

---

## 🧩 Core Classes & Auto-wiring

LilaPHP natively supports **Auto-wiring Dependency Injection** via Reflection for all route handlers. You do not need to use `global $app` or instantiate core database connections manually.

```php
use Core\Response;
use Core\Session;
use Core\Config;
use Core\Database;
use Core\Translate;

#[GET]
function dashboard(array $req, Response $res, Session $session, Config $config, Database $db, Translate $translate) {
    // 1. Session injected
    $lang = $session::get('lang') ?? $translate::getLang();

    // 2. Config static properties (Loaded automatically from .env)
    $debugMode = $config::$DEBUG;
    $url = $config::$URL_PROJECT;

    // 3. Database connection injected (defaults to .env settings implicitly)
    $pdo = $db->getConnection();

    return $res->render('dashboard', ['lang' => $lang]);
}
```

### Static Core Usage vs Injection

Most core classes can be used globally via static scope OR injected cleanly into handlers:

- **`Config`**: Exposes `.env` variables via static properties: `Config::$DEBUG`, `Config::$URL_PROJECT`, `Config::$PATH_LOGS`, `Config::$VERSION_PROJECT`, etc.
- **`Translate`**: Dynamically accesses translations globally: `Translate::get('key')`, `Translate::getLang()`.
- **`Database`**: When injected as `Database $db`, its constructor auto-resolves `.env` DB configuration. No manual instantiation needed.
- **`Session`**: Provides static management: `Session::get()`, `Session::set()`, `Session::has()`.
- **`Logger`**: Resolves logs dynamically: `Logger::info()`, `Logger::error()`.

Always favor Auto-wiring these via the method signature so components remain fully decoupled.

---

## 🛡️ Security Stack

All security runs **before** middlewares via `Security::runBeforeMiddlewares()`:

1. **Logger** — Logs every request (masks passwords, CSRF tokens, emails)
2. **CORS** — Configurable origins, methods, headers, credentials
3. **CSP** — Content Security Policy headers (configurable directives)
4. **Rate Limiting** — Session-based, configurable per-minute limit (default: 200)
5. **Sanitization** — Trims strings, removes null bytes
6. **Payload Check** — XSS pattern detection (`<script>`, `onerror=`, `javascript:`)
7. **CSRF** — Token generation via `Security::generateCsrfToken()`, validation via `Security::validateCsrfToken()`

### CSP + Vite

When Vite dev server runs on `localhost:5173`, ensure CSP directives include:

- `script-src`: `http://localhost:5173`
- `style-src`: `http://localhost:5173`
- `connect-src`: `ws://localhost:5173`

---

## 🗃️ Models & Validation

Models extend `Core\BaseModel` and use two attribute systems:

### `#[Field(...)]` — Request Validation

Triggered automatically when instantiating: `new ModelClass(data: $req, lang: 'es')`
Throws `ValidationException` on failure.

```php
#[Field(required: true, format: 'email')]
public string $email;

#[Field(required: true, min_length: 6, max_length: 100)]
public string $password;
```

**Supported formats**: `email`, `url`, `ip`, `uuid`, `number`, `integer`, `float`, `boolean`, `date`, `datetime`, `alpha`, `alphanumeric`, `numeric`, `phone`, `credit_card`, `domain`, `mac_address`, `json`, `base64`, `regex`

### `#[FieldDatabase(...)]` — Database Schema

Used by CLI migrations to auto-create tables.

```php
#[FieldDatabase(type: 'int', primaryKey: true, autoIncrement: true, unsigned: true)]
protected int $id;

#[FieldDatabase(type: 'varchar', length: 255, unique: true, index: true)]
public string $email;

#[FieldDatabase(type: 'datetime', default: 'CURRENT_TIMESTAMP')]
protected string $created_at;
```

**Active Record Methods**:
Automatically inherited from `BaseModel`.

```php
$user = UserModel::find(1);
$activeUsers = UserModel::where('is_active', '=', 1);
$allUsers = UserModel::all();
$user->save();
$user->delete(logic: true);
```

**Table naming**: `UserProfile` → `user_profiles` (snake_case + pluralized).

---

## 🛠️ Admin Portal

The Admin Portal is a built-in, zero-configuration dashboard that automatically discovers and manages your models.

### Setup & Authentication

1.  **Create Endpoint**: Create an `Admin/index.php` file using the `#[Admin]` attribute.
2.  **Add User**: Run `php cli.php admin:add` to create the initial administrator.
3.  **Automatic Discovery**: The portal uses Reflection to find all classes extending `BaseModel` (excluding internal ones like `Admin`) and provides a responsive dashboard with search and pagination.

```php
use Core\{Admin, Response};

#[Admin]
function adminPortal(array $req, Response $res) {
    // AdminPortal::handle() is called automatically via Method middleware
}
$app->add('adminPortal');
```

### Features
- **Auto-Discovery**: Displays all models found in `models/`.
- **Sensitive Data Filtering**: Automatically hides fields like `password`, `token`, `hash`, `secret`, `key`.
- **Search & Pagination**: Server-side filtering and slicing for large datasets.
- **Mobile Drawer**: Fully responsive navigation for mobile devices.

---

## 🎨 Frontend: Twig + React Islands

### Twig Helpers

| Helper                                        | Usage                                 |
| --------------------------------------------- | ------------------------------------- |
| `{{ url('path') }}`                           | Generates full URL from `URL_PROJECT` |
| `{{ csrf_input() }}`                          | Hidden CSRF input field               |
| `{{ image('path', width, height) }}`          | Optimized WebP image URL              |
| `{{ translate('key') }}` or `{{ __('key') }}` | Translation lookup                    |
| `{{ react('ComponentName', {props}) }}`       | Mount React island component          |
| `{{ vite_assets() }}`                         | Inject Vite dev/prod scripts          |

### React Islands Architecture

- Components live in `resources/js/pages/*.jsx` and `resources/js/components/*.jsx`
- Auto-discovered by `main.jsx` via `import.meta.glob`
- Twig helpers `{{ react('ComponentName') }}` mount islands via `data-react-component="ComponentName"`. These files are searched for **strictly within `resources/js/components/`**.
- Full page renders `$res->renderReact('PageComponent')` mount via `data-react-page="PageComponent"`. These files are searched for **strictly within `resources/js/pages/`**.
- **Re-render from JS**: `window.renderReactComponent('ComponentName', 'component')` or `window.renderReactComponent('PageComponent', 'page')`

### React Full Page Render

```php
$res->renderReact('PageComponent', $props, [
    'lang' => 'es',
    'title' => 'Page Title',
    'meta' => [['name' => 'description', 'content' => '...']],
    'scripts' => ['https://cdn.tailwindcss.com'],
    'styles' => []
]);
```

---

## 🚀 Single Page Application (SPA) Support

LilaPHP features a native SPA engine (`assets/js/spa.js`) that enables instant transitions between Twig views and React islands without full page reloads.

### How it works
1. **Interceptor**: `spa.js` intercepts internal link clicks.
2. **Partial Request**: It appends `?source=frontend` to the URL.
3. **JSON Response**: The `Template` core detects the source and returns a JSON containing `body` (HTML partial), `meta`, `scripts`, `css`, and `props`.
4. **DOM Update**: `spa.js` updates `#lila-spa-content`, injects missing assets into `<head>`, and executes embedded scripts.
5. **React Re-sync**: Automatically triggers `window.renderReactComponent()` if available.

### Usage & Conventions
- **Container**: The main content must be wrapped in `<main id="lila-spa-content">` in `base.twig`.
- **Dynamic Layout**: All Twig templates must use `{% extends layout | default("base.twig") %}`.
- **Exclusion**: Add `data-no-spa` attribute to any link to force a full page reload.
- **Error Handling**: If the server returns 401/403 (Unauthorized/Forbidden), the SPA engine performs a full reload to allow proper session handling (e.g., redirecting to login).

### Authentication Attribute `#[AUTH]`
Automatically protects routes by checking for a session key. Fully compatible with `app:optimize` attribute caching.

```php
use Core\{GET, AUTH, Response};

#[GET]
#[AUTH(key: 'auth', decrypt: true, redirect: '/login')]
function dashboard(array $req, Response $res) {
    return $res->render('dashboard');
}
```
If the session key is missing, it will redirect to `/login` (default). If `redirect` is set to `false`, it returns a 401 response (which triggers a full reload in SPA mode).

---

## 🗄️ Database & CLI

### Database Connection

```php
$db = $app->getDatabaseConnection(); // Uses .env config
// Returns PDO instance with retry logic (up to 5 attempts)
```

**Providers**: `mysql`, `pgsql`, `sqlite`

### CLI Commands

```bash
php cli.php init               # Initialize scaffolding from lila/scaffold/
php cli.php migrate:create     # Create DB + tables from models
php cli.php migrate:run        # Run pending migrations
php cli.php migrate:fresh      # Drop all + re-migrate
php cli.php migrate:status     # Show migration status
php cli.php model:create Name  # Generate model file
php cli.php seed:create Name   # Generate seeder file
php cli.php seed:run           # Run all seeders
php cli.php test:run           # Run all unit tests (Recursive .test.php discovery)
php cli.php assets:minify      # Minify all assets in assets/
php cli.php sitemap:generate   # Generate multilingual sitemap.xml
php cli.php admin:add          # Create or update an admin user
php cli.php key:generate       # Generate a secure random SECRET_KEY
php cli.php app:optimize       # Unified production optimization
```

### Test Runner
The Test Runner (`test:run`) looks for files ending in `.test.php` within the project. It handles exceptions and performance tracking for each test suite.

### Scheduler
The Scheduler (`schedule:run`) executes tasks defined in `tasks.php`. It should be hooked into a system Cron job (e.g., `* * * * * php /path/to/cli.php schedule:run`).

---

## 📦 Response Types

```php
$res->jsonResponse(['key' => 'value'], 200);        // JSON
$res->render('template', ['var' => 'val']);           // Twig HTML
$res->renderReact('Component', $props, $options);     // React full page
$res->redirect('/path');                              // Redirect

// Static Response methods
Response::JSON($data, $status);
Response::HTML($html, $status);
Response::File($path, $downloadName, $inline);
Response::Stream($callback, $status, $contentType);
Response::Text($text, $status);
Response::Redirect($url, $status);
Response::NoContent();
```

---

## 🔐 Sessions

```php
$app->setSession('key', $value);                    // Store value
$app->setSession('auth', $user, encrypt: true);     // AES-256-GCM encrypted
$app->getSession('key');                            // Retrieve
$app->getSession('auth', null, decrypt: true);      // Decrypt
$app->hasSession('key');                            // Check exists
$app->removeSession('key');                         // Remove
$app->destroySession();                             // Destroy all
```

Sessions auto-regenerate every 5 minutes, expire after 30min inactivity, and validate IP + User-Agent.

---

## 🌍 Translations

Locale files in `locales/{lang}.php` return associative arrays.
Validation messages in `locales/validation_{lang}.php`.

```php
$app->translate('key');       // PHP
{{ translate('key') }}        // Twig
{{ __('key') }}               // Twig shorthand
```

Language switching: `?set-lang=true&lang=es` (auto-handled by framework).

### Single Language Mode
If your application only supports one language, you can disable the translation system globally in `.env` or per `App` instance.
- **Global**: Set `TRANSLATE=false` in `.env`.
- **Instance**: Pass `['translate' => false]` to `new App()`.

When disabled:
- URL prefixes (e.g., `/es/`) are ignored and NOT generated by the `url()` helper.
- `set-lang` logic is skipped.
- Sitemap generator only includes the default language without prefixes.

---

## ⚙️ Configuration (.env)

```env
TITLE_PROJECT="App Name"
DEBUG=true                    # true=dev mode, false=production (Twig cache, minification)
VERSION_PROJECT="0.1"
SECRET_KEY="your-secret-key"  # Used for session encryption
URL_PROJECT="http://localhost/project"
LANG="es"                    # Default language (en, es, pt-br, pt)
TRANSLATE=true               # Enable/Disable multi-language support (default: true)
DB_PROVIDER="mysql"           # mysql, pgsql, sqlite
DB_NAME="dbname"
DB_USER="root"
DB_PASSWORD=""
DB_HOST="localhost"
DB_PORT="3306"
```

---

## ⚠️ Rules for AI Assistants

1. **Never add `exit;` or `die()`** in response helpers or middlewares — let the dispatch loop complete
2. **Leverage Auto-wiring for Dependencies** — Never use `global $app` or instantiate `Database` directly in routes. Inject dependencies via handler arguments: `function(Database $db, Config $config)` which replaces older patterns like `$app->getDatabaseConnection()`.
3. **Validation is automatic** — instantiating `new Model($data)` triggers validation in the constructor
4. **Translations are separated** — app translations in `locales/{lang}.php`, validation messages in `locales/validation_{lang}.php`
5. **Exceptions go in `lila/core/`** — e.g., `ValidationException.php`, to comply with PSR autoloading
6. **React components go in `resources/js/pages/`** — they are auto-discovered by filename
7. **Each endpoint has its own `index.php`** — do NOT create a centralized router
8. **CSP matters** — when adding external scripts/CDNs, update the CSP directives in the `App` constructor
9. **CSRF for mutations** — POST/PUT/DELETE routes should use `csrf: true` or `#[CSRF]` attribute
10. **Use named parameters** — LilaPHP code style uses PHP 8 named arguments extensively
11. **Auto-wiring DI is the Standard** — Write fully independent endpoint controllers utilizing the native DI. Auto-wiring handles resolution regardless of parameter order.
12. **Twig Block Convention** — Standard layouts (e.g., `base.twig`) use `{% block content %}` for the main body area. Avoid using `{% block body %}`. This is the default framework pattern to accelerate template development, although React full-page rendering remains a more flexible alternative.
13. **SEO and Localization** — Use standardized 2-letter ISO codes (e.g., `en`, `es`, `pt`) for translations. Always use the `url()` helper to benefit from automatic language-prefix routing. Use the `#[SEO(key: "...")]` attribute on main public-facing routes to resolve metadata from the array defined in `locales/seo.php`.
