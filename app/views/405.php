<?php

declare(strict_types=1);

use Core\View;

$method = View::escape($method ?? ($_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN'));
$allowed = isset($allowed) && is_array($allowed) ? View::escape(implode(', ', $allowed)) : 'GET';
?>
<div style="text-align: center; padding: 4rem 1rem;">
    <h1 style="font-size: 4rem; font-weight: 800; color: #ef4444; margin-bottom: 1rem;">405</h1>
    <h2 style="font-size: 1.5rem; margin-bottom: 1rem; color: #f8fafc;">Method Not Allowed</h2>
    <p style="color: #94a3b8; max-width: 500px; margin: 0 auto 1.5rem auto;">
        HTTP method <code><?= $method ?></code> is not permitted for this route.
    </p>
    <p style="color: #64748b; font-size: 0.95rem; margin-bottom: 2rem;">
        Allowed HTTP methods: <code><?= $allowed ?></code>
    </p>
    <a href="/" style="display: inline-block; padding: 0.75rem 1.5rem; background: #334155; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600;">Return to Home</a>
</div>
