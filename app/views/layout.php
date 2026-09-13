<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= \Core\View::escape($title ?? 'LilaPHP') ?></title>
    <?php if (!empty($description)): ?>
    <meta name="description" content="<?= \Core\View::escape($description) ?>">
    <?php endif; ?>
    <?php if (!empty($keywords)): ?>
    <meta name="keywords" content="<?= \Core\View::escape($keywords) ?>">
    <?php endif; ?>
    <?php if (!empty($canonical)): ?>
    <link rel="canonical" href="<?= \Core\View::escape($canonical) ?>">
    <?php endif; ?>
    <?php if (!empty($manifest)): ?>
    <link rel="manifest" href="<?= \Core\View::escape($manifest) ?>">
    <?php endif; ?>
    <?= $csrf_meta ?? '' ?>
    <link rel="icon" href="<?= \Core\View::asset('/favicon.ico') ?>" type="image/x-icon">
    <link rel="stylesheet" href="<?= \Core\View::asset('/css/bluebird.css') ?>">
    <?php if (!empty($jsonLD)): ?>
    <script type="application/ld+json"><?= is_array($jsonLD) ? json_encode($jsonLD, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : $jsonLD ?></script>
    <?php endif; ?>
</head>
<body>
    <header class="navbar" style="border-bottom: 1px solid var(--border-color, #334155); padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center;">
        <div style="display: flex; align-items: center; gap: 1rem;">
            <a href="/" style="font-weight: 700; font-size: 1.25rem; text-decoration: none; color: var(--primary, #38bdf8); display: flex; align-items: center; gap: 0.5rem;">
                <img src="<?= \Core\View::asset('/images/lila.png') ?>" alt="LilaPHP Logo" style="height: 28px; width: auto;" onerror="this.style.display='none'">
                <span>LilaPHP</span>
            </a>
        </div>
        <nav style="display: flex; gap: 1.5rem; align-items: center;">
            <a href="/" style="text-decoration: none; color: inherit;">Home</a>
            <a href="/about" style="text-decoration: none; color: inherit;">About</a>
            <a href="/api" target="_blank" style="text-decoration: none; color: inherit;">REST API</a>
            <a href="/docs/index.html" target="_blank" style="text-decoration: none; color: inherit;">Docs</a>
        </nav>
    </header>

    <main class="container" style="max-width: 1200px; margin: 0 auto; padding: 2rem 1rem; min-height: calc(100vh - 160px);">
        <?= $content ?? '' ?>
    </main>

    <footer style="border-top: 1px solid var(--border-color, #334155); padding: 1.5rem 2rem; text-align: center; color: #94a3b8; font-size: 0.875rem;">
        <p>LilaPHP &copy; <?= date('Y') ?> &middot; High-Performance Zero-Dependency PHP 8.4 Framework &middot; Styled with Bluebird CSS</p>
    </footer>

    <script src="<?= \Core\View::asset('/js/bluebird.js') ?>"></script>
</body>
</html>
