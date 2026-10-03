<?php require __DIR__ . '/lib.php';
$u = require_login(); $admin = $u['role'] === 'admin';
$today = date('Y-m-d');
$docId = $admin ? (int)($_GET['doctor'] ?? 0) : (int)$u['doctor_id'];
$view = in_array($_GET['view'] ?? '', ['today', 'upcoming', 'history'], true) ? $_GET['view'] : 'today';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new = ['visit' => 'visited', 'cancel' => 'cancelled'][$_POST['action'] ?? ''] ?? null;
    $id = (int)($_POST['id'] ?? 0);
    if (!csrf_ok() || !$new || $id < 1) flash('Action refusée. Rechargez la page et réessayez.', 'err');
    else {
        $sql = "UPDATE appointments SET status=? WHERE id=? AND status='booked'"; $args = [$new, $id];
        if (!$admin) { $sql .= ' AND doctor_id=?'; $args[] = (int)$u['doctor_id']; }   // a doctor can only touch their own patients
        $st = db()->prepare($sql); $st->execute($args);
        $st->rowCount() ? flash($new === 'visited' ? 'Patient marqué comme vu.' : 'Rendez-vous annulé. Le créneau est de nouveau libre.')
                        : flash("Ce rendez-vous n'est plus actif.", 'err');
    }
    redirect($_SERVER['REQUEST_URI']);
}

$w = $docId ? ' AND a.doctor_id=?' : ''; $wa = $docId ? [$docId] : [];
$st = db()->prepare("SELECT COALESCE(SUM(a.appt_date=? AND a.status<>'cancelled'),0) td, COALESCE(SUM(a.appt_date>? AND a.status='booked'),0) up,
    COALESCE(SUM(a.status='visited' AND a.appt_date>=?),0) vis, COALESCE(SUM(a.status='cancelled' AND a.appt_date>=?),0) can FROM appointments a WHERE 1=1$w");
$m = date('Y-m-01'); $st->execute([$today, $today, $m, $m, ...$wa]); $stats = $st->fetch();

[$cond, $order] = match ($view) {
    'today' => ['a.appt_date=?', 'a.appt_time'],
    'upcoming' => ["a.appt_date>? AND a.status='booked'", 'a.appt_date,a.appt_time'],
    'history' => ['a.appt_date<?', 'a.appt_date DESC,a.appt_time DESC'],
};
$st = db()->prepare("SELECT a.*, d.name doc FROM appointments a JOIN doctors d ON d.id=a.doctor_id WHERE $cond$w ORDER BY $order LIMIT 200");
$st->execute([$today, ...$wa]); $rows = $st->fetchAll();
$doctors = $admin ? db()->query('SELECT id,name FROM doctors ORDER BY name')->fetchAll() : [];
$q = fn(array $x) => 'dashboard.php?' . http_build_query(array_filter(['view' => $view, 'doctor' => $docId ?: null, ...$x]));
$labels = ['booked' => 'À venir', 'visited' => 'Vu', 'cancelled' => 'Annulé'];
head('Tableau de bord', 'dashboard.php'); ?>
<div class="page">
<div class="dh"><div><h1 style="font-size:clamp(2rem,5vw,3.2rem)"><?= e(t('Bonjour,')) ?> <?= e($u['name']) ?></h1>
  <p class="muted" style="margin:6px 0 0"><?= e(fr($today, 'dl') . ' ' . fr($today, 'n') . ' ' . fr($today, 'ml')) ?></p></div>
  <div style="display:flex;gap:8px;flex-wrap:wrap"><?php if ($admin): ?><a class="btn sm" href="add_doctor.php">+ <?= e(t('Ajouter un médecin')) ?></a><a class="btn ghost sm" href="messages.php"><?= e(t('Messages')) ?></a><?php endif ?>
  <a class="btn ghost sm" href="account.php"><?= e(t('Mon compte')) ?></a>
  <form method="post" action="logout.php"><?= csrf_field() ?><button class="btn ghost sm"><?= e(t('Se déconnecter')) ?></button></form></div></div>
<?php if ($admin): ?><form method="get" class="fl" style="grid-template-columns:1fr auto;margin:0 0 20px"><input type="hidden" name="view" value="<?= e($view) ?>">
  <select name="doctor" data-icon="user" aria-label="<?= e(t('Médecin')) ?>"><option value="0"><?= e(t('Tous les médecins')) ?></option><?php foreach ($doctors as $d) echo '<option value="', $d['id'], '"', $d['id'] === $docId ? ' selected' : '', '>', e($d['name']), '</option>'; ?></select><button class="btn sm"><?= e(t('Afficher')) ?></button></form><?php endif ?>
<div class="stats"><div class="stat a"><b><?= (int)$stats['td'] ?></b><span><?= e(t("Rendez-vous aujourd'hui")) ?></span></div><div class="stat"><b><?= (int)$stats['up'] ?></b><span><?= e(t('À venir')) ?></span></div>
  <div class="stat m"><b><?= (int)$stats['vis'] ?></b><span><?= e(t('Patients vus ce mois-ci')) ?></span></div><div class="stat"><b><?= (int)$stats['can'] ?></b><span><?= e(t('Annulations ce mois-ci')) ?></span></div></div>
<div class="chips" style="margin-top:0"><?php foreach (['today' => "Aujourd'hui", 'upcoming' => 'À venir', 'history' => 'Historique'] as $k => $l): ?><a class="chip<?= $k === $view ? ' on' : '' ?>" href="<?= e($q(['view' => $k])) ?>"><?= e(t($l)) ?></a><?php endforeach ?></div>
<?php if (!$rows): ?><div class="empty"><?= e(t(['today' => "Aucun rendez-vous aujourd'hui.", 'upcoming' => 'Aucun rendez-vous à venir.', 'history' => 'Aucun rendez-vous passé.'][$view])) ?></div><?php endif;
$last = null; foreach ($rows as $r):
    if ($view !== 'today' && $r['appt_date'] !== $last) { $last = $r['appt_date']; echo '<div class="dgroup">', e(fr($last, 'dl') . ' ' . fr($last, 'n') . ' ' . fr($last, 'ml')), '</div>'; } ?>
<article class="ar <?= e($r['status']) ?>"><div class="at"><b><?= substr($r['appt_time'], 0, 5) ?></b></div>
  <div><h3><?= e($r['name']) ?></h3><p><a href="tel:<?= e($r['phone']) ?>"><?= e($r['phone']) ?></a><br><?= e($r['email']) ?></p>
    <?php if ($admin) echo '<p>', e($r['doc']), '</p>'; if ($r['message'] !== '') echo '<p class="msg">', e($r['message']), '</p>'; ?></div>
  <div class="ac"><span class="st <?= e($r['status']) ?>"><?= e(t($labels[$r['status']])) ?></span>
  <?php if ($r['status'] === 'booked'): ?>
    <form method="post" action="<?= e($_SERVER['REQUEST_URI']) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn sm" name="action" value="visit"><?= e(t('Marquer comme vu')) ?></button></form>
    <form method="post" action="<?= e($_SERVER['REQUEST_URI']) ?>" onsubmit="return confirm('<?= e(t('Annuler ce rendez-vous ?')) ?>')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn ghost danger sm" name="action" value="cancel"><?= e(t('Annuler')) ?></button></form>
  <?php endif ?></div></article>
<?php endforeach ?></div>
<?php foot();
