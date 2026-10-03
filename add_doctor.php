<?php require __DIR__ . '/lib.php';
require_login(true);   // administrators only

$specs = db()->query('SELECT DISTINCT speciality FROM doctors ORDER BY 1')->fetchAll(PDO::FETCH_COLUMN);
$v = ['name' => '', 'speciality' => '', 'fees' => '', 'cname' => '', 'address' => '', 'phone' => '', 'open' => '08:00', 'close' => '16:00', 'acc' => '1', 'email' => ''];
$err = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($v as $k => $unused) $v[$k] = trim((string)($_POST[$k] ?? ''));   // unchecked checkbox => ''
    $pass = (string)($_POST['password'] ?? ''); $pass2 = (string)($_POST['confirm'] ?? '');
    $phone = clean_phone($v['phone']); $mkAcc = $v['acc'] !== '';
    $full = preg_match('/^(dr|pr)\.?\s/i', $v['name']) ? $v['name'] : 'Dr. ' . $v['name'];

    if (!csrf_ok()) $err[] = 'Votre session a expiré. Rechargez la page et réessayez.';
    if (mb_strlen($v['name']) < 3 || mb_strlen($v['name']) > 75) $err[] = 'Indiquez le nom complet du médecin (3 à 75 caractères).';
    if (mb_strlen($v['speciality']) < 2 || mb_strlen($v['speciality']) > 60) $err[] = 'Indiquez la spécialité (2 à 60 caractères).';
    $fees = filter_var($v['fees'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100000]]);
    if ($fees === false) $err[] = 'Le tarif doit être un nombre entre 1 et 100000 DA.';
    if (mb_strlen($v['cname']) < 3 || mb_strlen($v['cname']) > 200) $err[] = 'Indiquez le nom du cabinet (3 à 200 caractères).';
    if (mb_strlen($v['address']) < 5 || mb_strlen($v['address']) > 200) $err[] = "Indiquez l'adresse du cabinet (5 à 200 caractères).";
    if (!preg_match('/^\+?\d{9,15}$/', $phone)) $err[] = 'Le numéro de téléphone doit contenir 9 à 15 chiffres.';
    $tm = '/^([01]\d|2[0-3]):[0-5]\d$/';
    if (!preg_match($tm, $v['open']) || !preg_match($tm, $v['close']) || strtotime($v['close']) - strtotime($v['open']) < 1800)
        $err[] = "Horaires invalides : la fermeture doit avoir lieu au moins 30 minutes après l'ouverture.";

    if ($mkAcc) {
        if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($v['email']) > 100) $err[] = "L'adresse email n'est pas valide.";
        else {
            $st = db()->prepare('SELECT COUNT(*) FROM users WHERE email=?'); $st->execute([$v['email']]);
            if ((int)$st->fetchColumn() > 0) $err[] = 'Cet email est déjà utilisé par un autre compte.';
        }
        if (mb_strlen($pass) < 8) $err[] = 'Le mot de passe doit contenir au moins 8 caractères.';
        elseif ($pass !== $pass2) $err[] = 'La confirmation ne correspond pas.';
    }

    // Optional photo: JPG, PNG or WebP, 3 MB max
    $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $f = $_FILES['photo'] ?? null; $ext = null;
    if ($f && $f['error'] !== UPLOAD_ERR_NO_FILE) {
        $info = $f['error'] === UPLOAD_ERR_OK && is_uploaded_file($f['tmp_name']) ? @getimagesize($f['tmp_name']) : false;
        if (in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $f['size'] > 3145728) $err[] = 'La photo dépasse 3 Mo.';
        elseif (!$info || !isset($types[$info['mime']]) || $info[0] > 6000 || $info[1] > 6000) $err[] = 'La photo doit être une image JPG, PNG ou WebP.';
        else $ext = $types[$info['mime']];
    }

    if (!$err) {
        $dir = __DIR__ . '/assets/img/doctors/'; $saved = null; $image = 'placeholder.svg';
        try {
            if ($ext) {
                $saved = 'd' . bin2hex(random_bytes(6)) . '.' . $ext;
                if (!is_dir($dir) || !move_uploaded_file($f['tmp_name'], $dir . $saved)) { $saved = null; throw new RuntimeException('upload'); }
                $image = $saved;
            }
            $pdo = db(); $pdo->beginTransaction();
            $pdo->prepare('INSERT INTO doctors (name,image,speciality,fees_da) VALUES (?,?,?,?)')->execute([$full, $image, $v['speciality'], $fees]);
            $id = (int)$pdo->lastInsertId();
            $pdo->prepare('INSERT INTO clinics (doctor_id,name,address,phone,open_time,close_time) VALUES (?,?,?,?,?,?)')
                ->execute([$id, $v['cname'], $v['address'], $phone, $v['open'] . ':00', $v['close'] . ':00']);
            if ($mkAcc) $pdo->prepare('INSERT INTO users (doctor_id,name,email,password_hash,role) VALUES (?,?,?,?,?)')
                ->execute([$id, $full, $v['email'], password_hash($pass, PASSWORD_DEFAULT), 'doctor']);
            $pdo->commit();
            flash('Médecin ajouté. Il apparaît maintenant dans la liste des médecins.');
            redirect('dashboard.php');
        } catch (RuntimeException $ex) {
            $err[] = "Impossible d'enregistrer la photo (le dossier assets/img/doctors doit être accessible en écriture).";
        } catch (Throwable $ex) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            if ($saved) @unlink($dir . $saved);
            if ($ex instanceof PDOException && $ex->getCode() === '23000') $err[] = 'Cet email est déjà utilisé par un autre compte.';
            else throw $ex;
        }
    }
}
head('Ajouter un médecin', 'dashboard.php'); ?>
<div class="page"><div class="dh"><h1 style="font-size:clamp(2rem,5vw,3.2rem)"><?= e(t('Ajouter un médecin')) ?></h1>
  <a class="btn ghost sm" href="dashboard.php"><?= e(t('Retour au tableau de bord')) ?></a></div>
