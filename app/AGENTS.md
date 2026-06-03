# LilaPHP Agent Guide (AI Instruction Manual)

This document provides essential technical context for AI agents (and developers) working on the LilaPHP framework. It summarizes architectural rules and modern coding standards.

## 🚀 Core Architecture

### Traditional Routing
- **Entry Point**: In LilaPHP, each page script at the project root (e.g. `index.php`, `login.php`, `cache-response.php`) is an independent entry point.
- **Responsibility**: It loads `app/bootstrap.php`, registers its own route handlers using PHP 8 Attributes, and calls `$app->run()`.
- **Rule**: Put page logic and routes in the root files (or `/api/` subdirectory). Do not place them in a `routes/` subdirectory.

### CLI vs Web Context
- **Environment Safety**: Always check `PHP_SAPI !== 'cli'` before sending HTTP headers or starting sessions.
- **Configuration**: `Core\Config` properties must be initialized with defaults in `Config.php` to prevent crashes during CLI boot.

## 🔧 Modern Coding Standards (The "Lila Way")

### Route Definition (Attributes & DI)
LilaPHP uses PHP 8 Attributes and Dependency Injection. Avoid global variables.

**Pattern:**
```php
require_once __DIR__ . '/app/bootstrap.php';
use Core\{App, GET, POST, Response, Session, Config, SEO};

$app = new App();

#[GET]
#[SEO(key: "page_key")] // Key from app/locales/seo.php
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
Models must reside in `/app/models` and follow the `Models\` namespace.

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
- **Scope**: CLI commands (`migrate`, `optimize`, `admin:add`) **ONLY** scan the `/app/models` directory.
- **Rule**: Never perform a recursive scan of the project root. This prevents accidental execution of page files during database tasks.

### Production Optimization
- **Command**: `php app/cli.php app:optimize`
- **Logic**: Caches env, models, and routes. Minifies JS/CSS. Builds Frontend/Vite assets.
- **Rule**: Run this after any major change to models or environment variables.

### Project Bootstrapping
- **Method**: The project is pre-scaffolded with all root entry points. Spin up the dev containers using `./app/docker/dev.sh build` (or `start` to boot, `stop` to shutdown and free host resources when not coding).
- **Rule**: All CLI commands MUST be executed from the project root (e.g., `LilaPHP/`) as `php app/cli.php <command>` or via `docker exec -it lilaphp_dev_php php app/cli.php <command>`.

## 📁 Key File Locations

- **Pages / Routes**: Root `/*.php` (e.g. `index.php`, `login.php`) and `/api/*.php`.
- **Models**: `/app/models/*.php` (Scanned by CLI/ORM).
- **Tasks**: `/app/tasks/tasks.php` (Scheduler config) and `/app/tasks/*.php` (Task logic).
- **Assets**: `/assets/` (Static files and minified output).
- **Resources**: `/app/resources/` (Twig templates and `main.js` frontend entry point).
- **Locales**: `/app/locales/` (Translation files).

## ⚠️ Common Pitfalls

1. **Headers Sent**: Ensure CLI model discovery is directory-scoped to `/app/models` only.
2. **Path Resolution**: Use `require_once __DIR__ . '/app/bootstrap.php'` in root page files to ensure portability.
3. **Asset Sync**: If UI changes don't show, run `app:optimize` to refresh build manifests.

---
*Last Updated: 2026-06-03*
