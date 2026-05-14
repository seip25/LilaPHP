# LilaPHP Agent Guide (AI Instruction Manual)

This document provides essential technical context for AI agents (and developers) working on the LilaPHP framework. It summarizes architectural rules and modern coding standards.

## 🚀 Core Architecture

### Centralized Dispatcher
- **Entry Point**: `Core\Dispatcher::dispatch()` is the heart of the framework.
- **Responsibility**: It initializes `Debug`, applies `Security` headers, and triggers the router.
- **Rule**: Do not add logic to `index.php` files. Routes should be defined in `routes/*.php`.

### CLI vs Web Context
- **Environment Safety**: Always check `PHP_SAPI !== 'cli'` before sending HTTP headers or starting sessions.
- **Configuration**: `Core\Config` properties must be initialized with defaults in `Config.php` to prevent crashes during CLI boot.

## 🔧 Modern Coding Standards (The "Lila Way")

### Route Definition (Attributes & DI)
LilaPHP uses PHP 8 Attributes and Dependency Injection. Avoid global variables.

**Pattern:**
```php
include_once __DIR__ . "/../index.php";
use Core\{App, GET, POST, Response, Session, Config, SEO};

$app = new App();

#[GET]
#[SEO(key: "page_key")] // Key from locales/seo.php
function myRoute(Response $res, Session $session, Config $config) {
    return $res->render("template_name", [
        "debug" => $config::$DEBUG
    ]);
}
$app->add('myRoute');
$app->run();
```

**Injectable Objects:**
- `array $req`: Incoming request data.
- `Core\Response $res`: For rendering HTML/JSON.
- `Core\Session $session`: Session management.
- `Core\Config $config`: Environment variables.
- `Core\Translate $translate`: Localization engine.
- `Core\Database $db`: PDO connection.

### Model Definition
Models must reside in `/models` and follow the `Models\` namespace.

```php
namespace Models;
use Core\{BaseModel, Field, FieldDatabase};

class User extends BaseModel {
    #[FieldDatabase(type: 'int', primaryKey: true, autoIncrement: true)]
    public int $id;

    #[Field(required: true, format: 'email')]
    #[FieldDatabase(type: 'varchar', length: 255, unique: true)]
    public string $email;
}
```

## 🔧 CLI & Automated Tools

### Model Discovery (CRITICAL)
- **Scope**: CLI commands (`migrate`, `optimize`, `admin:add`) **ONLY** scan the `/models` directory.
- **Rule**: Never perform a recursive scan of the project root. This prevents accidental execution of route files during database tasks.

### Production Optimization
- **Command**: `php cli.php app:optimize`
- **Logic**: Caches env, models, and routes. Minifies JS/CSS. Builds React/Vite assets.
- **Rule**: Run this after any major change to models or environment variables.

### Project Bootstrapping
- **Command**: `php cli.php app:init`
- **Logic**: Copies initial structure (routes, models, .env) from `lila/scaffold/` to the root.

## 📁 Key File Locations

- **Routes**: `/routes/*.php` (Scanned by router, entry point for web).
- **Models**: `/models/*.php` (Scanned by CLI/ORM).
- **Tasks**: `tasks/tasks.php` (Scheduler config) and `tasks/*.php` (Task logic).
- **Assets**: `/assets/` (Static files and minified output).
- **Resources**: `/resources/templates/` (Twig) and `/resources/js/pages/` (React).

## ⚠️ Common Pitfalls

1. **Headers Sent**: Ensure CLI model discovery is directory-scoped to `/models` only.
2. **Path Resolution**: Use `__DIR__ . "/../index.php"` in routes to ensure portability.
3. **Asset Sync**: If UI changes don't show, run `app:optimize` to refresh build manifests.

---
*Last Updated: 2026-05-14*
