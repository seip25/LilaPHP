# LilaPHP Framework

 
 

**A lightweight, modular, and modern PHP micro-framework**

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.0-8892BF.svg)](https://php.net)

[Documentation](https://seip25.github.io/LilaPHP/) • [GitHub](https://github.com/seip25/LilaPHP)

 

---

## 🌟 Overview

**LilaPHP** is a lightweight and modular PHP micro-framework designed for **simplicity**, **flexibility**, and **security**. It provides a minimal yet powerful foundation for building web applications with clean routing, middleware, Twig templates, and Dotenv configuration.

LilaPHP gives you **control** and **performance** — no boilerplate, no heavy dependencies, just clean, fast PHP.

---

## ✨ Why Choose LilaPHP?

LilaPHP was designed with one clear purpose — to give developers **full control**, **performance**, and **simplicity** without the unnecessary overhead of large, opinionated frameworks.

### 🎯 Key Features

- ⚡ **Lightweight core** — Loads only what's needed for each request
- 🧩 **Modular structure** — Each app, route, or endpoint can define its own configuration
- 🔒 **Secure by default** — Built-in CSRF protection, input sanitization, and isolated session handling
- 🪶 **SEO-friendly** — Clean URLs, optimized image helpers, and auto-generated metadata
- 🖥️ **Universal compatibility** — Works on NGINX, Apache, FrankenPHP, Swoole, VPS, or shared hosting
- 🧠 **Developer experience first** — Instant setup, clear routing, and intuitive Twig integration
- 🚀 **Performance oriented** — Minimal I/O, cached helpers, and pre-optimized rendering for production
- 🌐 **Multi-language support** — Built-in localization system with English, Spanish, Portuguese, and Brazilian Portuguese
- ⚡ **SPA Ready** — Built-in Single Page Application engine (`spa.js`) for instant transitions between Twig views using Vanilla JS

### 🔥 Modular Architecture

Each entry file can instantiate the framework with its own configuration, giving you complete control over security, middleware, and session behavior.

```php
// LilaPHP/index.php - Default configuration
$app = new App();
```

```php
// api/test.php - Custom configuration
$app = new App([
    'security' => [
        'cors' => false,
        'sanitize' => false,
        'logger' => true
    ]
]);
```

---

## 📦 Installation

### Install with composer

```bash
composer create-project seip25/lila-php LilaPHP
```

 

Edit `.env` and update the base URL:

```env
URL_PROJECT=http://localhost/LilaPHP #or "http://localhost:8000/" 
DEBUG=true
LANG="en"
TRANSLATE=true # Set to false for single-language apps
```

### 🌐 Server Setup

LilaPHP can be run locally using **Docker** (recommended) or traditional web servers like **Apache (XAMPP/MAMP/LAMPP)**.

#### Option A: Docker Dev Environment (Recommended)

LilaPHP includes convenience scripts (`app/docker/dev.sh` and `app/docker/dev.bat`) to manage your development containers manually. By default, containers do not restart automatically on system startup, saving valuable CPU and RAM when you use your computer for gaming or other non-development activities.

```bash
# 1. Build and start containers (Nginx + PHP 8.4-FPM + MySQL)
./app/docker/dev.sh build

# 2. Run migrations inside the container
docker exec -it lilaphp_dev_php php app/cli.php migrate:create

# 3. Stop the containers when you're done coding to free up resources
./app/docker/dev.sh stop

# 4. Start them back up later without rebuilding
./app/docker/dev.sh start
```

Your app will be running at `http://localhost:8000`.

#### Option B: Local Web Server (XAMPP / MAMP / LAMPP)

1. Move the framework folder into your server's public root (e.g. `htdocs/LilaPHP`).
2. The root `.htaccess` handles trailing slashes, extensionless URLs, and blocks framework directory access automatically.
3. If placed inside a subdirectory, update `RewriteBase` inside the root `.htaccess`:
   ```apache
   RewriteBase /LilaPHP/
   ```
4. Generate your local application key:
   ```bash
   php app/cli.php key:generate
   ```

Your app will be running at `http://localhost/LilaPHP/`.


### Frontend Assets & Hot Reload

Install dependencies and build/run the dev server with Vite:

```bash
cd app
npm install
npm run dev    # Start local Vite development server for hot-reload
npm run build  # Build production-ready assets
```

## 📁 Project Structure

```
LilaPHP/
├── app/               # Application & Framework core
│   ├── core/          # Core classes (App, Database, Config, Template, etc.)
│   ├── cli/           # CLI commands
│   ├── models/        # Database models (Scanned by CLI)
│   ├── tasks/         # Scheduled tasks
│   ├── locales/       # Multilingual files
│   ├── resources/     # Templates (Twig) & source JS (flat structure)
│   │   ├── app/       # Framework debug layouts & admin views
│   │   ├── index.twig # Homepage template
│   │   ├── base.twig  # Base HTML template
│   │   └── main.js    # Entry point JS for Vite compilation
│   ├── cache/         # Cache files (routes, models, templates)
│   ├── logs/          # Log files
│   ├── .env           # Environment configuration
│   ├── package.json   # Frontend manifest
│   └── vite.config.js # Vite configuration
├── assets/            # Public web assets (CSS, JS, images, build output)
├── api/               # API endpoints
├── index.php          # Homepage / Main web entry point
├── login.php          # Login page entry point
├── 404.php            # 404 error page
├── cache-response.php # Cache example entry point
├── composer.json      # Composer dependencies & autoloading
└── .htaccess          # Apache URL rewriting & security
```

---

## 🚀 Quick Start

### Basic Routing (Attributes & DI)

```php
<?php
// LilaPHP/index.php
require_once __DIR__ . '/app/bootstrap.php';

use Core\{App, GET, Response, Session, SEO};

$app = new App();

#[GET]
#[SEO(key: "home")]
function home(Response $res, Session $session) {
    return $res->render("index", [
        "user" => $session::get("user_id")
    ]);
}

$app->add('home');
$app->run();
```


### Validation with Attributes

```php
use Core\{BaseModel, Field, POST, Response};

class LoginModel extends BaseModel
{
    #[Field(required: true, format: "email")]
    public string $email;

    #[Field(required: true, min_length: 6)]
    public string $password;
}

#[POST]
function login(array $req, Response $res) {
    $model = new LoginModel(data: $req); // Throws exception on failure
    return $res->jsonResponse(["success" => true]);
}

$app->add('login', csrf: true);
```

### 🚀 Single Page Application (SPA)

LilaPHP includes a native SPA engine (`assets/js/spa.js`) that intercepts links and performs partial DOM updates.

- **Twig & Vanilla JS**: Seamlessly switch between static Twig templates and dynamic Vanilla JS logic.
- **Smart Loading**: Automatically detects and injects missing CSS or JS assets during transitions.
- **Dynamic Layouts**: Uses a conditional layout system to return only the necessary HTML fragment.
- **Fail-safe**: Automatic fallback to traditional navigation on timeout or server error.

To use it, ensure your main content is inside `<main id="lila-spa-content">` and your templates extend the dynamic layout:
```twig
{% extends layout | default("base.twig") %}
```

### 🔐 Authentication Attribute

Secure your routes declaratively using PHP 8 Attributes. By default, it redirects to `/login` if no session is found.

```php
use Core\{GET, AUTH, Response};

#[GET]
#[AUTH(key: "auth", decrypt: true, redirect: "/login")]
function dashboard($req, $res) {
    return $res->render("dashboard");
}
```

- **Default behavior**: Redirects to `/login`.
- **Pure 401**: Use `redirect: false` to return a 401 Unauthorized status.
- **Cache Ready**: Fully compatible with the `app:optimize` attribute caching system.

### Sessions

```php
$app->setSession("user_id", 1);
$user = $app->getSession("user_id");
$app->removeSession("user_id");
```

---

## 🌍 Localization

LilaPHP includes native support for multi-language applications.

**Define default language in `.env`:**

```env
LANG_DEFAULT="esp"
```

**Translation files structure:**

```
locales/
├── esp.php
├── eng.php
├── bra.php
└── por.php
```

**Example translation file:**

```php
<?php
// locales/eng.php
return [
    "welcome" => "Welcome!",
    "login" => [
        "title" => "Login",
        "email" => "Email address"
    ]
];
```

**Using in Twig templates:**

```twig
<h1>{{ translate("welcome") }}</h1>
<h2>{{ translate("login.title") }}</h2>
```

**Dynamic language switching:**

```php
$app->get(callback: function($req, $res) use ($app) {
    $lang = $_GET['lang'] ?? $app->getLangDefault();
    $app->setSession("lang", $lang);
    $back = $_SERVER['HTTP_REFERER'] ?? '/';
    $app->redirect($back);
});
```

### 🎯 Single Language Mode
If your application only supports one language, you can disable the translation system to remove URL prefixes and simplify routing.

- **Global**: Set `TRANSLATE=false` in `.env`.
- **Per Instance**: Pass `['translate' => false]` to `new App()`.

**Key effects:**
- **Clean URLs**: The `url()` helper will NOT include language prefixes (e.g., `/es/`).
- **Standard Routing**: Virtual language detection and `set-lang` are disabled.
- **Single Sitemap**: The sitemap generator will only include URLs without prefixes.

---

## 🔒 Security & CSRF Protection

LilaPHP includes built-in CSRF protection for POST, PUT, and DELETE requests.

```php
$app->post(
    callback: fn($req, $res) => $app->jsonResponse(["success" => true]),
    middlewares: [fn($req, $res) => new LoginModel(data: $req)],
    csrf: true
);
```

**In your Twig template:**

```html
<form method="POST">
    {{ csrf_input() }}
    <input type="email" name="email" required />
    <input type="password" name="password" required />
    <button type="submit">Login</button>
</form>
```

---

## 🎨 Twig Template Functions

LilaPHP extends Twig with powerful built-in helpers:

- **`image(file, width, height, quality, type)`** — Generates optimized WebP or ICO images
- **`url(path)`** — Returns the full absolute project URL. Automatically handles language prefixes if `TRANSLATE` is enabled.
- **`csrf_input()`** — Outputs the hidden CSRF token field
- **`translate(key)`** or **`__(key)`** — Returns translated strings

**Example:**

```twig
<link rel="icon" href="{{ image('img/lila.png', 40, 0, 70, 'ico') }}" />
<img src="{{ image('img/lila.png', 200) }}" width="200" alt="LilaPHP" />
```

---

## 🗄️ Database (PDO)

LilaPHP provides a unified database connection layer with automatic retry logic.

**Configure in `.env`:**

```env
DB_PROVIDER="mysql"
DB_HOST="localhost"
DB_USER="root"
DB_PASSWORD=""
DB_NAME="db_test"
DB_PORT="3306"
```

**Using the database:**

```php
$app->get(callback: function ($req, $res) use ($app) {
    $db = $app->getDatabaseConnection();
    $sql = "SELECT * FROM `users`";
    $result = $db->query($sql);
    $users = $result->fetchAll();

    return $app->render(template: "login", context: ["users" => $users]);
});
```

**Multiple database connections:**

```php
// Default connection
$db1 = $app->getDatabaseConnection();

// Custom connection
$db2 = $app->getDatabaseConnection(
    provider: "pgsql",
    host: $app->getEnv("DB_HOST_2"),
    dbUser: $app->getEnv("DB_USER_2"),
    dbPassword: $app->getEnv("DB_PASSWORD_2"),
    dbName: $app->getEnv("DB_NAME_2"),
    port: 5432
);
```

---

## 🔧 CLI Commands and Automation

LilaPHP includes a powerful CLI system for database management, background tasks, and production optimization.

### 🚀 Production Hardening

Before deploying to production, use the unified optimization command to ensure maximum performance and security.

```bash
# Unified optimization (config + models + assets + health check)
php app/cli.php app:optimize

# Generate a secure random SECRET_KEY for .env
php app/cli.php key:generate
```

### 📅 Task Scheduling

Automate background jobs by defining them in `tasks/tasks.php`.

```php
// tasks/tasks.php
use Core\Schedule;

Schedule::call(function() {
    // Your logic here
})->everyMinute();

Schedule::command('migrate:run')->dailyAt('02:00');
```

Run the scheduler via cron: `* * * * * php app/cli.php schedule:run >> /dev/null 2>&1`

### 🔍 SEO & Assets

```bash
# Generate sitemap.xml and robots.txt automatically
php app/cli.php sitemap:generate

# Minify CSS and JS files in assets/
php app/cli.php assets:minify
```

### 🗄️ Database & Migrations

```bash
# Create database and tables from models (scans /models directory)
php app/cli.php migrate:create

# Check migration status
php app/cli.php migrate:status

# Run seeders
php app/cli.php seed:run
```

### Creating Models with Migrations

Use the `FieldDatabase` attribute to define your database schema alongside validation rules:

```php
<?php

namespace Models;

use Core\BaseModel;
use Core\Field;
use Core\FieldDatabase;

class User extends BaseModel
{
    #[FieldDatabase(type: 'int', primaryKey: true, autoIncrement: true)]
    protected int $id;

    #[FieldDatabase(type: 'datetime', default: 'CURRENT_TIMESTAMP')]
    protected string $created_at;

    #[Field(required: true, format: 'email')]
    #[FieldDatabase(type: 'varchar', length: 255, unique: true)]
    public string $email;

    #[Field(required: true, min_length: 6)]
    #[FieldDatabase(type: 'varchar', length: 255)]
    public string $password;
}
```

**Supported Field Types:**
- Numeric: `int`, `bigint`, `smallint`, `tinyint`, `decimal`, `float`, `double`
- String: `varchar`, `char`, `text`, `mediumtext`, `longtext`
- Date/Time: `date`, `datetime`, `timestamp`, `time`
- Other: `boolean`, `json`

**Field Attributes:**
- `type` - Column type
- `length` - Column length (for varchar/char)
- `nullable` - Allow NULL values
- `default` - Default value (use `'CURRENT_TIMESTAMP'` for timestamps)
- `primaryKey` - Mark as primary key
- `autoIncrement` - Enable auto-increment
- `unique` - Add unique constraint
- `index` - Create index
- `unsigned` - For numeric types (MySQL)
- `comment` - Column comment

### 🏗️ Application Scaffolding

LilaPHP comes pre-scaffolded with all essential files ready at the project root. No manual initialization step is required.

```php
<?php

namespace Cli\Seeders;

use PDO;

class UserSeeder
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function run(): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO users (email, password, created_at) 
            VALUES (?, ?, NOW())
        ");

        $users = [
            ['user1@example.com', password_hash('password123', PASSWORD_DEFAULT)],
            ['user2@example.com', password_hash('password123', PASSWORD_DEFAULT)],
        ];

        foreach ($users as $user) {
            $stmt->execute($user);
        }
    }
}
```

### Workflow Example

```bash
# 1. Configure database in app/.env
DB_PROVIDER="mysql"
DB_HOST="localhost"
DB_USER="root"
DB_PASSWORD=""
DB_NAME="my_app"
DB_PORT="3306"

# 2. Create your models in app/models/

# 3. Run migrations
php app/cli.php migrate:create

# 4. Create and run seeders
php app/cli.php seed:create UserSeeder
# Edit app/cli/seeders/UserSeeder.php
php app/cli.php seed:run

# 5. Verify
php app/cli.php migrate:status
```

**Database Support:** MySQL/MariaDB, PostgreSQL, SQLite

--- 

## ⚡ Performance & Caching

LilaPHP is designed for speed. When `DEBUG=false` or when using `app:optimize`, the framework:

- ✅ **Config Caching**: Environments are loaded from a compiled PHP array (`env_cache.php`).
- ✅ **Model Metadata**: Table schemas are cached to avoid expensive reflection at runtime.
- ✅ **Asset Minification**: JS/CSS are automatically minified.
- ✅ **Static Dispatch**: Routes and DI maps are cached for instant resolution.

---

## 🏭 Production Deployment

### Environment Configuration

Set `DEBUG=false` in your `.env` file:

```env
DEBUG=false
```

### Restrict Access to `/lila` Directory

**NGINX:**

```nginx
location /lila {
    deny all;
}
``` 
 

---
**Apache (.htaccess):**

```apache
<Directory "lila">
  Order allow,deny
  Deny from all
</Directory>
```


---

### Docker Production Stack (Recommended)

LilaPHP includes an optimized production Docker environment configured with pre-tuned OPcache, APCu cache, and optimal PHP-FPM pool worker configurations. Use the prod scripts to start/stop the production containers manually:

```bash
# Build and run optimized production containers
./app/docker/prod.sh build    # or app\docker\prod.bat build

# Stop production containers to free up system resources
./app/docker/prod.sh stop     # or app\docker\prod.bat stop
```

---

## 📚 Documentation

Full documentation is available at: **[https://seip25.github.io/LilaPHP/](https://seip25.github.io/LilaPHP/)**

---

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

---

## 📄 License

This project is open-source and available under the [MIT License](LICENSE).

---

## 👨‍💻 Author

**Andrés Paiva** - [GitHub](https://github.com/seip25)

---
  
 
