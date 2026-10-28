<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';

// Déconnexion uniquement en POST + CSRF (un simple lien GET pourrait être forcé)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/');
}
check_csrf();

$_SESSION = [];
$params = session_get_cookie_params();
setcookie(session_name(), '', time() - 3600, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
session_destroy();

session_start();
flash('success', 'Tu es déconnecté.');
redirect('/login.php');
