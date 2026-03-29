# LilaPHP — AI Assistant Guidelines

> **Version:** 1.1.8 | **Author:** Andrés Paiva (Seip25)  
> This file provides context and rules for AI assistants working on the **LilaPHP** codebase.

---

## 🏗️ Architecture Overview

LilaPHP is a **lightweight PHP micro-framework** using PHP 8+, Twig, Dotenv, and React (via Vite).

### Core Principles

- **Micro-App Pattern**: Each endpoint directory has its own `index.php` that includes `app/index.php` (bootstrap) and instantiates `Core\App`. There is **NO single global router file**. Each endpoint independently defines its GET/POST/PUT/DELETE handlers.
- **Middleware Flow**: Script execution must flow through `before` → route middlewares → handler → `after` callbacks. **DO NOT** use `exit;` or `die()` inside helpers like `jsonResponse` or model constructors. Let the dispatch loop complete to execute `after` callbacks properly.
- **PHP 8 Attributes**: Routing, CSRF, caching, and validation are configured via PHP Attributes (`#[GET]`, `#[POST]`, `#[CSRF]`, `#[Cache]`, `#[Validate]`, `#[Middleware]`).
- **Dual Registration**: Routes can be registered either via closures (`$app->get(...)`, `$app->post(...)`) or via named functions with Attributes and `$app->add('functionName')`.

### File Layout

```
project-root/
├── AGENTS.md
├── index.php              ← Main endpoint (includes app/index.php)
├── login/index.php        ← Login endpoint
├── register/index.php     ← Register endpoint
├── dashboard/index.php    ← Dashboard endpoint
├── api/
│   └── */index.php        ← API endpoints
├── assets/                ← Public static files (images, build output)
├── composer.json
├── vendor/
└── app/
    ├── .env               ← Environment configuration (Dotenv)
    ├── index.php           ← Bootstrap: autoload + new App()
    ├── vite.config.js      ← Vite + React HMR config
    ├── package.json        ← Node dependencies (React, Vite)
    ├── core/               ← Framework core classes (namespace: Core)
    │   ├── App.php         ← Main application (routing, dispatch, rendering)
    │   ├── BaseModel.php   ← Abstract model: validation + DB schema via Attributes
    │   ├── Config.php      ← Dotenv loader, static config
    │   ├── Database.php    ← PDO wrapper (MySQL, PostgreSQL, SQLite) + retry logic
    │   ├── Debug.php       ← SQLite-based request tracker (dev mode)
    │   ├── ImageOptimizer.php ← WebP/ICO on-demand image optimization via GD
    │   ├── Logger.php      ← File-based logger (Y/M/D directory structure)
    │   ├── Method.php      ← PHP Attributes: GET, POST, PUT, DELETE, CSRF, Cache, Validate, Middleware
    │   ├── Response.php    ← Response helpers: JSON, HTML, File, Stream, Text, Redirect, cacheResponse()
    │   ├── Security.php    ← CORS, CSP, CSRF, rate limiting, sanitization, payload XSS check
    │   ├── Session.php     ← Secure sessions with AES-256-GCM encryption, IP/UA binding, auto-regeneration
    │   ├── Template.php    ← Twig renderer + React full-page render + Vite asset injection
    │   ├── Translate.php   ← i18n: loads locale files from app/locales/
    │   └── ValidationException.php ← Renders validation errors as JSON or HTML
    ├── cli/                ← CLI commands (namespace: Cli)
    │   ├── Command.php     ← Base CLI command class
    │   ├── Migrate.php     ← Migration commands (create, run, rollback, fresh, status)
    │   ├── Seed.php        ← Seeder commands
    │   └── Model.php       ← Model generator command
    ├── cli.php             ← CLI entry point: php app/cli.php <command>
    ├── models/             ← Application models (namespace: Models)
    ├── templates/          ← Twig templates
    │   └── lila/           ← Framework internal templates (debug, react_base)
    ├── resources/          ← React source (Vite root)
    │   ├── main.jsx        ← React island bootstrapper
    │   ├── components/     ← Reusable React components
    │   └── pages/          ← React pages/islands (auto-discovered by name)
    ├── locales/            ← Translation files (eng.php, esp.php, bra.php + validation_*.php)
    ├── logs/               ← Log files (auto-organized by date)
    └── lila/               ← Internal: debug.sqlite, build_manifest.php
```

---

## 🔀 Routing

### Closure-based (inline)

