<?php require __DIR__ . '/lib.php';
$q = trim($_GET['q'] ?? ''); $s = trim($_GET['spec'] ?? ''); $a = trim($_GET['area'] ?? '');
$where = []; $args = [];
if ($q !== '') { $where[] = 'd.name LIKE ?'; $args[] = '%' . addcslashes($q, '%_') . '%'; }
if ($s !== '') { $where[] = 'd.speciality = ?'; $args[] = $s; }
if ($a !== '') { $where[] = 'c.address = ?'; $args[] = $a; }
$st = db()->prepare(DOC_SQL . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY d.name');
$st->execute($args); $rows = $st->fetchAll();
$specs = db()->query('SELECT DISTINCT speciality FROM doctors ORDER BY 1')->fetchAll(PDO::FETCH_COLUMN);
$areas = db()->query('SELECT DISTINCT address FROM clinics ORDER BY 1')->fetchAll(PDO::FETCH_COLUMN);
$url = fn(string $spec) => 'doctors.php?' . http_build_query(array_filter(['q' => $q, 'area' => $a, 'spec' => $spec]));
head('Médecins', 'doctors.php'); ?>
<div class="page"><h1 style="font-size:clamp(2rem,5vw,3.4rem)"><?= e(t('Trouver un médecin')) ?></h1>
<form class="fl" method="get"><input name="q" value="<?= e($q) ?>" placeholder="<?= e(t('Nom du médecin')) ?>" aria-label="<?= e(t('Nom')) ?>">
  <select name="area" data-icon="pin" aria-label="<?= e(t('Lieu')) ?>"><option value=""><?= e(t('Toute la ville')) ?></option><?php foreach ($areas as $x) echo '<option value="', e($x), '"', $x === $a ? ' selected' : '', '>', e(t($x)), '</option>'; ?></select>
  <input type="hidden" name="spec" value="<?= e($s) ?>"><button class="btn"><?= e(t('Filtrer')) ?></button></form>
<div class="chips"><a class="chip<?= $s === '' ? ' on' : '' ?>" href="<?= e($url('')) ?>"><?= e(t('Tous')) ?></a>
<?php foreach ($specs as $x): ?><a class="chip<?= $x === $s ? ' on' : '' ?>" href="<?= e($url($x)) ?>"><?= e(t($x)) ?></a><?php endforeach ?></div>
<p class="muted"><?= e(n_doctors(count($rows))) ?></p>
<div class="grid"><?php foreach ($rows as $d) doctor_card($d);
if (!$rows) echo '<div class="note err">', e(t('Aucun médecin ne correspond. Essayez un autre nom ou retirez un filtre.')), '</div>'; ?></div></div>
<?php foot();
