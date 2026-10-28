<?php
declare(strict_types=1);

/** En-tête HTML commun */
function render_header(string $title): void
{
    $user = current_user();
    ?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · MesTâches</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<header class="topbar">
    <a href="/" class="brand">✓ MesTâches</a>
    <nav>
        <?php if ($user): ?>
            <span class="muted">Bonjour, <strong><?= e($user['username']) ?></strong></span>
            <form method="post" action="/logout.php" class="inline">
                <?= csrf_field() ?>
                <button class="btn btn-ghost">Déconnexion</button>
            </form>
        <?php else: ?>
            <a href="/login.php">Connexion</a>
            <a href="/register.php" class="btn">Inscription</a>
        <?php endif; ?>
    </nav>
</header>
<main class="container">
    <?php foreach (get_flashes() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
    <?php endforeach; ?>
<?php
}

/** Pied de page HTML commun */
function render_footer(): void
{
    ?>
</main>
<footer class="footer muted">MesTâches — PHP &amp; MySQL</footer>
</body>
</html>
<?php
}