```php
include_once "./app/index.php";
use Core\App;
$app=new App();
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
$app->get(callback: function($req, $res) use ($app) {
    return $app->render("home", ['title' => 'Welcome']);
});

$app->post(callback: function($req, $res) use ($app) {
    return $app->jsonResponse(["success" => true]);
}, csrf: true, middlewares: [fn($req, $res) => new LoginModel(data: $req)]);
```

### Attribute-based (named functions)

```php
use Core\{GET, POST, CSRF, Validate, Cache};

#[GET]
function showPage($req, $res) {
    global $app;
    return $app->render("page");
}
$app->add('showPage');

#[POST]
#[CSRF]
#[Validate(LoginModel::class)]
function handleSubmit($req, $res) {
    global $app;
    return $app->jsonResponse(["success" => true]);
}
$app->add('handleSubmit');
```

### Available Attributes

| Attribute                                  | Purpose                             |
| ------------------------------------------ | ----------------------------------- |
| `#[GET]`, `#[POST]`, `#[PUT]`, `#[DELETE]` | HTTP method binding                 |
| `#[CSRF]`                                  | Enable CSRF token validation        |
| `#[Cache(seconds: 60)]`                    | Response caching middleware         |
| `#[Validate(ModelClass::class)]`           | Auto-validate request against model |
| `#[Middleware(callable)]`                  | Attach middleware to route          |

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

Triggered automatically when instantiating: `new ModelClass(data: $req, lang: 'esp')`
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

**Table naming**: `UserProfile` → `user_profiles` (snake_case + pluralized).

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

- Components live in `app/resources/pages/*.jsx`
- Auto-discovered by `main.jsx` via `import.meta.glob('./pages/*.jsx')`
- Mount to DOM via `data-react-component="ComponentName"` attribute
- **Re-render from JS**: `window.renderReactComponent('ComponentName')` or `window.renderReactComponent()` for all

### React Full Page Render

```php
$app->renderReact('PageComponent', $props, [
    'lang' => 'es',
    'title' => 'Page Title',
    'meta' => [['name' => 'description', 'content' => '...']],
    'scripts' => ['https://cdn.tailwindcss.com'],
    'styles' => []
]);
```

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
php app/cli.php migrate:create     # Create DB + tables from models
php app/cli.php migrate:run        # Run pending migrations
php app/cli.php migrate:fresh      # Drop all + re-migrate
php app/cli.php migrate:status     # Show migration status
php app/cli.php model:create Name  # Generate model file
php app/cli.php seed:create Name   # Generate seeder file
php app/cli.php seed:run           # Run all seeders
```

---

## 📦 Response Types

```php
$app->jsonResponse(['key' => 'value'], 200);        // JSON
$app->render('template', ['var' => 'val']);           // Twig HTML
$app->renderReact('Component', $props, $options);     // React full page
$app->redirect('/path');                              // Redirect

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

Locale files in `app/locales/{lang}.php` return associative arrays.
Validation messages in `app/locales/validation_{lang}.php`.

```php
$app->translate('key');       // PHP
{{ translate('key') }}        // Twig
{{ __('key') }}               // Twig shorthand
```

Language switching: `?set-lang=true&lang=esp` (auto-handled by framework).

---

## ⚙️ Configuration (.env)

```env
TITLE_PROJECT="App Name"
DEBUG=true                    # true=dev mode, false=production (Twig cache, minification)
VERSION_PROJECT="0.1"
SECRET_KEY="your-secret-key"  # Used for session encryption
URL_PROJECT="http://localhost/project"
LANG="esp"                    # Default language (eng, esp, bra, por)
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
2. **Always use `$app->getDatabaseConnection()`** — never instantiate `Database` directly in routes
3. **Validation is automatic** — instantiating `new Model($data)` triggers validation in the constructor
4. **Translations are separated** — app translations in `locales/{lang}.php`, validation messages in `locales/validation_{lang}.php`
5. **Exceptions go in `app/core/`** — e.g., `ValidationException.php`, to comply with PSR autoloading
6. **React components go in `app/resources/pages/`** — they are auto-discovered by filename
7. **Each endpoint has its own `index.php`** — do NOT create a centralized router
8. **CSP matters** — when adding external scripts/CDNs, update the CSP directives in the `App` constructor
9. **CSRF for mutations** — POST/PUT/DELETE routes should use `csrf: true` or `#[CSRF]` attribute
10. **Use named parameters** — LilaPHP code style uses PHP 8 named arguments extensively
