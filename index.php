<?php require __DIR__ . '/lib.php';
$st = db()->query(DOC_SQL . ' ORDER BY d.id'); $docs = $st->fetchAll();
$specs = db()->query('SELECT speciality, COUNT(*) n FROM doctors GROUP BY speciality ORDER BY speciality')->fetchAll();
$stack = array_filter([$docs[1] ?? null, $docs[3] ?? null, $docs[4] ?? null]);
head('Accueil', 'index.php'); ?>
<section class="hero"><div><h1><?= e(t('Réservez votre médecin en 30 secondes.')) ?></h1>
  <p><?= e(t('Choisissez un médecin à Annaba, un jour, un créneau libre. Vous recevez votre billet tout de suite.')) ?></p>
  <form class="sf" action="doctors.php"><input name="q" placeholder="<?= e(t('Nom du médecin')) ?>" aria-label="<?= e(t('Nom du médecin')) ?>">
    <select name="spec" data-icon="spec" aria-label="<?= e(t('Spécialité')) ?>"><option value=""><?= e(t('Toutes spécialités')) ?></option><?php foreach ($specs as $s) echo '<option value="', e($s['speciality']), '">', e(t($s['speciality'])), '</option>'; ?></select>
    <button class="btn"><?= e(t('Chercher')) ?></button></form></div>
  <div class="stack"><?php foreach ($stack as $d) echo '<img src="assets/img/doctors/', e($d['image']), '" alt="">'; ?>
    <?php if ($docs) echo '<b>', e(t('Prochain créneau : ')), e(next_slot($docs[1] ?? $docs[0])), '</b>'; ?></div></section>
<div class="bento"><?php foreach ($specs as $s): ?><a href="doctors.php?spec=<?= urlencode($s['speciality']) ?>"><b><?= e(t($s['speciality'])) ?></b><span><?= e(n_doctors((int)$s['n'])) ?></span></a><?php endforeach ?></div>
<div class="sec"><h2><?= e(t('Disponibles dès demain')) ?></h2><a class="chip" href="doctors.php"><?= e(t('Voir tous les médecins')) ?></a></div>
<div class="grid" style="margin-bottom:64px"><?php foreach (array_slice($docs, 0, 3) as $d) doctor_card($d); ?></div>
<?php foot();
