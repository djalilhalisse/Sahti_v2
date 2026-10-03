<?php require __DIR__ . '/lib.php';
$u = require_login();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $st = db()->prepare('SELECT password_hash FROM users WHERE id=?'); $st->execute([$u['id']]);
    $cur = (string)($_POST['current'] ?? ''); $new = (string)($_POST['new'] ?? '');
    if (!csrf_ok()) flash('Session expirée. Réessayez.', 'err');
    elseif (!password_verify($cur, (string)$st->fetchColumn())) flash('Le mot de passe actuel est incorrect.', 'err');
    elseif (mb_strlen($new) < 8) flash('Le nouveau mot de passe doit contenir au moins 8 caractères.', 'err');
    elseif ($new !== ($_POST['confirm'] ?? '')) flash('La confirmation ne correspond pas.', 'err');
    else {
        db()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($new, PASSWORD_DEFAULT), $u['id']]);
        session_regenerate_id(true); flash('Mot de passe modifié.');
    }
    redirect('account.php');
}
head('Mon compte', 'dashboard.php'); ?>
<div class="page"><div class="narrow"><h1 style="font-size:clamp(2rem,5vw,3rem);margin-bottom:22px"><?= e(t('Mon compte')) ?></h1>
<form method="post" class="panel f"><?= csrf_field() ?>
  <label><?= e(t('Mot de passe actuel')) ?><input name="current" type="password" autocomplete="current-password" required></label>
  <label><?= e(t('Nouveau mot de passe (8 caractères minimum)')) ?><input name="new" type="password" autocomplete="new-password" minlength="8" required></label>
  <label><?= e(t('Confirmer le nouveau mot de passe')) ?><input name="confirm" type="password" autocomplete="new-password" required></label>
  <button class="btn"><?= e(t('Changer le mot de passe')) ?></button></form>
<p style="margin-top:16px"><a href="dashboard.php"><?= e(t('Retour au tableau de bord')) ?></a></p></div></div>
<?php foot();
