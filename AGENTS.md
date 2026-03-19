# LilaPHP - AI Assistant Guidelines

This file provides context and rules for AI assistants (like Claude, Cursor, or ChatGPT) working on the **LilaPHP** codebase. Refer to this to avoid making assumptions based on heavier frameworks like Laravel or Symfony.

---

## 🏗️ Architecture & Core concepts

- **Micro-App Structure**: LilaPHP does **NOT** use a single global router file to dispatch to controllers. Instead, it supports instantiating `Core\App` per endpoints/sub-directories (e.g., `/app/index.php`, `/login/index.php`), although you can map wirecards.
- **Middleware Flow**: Continuous script execution is critical. DO NOT append `exit;` or `die()` inside helper responses (like `jsonResponse`) or exception constructors. Allow the script to complete the loop to execute any registered `after` callbacks or clean-ups.
- **Validations**: Backed by PHP 8 Attributes inside `BaseModel`. Instantiating a model (e.g., `new UserModel($data)`) strictly triggers automatic validation inside the constructor and throws `ValidationException` on failure over continuous loops.

---

## 🎨 Frontend & Hybrid Components

- **React Islands Architecture**: 
  - Mix with Twig templates using `{{ react('ComponentName', props) }}`.
  - Mounts to containers possessing `data-react-component="ComponentName"`.
  - **Re-rendering Trigger:** Trigger on-page React component unmount/remount updates from standard JS or template macros with:
    ```javascript
    window.renderReactComponent(); // All islands
    window.renderReactComponent('Header'); // Specific component layout
    ```
- **Image Optimization**: Use the Twig helper `{{ image('path', width, height) }}` to load optimized cache replicas on demand securely.

---

## 🛡️ Security Standards

- **Cross Content Defenses:** Possesses `Security` context including CSP (Content Security Policy) loaded prior to middlewares running. Verify websocket/origin configurations there when Vite setup triggers content blocking nodes.
- **Validation Fallbacks:** Translations have been strictly separated to `app/locales/validation_[lang].php` to reduce core object boilerplate.

---

## 📂 File Layout Rules

- **Exceptions Range:** Custom high-level exceptions reside individually in `app/core/`, such as `ValidationException.php` to comply with automated PSR loaders.
- **Database Contexts:** Use `$app->getDatabaseConnection()` which initializes highly recoverable `PDO` nodes inside middlewares easily.
