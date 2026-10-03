<?php require __DIR__ . '/lib.php';
if (user()) redirect('dashboard.php');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['ltries'] = array_values(array_filter($_SESSION['ltries'] ?? [], fn($t) => $t > time() - 600));
    if (count($_SESSION['ltries']) >= 5) {
        flash('Trop de tentatives. Réessayez dans 10 minutes.', 'err');
    } else {
        $st = db()->prepare('SELECT id,doctor_id,name,role,password_hash FROM users WHERE email=? AND active=1');
        $st->execute([trim($_POST['email'] ?? '')]); $row = $st->fetch();
        if (csrf_ok() && $row && password_verify((string)($_POST['password'] ?? ''), $row['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user'] = ['id' => (int)$row['id'], 'doctor_id' => $row['doctor_id'] ? (int)$row['doctor_id'] : null, 'name' => $row['name'], 'role' => $row['role']];
            unset($_SESSION['ltries']);
            redirect('dashboard.php');
        }
        $_SESSION['ltries'][] = time();
        flash('Email ou mot de passe incorrect.', 'err');
    }
    redirect('login.php');
}
head('Connexion', 'login.php'); ?>
<div class="page"><div class="narrow"><h1 style="font-size:clamp(2rem,5vw,3rem);margin-bottom:22px"><?= e(t('Espace médecin')) ?></h1>
<form method="post" class="panel f"><?= csrf_field() ?>
  <label><?= e(t('Email')) ?><input name="email" type="email" autocomplete="username" required autofocus></label>
  <label><?= e(t('Mot de passe')) ?><input name="password" type="password" autocomplete="current-password" required></label>
  <button class="btn"><?= e(t('Se connecter')) ?></button></form></div></div>
<?php foot();
