<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/layout.php';

if (current_user()) {
    redirect('/');
}

$errors = [];
$username = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $username = trim((string) ($_POST['username'] ?? ''));
    $email    = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirm  = (string) ($_POST['password_confirm'] ?? '');

    // --- Validation ---
    if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
        $errors[] = "Le pseudo doit faire 3 à 50 caractères (lettres, chiffres, _).";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Adresse e-mail invalide.";
    }
    if (strlen($password) < 8) {
        $errors[] = "Le mot de passe doit faire au moins 8 caractères.";
    }
    if ($password !== $confirm) {
        $errors[] = "Les mots de passe ne correspondent pas.";
    }

    // --- Unicité ---
    if (!$errors) {
        $stmt = db()->prepare('SELECT 1 FROM users WHERE username = ? OR email = ?');
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $errors[] = "Ce pseudo ou cet e-mail est déjà utilisé.";
        }
    }

    // --- Création ---
    if (!$errors) {
        $stmt = db()->prepare('INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)');
        $stmt->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT)]);

        login_user((int) db()->lastInsertId());
        flash('success', 'Compte créé, bienvenue !');
        redirect('/');
    }
}

render_header('Inscription');
?>
<section class="card narrow">
    <h1>Créer un compte</h1>

    <?php if ($errors): ?>
        <div class="alert alert-error"><ul>
            <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
        </ul></div>
    <?php endif; ?>

    <form method="post" class="form">
        <?= csrf_field() ?>
        <label>Pseudo
            <input type="text" name="username" value="<?= e($username) ?>" required maxlength="50">
        </label>
        <label>E-mail
            <input type="email" name="email" value="<?= e($email) ?>" required maxlength="255">
        </label>
        <label>Mot de passe
            <input type="password" name="password" required minlength="8">
        </label>
        <label>Confirmer le mot de passe
            <input type="password" name="password_confirm" required minlength="8">
        </label>
        <button class="btn btn-block">S'inscrire</button>
    </form>
    <p class="muted center">Déjà inscrit ? <a href="/login.php">Se connecter</a></p>
</section>
<?php render_footer();
