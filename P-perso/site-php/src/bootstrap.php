<?php
declare(strict_types=1);

/**
 * Chargé en tête de chaque page : session sécurisée + fonctions utilitaires.
 */

require_once __DIR__ . '/db.php';

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,      // cookie inaccessible au JavaScript
    'samesite' => 'Lax',     // limite le CSRF cross-site
    'secure'   => !empty($_SERVER['HTTPS']),
]);
session_start();

/* ---------- Échappement HTML (anti-XSS) ---------- */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/* ---------- Redirection ---------- */
function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

/* ---------- Messages flash (affichés une seule fois) ---------- */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/* ---------- Protection CSRF ---------- */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function check_csrf(): void
{
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(400);
        exit('Jeton CSRF invalide.');
    }
}

/* ---------- Authentification ---------- */
function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    static $user = null;
    if ($user === null) {
        $stmt = db()->prepare('SELECT id, username, email FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch() ?: null;
    }
    return $user;
}

function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        flash('error', 'Connecte-toi pour accéder à cette page.');
        redirect('/login.php');
    }
    return $user;
}

function login_user(int $userId): void
{
    session_regenerate_id(true); // anti fixation de session
    $_SESSION['user_id'] = $userId;
}
