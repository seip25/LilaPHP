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

### 🔥 Modular Architecture

Each entry file can instantiate the framework with its own configuration, giving you complete control over security, middleware, and session behavior.

```php
// app/index.php - Default configuration
$app = new App();
```

```php
// app/newApp.php - Custom configuration
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
LANG="eng"
```

### Run the Application or visit http://localhost/LilaPHP in LAMPP ,XAMPP,WAMP

**Using PHP's Built-in Server (Development):**

```bash
php -S localhost:8000
```

Then visit [http://localhost:8000](http://localhost:8000)


### React 

## Install

```bash
cd app
npm install
npm run dev 
npm run build
```

**Edit in app/resources/pages  :**

## 📁 Project Structure

```
LilaPHP/
├── app/
│   ├── templates/     # Twig templates
│   ├── locales/       # Language files (eng, esp, bra, por)
│   ├── vendor/        # Composer dependencies
│   ├── .env           # Environment configuration
│   ├── index.php      # Framework bootstrap
│   └── lila/          # Framework core and CLI
│       ├── core/      # Core framework files (router, response, validator)
│       └── cli/       # CLI commands
│
├── public/            # Public assets (CSS, JS, images)
├── index.php          # Main entry point
├── login/             # Independent app example
│   └── index.php
├── set-lang/          # Language switcher
│   └── index.php
└── .htaccess          # Apache configuration
```

---

## 🚀 Quick Start

### Basic Routing

```php
<?php
include_once __DIR__."/app/index.php";

$app->get(callback: function($req, $res) use ($app) {
    return $app->render("home");
});

$app->post(callback: function($req, $res) use ($app) {
    return $app->jsonResponse(["success" => true]);
}, csrf: true);

$app->run();
```

### React Render

```php
<?php
include_once __DIR__."/app/index.php";

$app->get(callback: function($req, $res) use ($app) {
        
        //Example render Twig and React Island app/templates/react_integration.twig
        return $app->render(template:"react_integration", 
        context:
        [
            "app" => [
                "debug" => $app->getEnv("DEBUG")
            ]
        ]);

        //Example render full page React app/resources/ReactExample.jsx
        return $app->renderReact(
            page: "ReactExample",
            props: [
                "app" => [
                    "debug" => $app->getEnv("DEBUG")
                ],
                "csrf"=>Security::generateCsrfToken() ,
                "translations"=>Translate::translations()
            ],
            options: [
                "lang" => "es",
                "title" => "React full Page + LilaPHP",
                "meta" => [
                    ["name" => "description", "content" => "React full page render example meta description"]
                ],

                "scripts" => [
                    "https://cdn.tailwindcss.com"
                ]
            ]
        );
  
});
 

$app->run();
```
## In React receives props 

```javascript
export default function ReactIsland({ csrf, translations }) { 
    ...
}

```

### 🔄 Re-rendering React Islands

You can trigger a re-render of your React islands from standard JavaScript (e.g., from Twig templates, jQuery, or other non-React code) using the global function:

```javascript
// Re-render all React components on the page
window.renderReactComponent();

// Re-render only components named 'CartBadge'
window.renderReactComponent('CartBadge', 'component');

// Re-render a specific full page component
window.renderReactComponent('ReactExample', 'page');
```

This is incredibly useful for updating separate component roots when reading shared client endpoints (such as `localStorage`) from external areas.

### Validation with PHP 8 Attributes

```php
use Core\BaseModel;
use Core\Field;

class LoginModel extends BaseModel
{
    #[Field(required: true, format: "email")]
    public string $email;

    #[Field(required: true, min_length: 6)]
    public string $password;
}

$app->post(
    callback: fn($req, $res) => $app->jsonResponse(["success" => true]),
    middlewares: [
        fn($req, $res) => new LoginModel(data: $req)
    ],
    csrf: true
);
```

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
app/locales/
├── esp.php
├── eng.php
├── bra.php
└── por.php
```

**Example translation file:**

```php
<?php
// app/locales/eng.php
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
- **`url(path)`** — Returns the full absolute project URL
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

## 🔧 CLI Commands and Migrations

LilaPHP includes a powerful CLI system for database migrations and seeders.

### Quick Start

```bash
# Create database and run migrations
cd app
php cli.php migrate:create

# Check migration status
php cli.php migrate:status

# Run seeders
php cli.php seed:run
```

### Available Commands

**Migration Commands:**

```bash
php cli.php migrate:create    # Create database and run all migrations
php cli.php migrate:run        # Run pending migrations
php cli.php migrate:status     # Show migration status
php cli.php migrate:fresh      # Drop all tables and re-migrate (WARNING: destructive)
php cli.php migrate:rollback   # Rollback last migration
```

**Seeder Commands:**

```bash
php cli.php seed:run           # Run all seeders
php cli.php seed:create UserSeeder  # Create a new seeder
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

### Creating Seeders

Generate a seeder template:

```bash
php cli.php seed:create UserSeeder
```

This creates `app/lila/cli/seeders/UserSeeder.php`:

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
# 1. Configure database in .env
DB_PROVIDER="mysql"
DB_HOST="localhost"
DB_USER="root"
DB_PASSWORD=""
DB_NAME="my_app"
DB_PORT="3306"

# 2. Create your models in app/models/

# 3. Run migrations
cd app
php cli.php migrate:create

# 4. Create and run seeders
php cli.php seed:create UserSeeder
# Edit app/lila/cli/seeders/UserSeeder.php
php cli.php seed:run

# 5. Verify
php cli.php migrate:status
```

**Database Support:** MySQL/MariaDB, PostgreSQL, SQLite

---

## ⚡ Performance

When `DEBUG=false`, LilaPHP automatically:

- ✅ Minifies HTML, CSS, and JavaScript
- ✅ Generates optimized WebP images
- ✅ Caches Twig templates
- ✅ Loads only required components per route

---

## 🏭 Production Deployment

### Environment Configuration

Set `DEBUG=false` in your `.env` file:

```env
DEBUG=false
```

### Restrict Access to `/app` Directory

**NGINX:**

```nginx
location /app {
    deny all;
}
``` 
 

---
**Apache (.htaccess):**

```apache
<Directory "app">
  Order allow,deny
  Deny from all
</Directory>
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
  
 
