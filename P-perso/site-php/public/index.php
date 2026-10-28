<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/layout.php';

$user = require_login();

// Filtre : all | todo | done (liste blanche)
$filter = $_GET['filter'] ?? 'all';
if (!in_array($filter, ['all', 'todo', 'done'], true)) {
    $filter = 'all';
}

$sql = 'SELECT id, title, description, is_done, created_at FROM tasks WHERE user_id = ?';
if ($filter === 'todo') $sql .= ' AND is_done = 0';
if ($filter === 'done') $sql .= ' AND is_done = 1';
$sql .= ' ORDER BY is_done ASC, created_at DESC';

$stmt = db()->prepare($sql);
$stmt->execute([$user['id']]);
$tasks = $stmt->fetchAll();

// Statistiques
$stmt = db()->prepare('SELECT COUNT(*) AS total, COALESCE(SUM(is_done), 0) AS done FROM tasks WHERE user_id = ?');
$stmt->execute([$user['id']]);
$stats = $stmt->fetch();
$total = (int) $stats['total'];
$done  = (int) $stats['done'];
$percent = $total ? (int) round($done / $total * 100) : 0;

render_header('Mes tâches');
?>
<section class="card">
    <h1>Nouvelle tâche</h1>
    <form method="post" action="/task_action.php" class="form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">
        <label>Titre
            <input type="text" name="title" required maxlength="150" placeholder="Ex : Préparer la réunion">
        </label>
        <label>Description <span class="muted">(optionnel)</span>
            <textarea name="description" rows="2" maxlength="2000"></textarea>
        </label>
        <button class="btn">Ajouter</button>
    </form>
</section>

<section class="card">
    <div class="list-head">
        <h2>Mes tâches</h2>
        <div class="filters">
            <?php foreach (['all' => 'Toutes', 'todo' => 'À faire', 'done' => 'Terminées'] as $key => $label): ?>
                <a href="?filter=<?= $key ?>" class="chip <?= $filter === $key ? 'active' : '' ?>"><?= $label ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="progress" title="<?= $percent ?> % terminé">
        <div class="progress-bar" style="width: <?= $percent ?>%"></div>
    </div>
    <p class="muted"><?= $done ?> / <?= $total ?> terminée(s) — <?= $percent ?> %</p>

    <?php if (!$tasks): ?>
        <p class="empty">Aucune tâche ici pour l'instant.</p>
    <?php else: ?>
        <ul class="tasks">
            <?php foreach ($tasks as $t): ?>
                <li class="task <?= $t['is_done'] ? 'is-done' : '' ?>">
                    <form method="post" action="/task_action.php" class="inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                        <button class="check" title="Changer le statut"><?= $t['is_done'] ? '✓' : '' ?></button>
                    </form>

                    <div class="task-body">
                        <strong><?= e($t['title']) ?></strong>
                        <?php if ($t['description']): ?>
                            <p><?= nl2br(e($t['description'])) ?></p>
                        <?php endif; ?>
                        <small class="muted">Créée le <?= e(date('d/m/Y à H:i', strtotime($t['created_at']))) ?></small>
                    </div>

                    <div class="task-actions">
                        <a href="/task_edit.php?id=<?= (int) $t['id'] ?>" class="btn btn-ghost btn-sm">Modifier</a>
                        <form method="post" action="/task_action.php" class="inline"
                              onsubmit="return confirm('Supprimer cette tâche ?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                            <button class="btn btn-danger btn-sm">Supprimer</button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
<?php render_footer();
