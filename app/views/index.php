<?php

declare(strict_types=1);

use Core\View;

$appName = View::escape($app_name ?? 'LilaPHP');
$appEnv = View::escape($app_env ?? 'production');
$csrfField = $csrf_input ?? '';

echo View::html(function () use ($appName, $appEnv, $csrfField) {
    return <<<HTML
<div style="text-align: center; padding: 3rem 1rem;">
    <h1 style="font-size: 3rem; font-weight: 800; margin-bottom: 1rem; background: linear-gradient(135deg, #38bdf8 0%, #818cf8 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
        {$appName}
    </h1>
    <p style="font-size: 1.25rem; color: #94a3b8; max-width: 680px; margin: 0 auto 2rem auto; line-height: 1.6;">
        Ultra-fast PHP 8.4 Full-Stack Framework with zero external dependencies, native tiered caching, and Bluebird CSS.
    </p>

    <div style="display: flex; gap: 1rem; justify-content: center; margin-bottom: 3rem; flex-wrap: wrap;">
        <a href="/about" class="btn btn-primary" style="padding: 0.75rem 1.5rem; background: #0284c7; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600;">Learn About LilaPHP</a>
        <a href="/api/health" target="_blank" class="btn btn-secondary" style="padding: 0.75rem 1.5rem; background: #334155; color: #f8fafc; text-decoration: none; border-radius: 6px; font-weight: 600;">System Health JSON</a>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; text-align: left;">
        <div style="background: #1e293b; border: 1px solid #334155; border-radius: 8px; padding: 1.5rem;">
            <h3 style="color: #38bdf8; margin-top: 0; margin-bottom: 0.5rem; font-size: 1.2rem;">Native View Engine</h3>
            <p style="color: #cbd5e1; font-size: 0.95rem; line-height: 1.5; margin: 0;">
                Full layout support, reusable partials, heredoc components, and tiered caching across APCu, Redis, and disk storage.
            </p>
        </div>

        <div style="background: #1e293b; border: 1px solid #334155; border-radius: 8px; padding: 1.5rem;">
            <h3 style="color: #38bdf8; margin-top: 0; margin-bottom: 0.5rem; font-size: 1.2rem;">Unified REST & Web Routing</h3>
            <p style="color: #cbd5e1; font-size: 0.95rem; line-height: 1.5; margin: 0;">
                Automatic 405 Method Not Allowed handling, parameter resolution, and instant REST APIs in <code>app/routes/api/</code>.
            </p>
        </div>

        <div style="background: #1e293b; border: 1px solid #334155; border-radius: 8px; padding: 1.5rem;">
            <h3 style="color: #38bdf8; margin-top: 0; margin-bottom: 0.5rem; font-size: 1.2rem;">Multi-Driver Database</h3>
            <p style="color: #cbd5e1; font-size: 0.95rem; line-height: 1.5; margin: 0;">
                Seamless switching between MySQL and standalone WAL SQLite for lightweight microservices, desktop, and mobile bundles.
            </p>
        </div>
    </div>

    <form method="POST" action="/api/users" style="display: none;">
        {$csrfField}
    </form>
</div>
HTML;
});