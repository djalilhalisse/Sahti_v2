<?php require __DIR__ . '/lib.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $_SESSION = []; session_destroy(); session_start(); flash('Vous êtes déconnecté.');
}
redirect(user() ? 'dashboard.php' : 'index.php');
