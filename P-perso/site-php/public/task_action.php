<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';

/**
 * Point d'entrée unique des actions sur les tâches (POST uniquement).
 * Chaque requête filtre sur user_id : impossible d'agir sur la tâche d'un autre (anti-IDOR).
 */

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Méthode non autorisée.');
}
check_csrf();

$action = $_POST['action'] ?? '';
$id     = (int) ($_POST['id'] ?? 0);

switch ($action) {
    case 'create':
    case 'update':
        $title       = trim((string) ($_POST['title'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));

        if ($title === '' || mb_strlen($title) > 150) {
            flash('error', 'Le titre est obligatoire (150 caractères max).');
            redirect($action === 'update' ? "/task_edit.php?id=$id" : '/');
        }
        $description = $description === '' ? null : mb_substr($description, 0, 2000);

        if ($action === 'create') {
            db()->prepare('INSERT INTO tasks (user_id, title, description) VALUES (?, ?, ?)')
                ->execute([$user['id'], $title, $description]);
            flash('success', 'Tâche ajoutée.');
        } else {
            $isDone = isset($_POST['is_done']) ? 1 : 0;
            $stmt = db()->prepare('UPDATE tasks SET title = ?, description = ?, is_done = ? WHERE id = ? AND user_id = ?');
            $stmt->execute([$title, $description, $isDone, $id, $user['id']]);
            flash('success', 'Tâche mise à jour.');
        }
        break;

    case 'toggle':
        db()->prepare('UPDATE tasks SET is_done = 1 - is_done WHERE id = ? AND user_id = ?')
            ->execute([$id, $user['id']]);
        break;

    case 'delete':
        $stmt = db()->prepare('DELETE FROM tasks WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $user['id']]);
        flash($stmt->rowCount() ? 'success' : 'error', $stmt->rowCount() ? 'Tâche supprimée.' : 'Tâche introuvable.');
        break;

    default:
        http_response_code(400);
        exit('Action inconnue.');
}

redirect('/');
