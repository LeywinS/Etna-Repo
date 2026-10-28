<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/layout.php';

$user = require_login();
$id   = (int) ($_GET['id'] ?? 0);

$stmt = db()->prepare('SELECT id, title, description, is_done FROM tasks WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $user['id']]);
$task = $stmt->fetch();

if (!$task) {
    http_response_code(404);
    flash('error', 'Tâche introuvable.');
    redirect('/');
}

render_header('Modifier la tâche');
?>
<section class="card narrow">
    <h1>Modifier la tâche</h1>
    <form method="post" action="/task_action.php" class="form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" value="<?= (int) $task['id'] ?>">
        <label>Titre
            <input type="text" name="title" value="<?= e($task['title']) ?>" required maxlength="150">
        </label>
        <label>Description
            <textarea name="description" rows="4" maxlength="2000"><?= e($task['description']) ?></textarea>
        </label>
        <label class="checkbox">
            <input type="checkbox" name="is_done" <?= $task['is_done'] ? 'checked' : '' ?>> Terminée
        </label>
        <div class="row">
            <button class="btn">Enregistrer</button>
            <a href="/" class="btn btn-ghost">Annuler</a>
        </div>
    </form>
</section>
<?php render_footer();