<?php foreach ($err as $m) echo '<div class="note err" role="alert">', e(t($m)), '</div>'; ?>
<form method="post" enctype="multipart/form-data" novalidate><?= csrf_field() ?>
<div class="two-col" style="margin-top:0">
  <section class="panel f"><h3><?= e(t('Informations du médecin')) ?></h3>
    <div class="up"><div class="pv" id="pv" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="7" r="3.2"/><path d="M3.5 17c.6-3.4 3.1-5.2 6.5-5.2s5.9 1.8 6.5 5.2"/></svg></div>
      <label><?= e(t('Photo du médecin (facultatif, JPG, PNG ou WebP, 3 Mo max)')) ?><input type="file" name="photo" accept="image/jpeg,image/png,image/webp"></label></div>
    <label><?= e(t('Nom et prénom')) ?><input name="name" required maxlength="75" placeholder="Dr. NOM Prénom" value="<?= e($v['name']) ?>"></label>
    <div class="two"><label><?= e(t('Spécialité')) ?><input name="speciality" list="specs" required maxlength="60" value="<?= e($v['speciality']) ?>">
      <datalist id="specs"><?php foreach ($specs as $s) echo '<option value="', e($s), '">'; ?></datalist></label>
      <label><?= e(t('Tarif de la consultation (DA)')) ?><input name="fees" type="number" min="1" max="100000" required value="<?= e($v['fees']) ?>"></label></div>
  </section>
  <section class="panel f"><h3><?= e(t('Cabinet')) ?></h3>
    <label><?= e(t('Nom du cabinet')) ?><input name="cname" required maxlength="200" value="<?= e($v['cname']) ?>"></label>
    <label><?= e(t('Adresse')) ?><input name="address" required maxlength="200" value="<?= e($v['address']) ?>"></label>
    <label><?= e(t('Téléphone')) ?><input name="phone" type="tel" required maxlength="20" value="<?= e($v['phone']) ?>"></label>
    <div class="two"><label><?= e(t('Ouverture')) ?><input name="open" type="time" required value="<?= e($v['open']) ?>"></label>
      <label><?= e(t('Fermeture')) ?><input name="close" type="time" required value="<?= e($v['close']) ?>"></label></div>
    <p class="muted hint"><?= e(t('Les créneaux de 30 minutes sont générés automatiquement entre ces deux heures.')) ?></p>
  </section>
</div>
<section class="panel" style="margin-top:28px"><h3 style="margin-bottom:14px"><?= e(t('Compte de connexion')) ?></h3>
  <label class="chk"><input type="checkbox" name="acc" value="1" id="acc"<?= $v['acc'] !== '' ? ' checked' : '' ?>><?= e(t("Créer un compte pour que ce médecin accède à son tableau de bord")) ?></label>
  <div id="accbox"><div class="f" style="margin-top:14px">
    <label><?= e(t('Email')) ?><input name="email" type="email" autocomplete="off" maxlength="100" value="<?= e($v['email']) ?>"></label>
    <div class="two"><label><?= e(t('Mot de passe')) ?> (<?= e(t('8 caractères minimum')) ?>)<input name="password" type="password" autocomplete="new-password" minlength="8"></label>
      <label><?= e(t('Confirmer le mot de passe')) ?><input name="confirm" type="password" autocomplete="new-password"></label></div>
  </div></div>
</section>
<div style="display:flex;gap:10px;justify-content:flex-end;margin-top:22px;flex-wrap:wrap"><a class="btn ghost" href="dashboard.php"><?= e(t('Annuler')) ?></a><button class="btn"><?= e(t('Ajouter le médecin')) ?></button></div>
</form></div>
<script>
(() => {
  const f = document.querySelector('input[name=photo]'), pv = document.getElementById('pv'), c = document.getElementById('acc'), box = document.getElementById('accbox');
  f.addEventListener('change', () => { const x = f.files[0]; pv.style.backgroundImage = x ? 'url(' + URL.createObjectURL(x) + ')' : ''; pv.classList.toggle('has', !!x); });
  const sync = () => { box.hidden = !c.checked; box.querySelectorAll('input').forEach((i) => { i.disabled = !c.checked; }); };
  c.addEventListener('change', sync); sync();
})();
</script>
<?php foot();
