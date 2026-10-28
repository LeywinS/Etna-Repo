<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/layout.php';

if (current_user()) {
    redirect('/');
}

$error = null;
$login = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $login    = trim((string) ($_POST['login'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $stmt = db()->prepare('SELECT id, password_hash FROM users WHERE username = ? OR email = ?');
    $stmt->execute([$login, $login]);
    $user = $stmt->fetch();

    // Message volontairement générique : on ne révèle pas si le compte existe
    if ($user && password_verify($password, $user['password_hash'])) {
        // Re-hash si l'algorithme par défaut de PHP a évolué
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        }
        login_user((int) $user['id']);
        flash('success', 'Connexion réussie.');
        redirect('/');
    }
    $error = 'Identifiants incorrects.';
}

render_header('Connexion');
?>
<section class="card narrow">
    <h1>Connexion</h1>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" class="form">
        <?= csrf_field() ?>
        <label>Pseudo ou e-mail
            <input type="text" name="login" value="<?= e($login) ?>" required autofocus>
        </label>
        <label>Mot de passe
            <input type="password" name="password" required>
        </label>
        <button class="btn btn-block">Se connecter</button>
    </form>
    <p class="muted center">Pas encore de compte ? <a href="/register.php">S'inscrire</a></p>
</section>
<?php render_footer();
