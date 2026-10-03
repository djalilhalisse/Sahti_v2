<?php require __DIR__ . '/lib.php';
$id = (int)($_GET['doctor'] ?? $_POST['doctor'] ?? 0);
$d = find_doctor($id);
if (!$d) { flash('Médecin introuvable.', 'err'); redirect('doctors.php'); }

// Ticket screen (shown once after a successful booking)
if (isset($_GET['done'], $_SESSION['ticket']) && $_SESSION['ticket']['doctor'] === $id) {
    $t = $_SESSION['ticket']; unset($_SESSION['ticket']);
    head('Rendez-vous confirmé', 'doctors.php'); ?>
<div class="page"><div class="note ok"><?= e(t('Rendez-vous confirmé. Gardez ce billet pour annuler si besoin.')) ?></div>
<div class="tk"><div class="m"><small><?= e($d['name']) ?></small>
  <h2><?= e(fr($t['date'], 'dl') . ' ' . fr($t['date'], 'n') . ' ' . fr($t['date'], 'ml')) ?><br><?= e(t('à')) ?> <?= e(substr($t['time'], 0, 5)) ?></h2>
  <small><?= e(t($d['address'])) ?></small><small><?= e($t['name']) ?></small></div>
  <div class="s"><small><?= e(t('Billet')) ?></small><b>#<?= (int)$t['id'] ?></b></div></div>
<div style="display:flex;gap:10px;justify-content:center;margin-top:24px;flex-wrap:wrap">
  <a class="btn ghost" href="cancel.php?id=<?= (int)$t['id'] ?>"><?= e(t('Annuler ce rendez-vous')) ?></a><a class="btn" href="doctors.php"><?= e(t('Prendre un autre rendez-vous')) ?></a></div></div>
<?php foot(); exit; }

$min = date('Y-m-d', strtotime('+1 day')); $max = date('Y-m-d', strtotime('+14 days'));
$date = (string)($_POST['date'] ?? $_GET['date'] ?? $min);
$dt = DateTime::createFromFormat('Y-m-d', $date);
$dateOk = $dt && $dt->format('Y-m-d') === $date && $date >= $min && $date <= $max;
if (!$dateOk) $date = $min;

$all = slots($d);
$q = db()->prepare("SELECT appt_time FROM appointments WHERE doctor_id=? AND appt_date=? AND status='booked'");
$q->execute([$id, $date]); $taken = $q->fetchAll(PDO::FETCH_COLUMN);

$err = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? ''); $phone = clean_phone($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? ''); $time = $_POST['time'] ?? ''; $msg = trim($_POST['message'] ?? '');
    if (!csrf_ok()) $err[] = 'Votre session a expiré. Rechargez la page et réessayez.';
    if (!$dateOk) $err[] = 'Choisissez un jour dans les 14 prochains jours.';
    if (!in_array($time, $all, true) || in_array($time, $taken, true)) $err[] = 'Choisissez une heure disponible.';
    if (mb_strlen($name) < 3 || mb_strlen($name) > 50) $err[] = 'Indiquez votre nom et prénom.';
    if (!preg_match('/^\+?\d{9,15}$/', $phone)) $err[] = 'Le numéro de téléphone doit contenir 9 à 15 chiffres.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100) $err[] = "L'adresse email n'est pas valide.";
    if (!$err) {
        try {
            db()->prepare('INSERT INTO appointments (doctor_id,name,email,phone,appt_date,appt_time,message) VALUES (?,?,?,?,?,?,?)')
                ->execute([$id, $name, $email, $phone, $date, $time, mb_substr($msg, 0, 500)]);
            $_SESSION['ticket'] = ['doctor' => $id, 'id' => (int)db()->lastInsertId(), 'date' => $date, 'time' => $time, 'name' => $name];
            redirect("book.php?doctor=$id&done=1");
        } catch (PDOException $ex) {
            if ($ex->getCode() !== '23000') throw $ex;
            $err[] = "Ce créneau vient d'être réservé par quelqu'un d'autre. Choisissez une autre heure.";
        }
    }
}
$periods = [t('Matin') => array_filter($all, fn($t) => $t < '12:00'), t('Après-midi') => array_filter($all, fn($t) => $t >= '12:00')];
head('Prendre rendez-vous', 'doctors.php'); ?>
<div class="page"><div class="bk">
<aside class="side"><img src="assets/img/doctors/<?= e($d['image']) ?>" alt="">
  <div><h2 style="font-size:1.5rem"><?= e($d['name']) ?></h2><span class="nx" style="justify-self:start"><?= e(t($d['speciality'])) ?></span>
  <p class="muted" style="margin:8px 0 0"><?= e(t($d['address'])) ?><br><?= e(sprintf(t('Ouvert de %s à %s'), substr($d['open_time'], 0, 5), substr($d['close_time'], 0, 5))) ?><br><b style="color:var(--ink)"><?= (int)$d['fees_da'] ?> <?= e(t('DA')) ?></b><?= e(t(' la consultation')) ?></p></div></aside>
<form method="post" novalidate>
  <?php foreach ($err as $m) echo '<div class="note err" role="alert">', e(t($m)), '</div>'; ?>
  <?= csrf_field() ?><input type="hidden" name="doctor" value="<?= $id ?>"><input type="hidden" name="date" value="<?= e($date) ?>">
  <div class="step"><h3><i>1</i><?= e(t('Choisissez un jour')) ?></h3><div class="days">
    <?php for ($i = 1; $i <= 14; $i++): $x = date('Y-m-d', strtotime("+$i day")); ?>
      <a href="book.php?doctor=<?= $id ?>&date=<?= $x ?>"<?= $x === $date ? ' class="on" aria-current="date"' : '' ?>><span><?= fr($x, 'd') ?></span><b><?= fr($x, 'n') ?></b><span><?= fr($x, 'm') ?></span></a>
    <?php endfor ?></div></div>
  <div class="step"><h3><i>2</i><?= e(t('Choisissez une heure')) ?></h3>
    <?php foreach ($periods as $label => $list): if (!$list) continue; ?><div class="per"><?= $label ?></div><div class="slots">
      <?php foreach ($list as $t): $off = in_array($t, $taken, true); ?>
        <label><input type="radio" name="time" value="<?= $t ?>"<?= $off ? ' disabled' : '' ?><?= ($_POST['time'] ?? '') === $t ? ' checked' : '' ?>><span><?= substr($t, 0, 5) ?></span></label>
      <?php endforeach ?></div><?php endforeach ?></div>
  <div class="step f"><h3><i>3</i><?= e(t('Vos coordonnées')) ?></h3>
    <label><?= e(t('Nom et prénom')) ?><input name="name" autocomplete="name" required maxlength="50" value="<?= e($_POST['name'] ?? '') ?>"></label>
    <div class="two"><label><?= e(t('Téléphone')) ?><input name="phone" type="tel" autocomplete="tel" required value="<?= e($_POST['phone'] ?? '') ?>"></label>
    <label><?= e(t('Email')) ?><input name="email" type="email" autocomplete="email" required value="<?= e($_POST['email'] ?? '') ?>"></label></div>
    <label><?= e(t('Message pour le médecin (facultatif)')) ?><textarea name="message" rows="2" maxlength="500"><?= e($_POST['message'] ?? '') ?></textarea></label>
    <button class="btn"><?= e(t('Confirmer le rendez-vous')) ?></button></div>
</form></div></div>
<?php foot();
