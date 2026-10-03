<?php require __DIR__ . '/lib.php';
require_login(true);
$rows = db()->query('SELECT * FROM feedback ORDER BY id DESC LIMIT 100')->fetchAll();
head('Messages', 'dashboard.php'); ?>
<div class="page"><div class="dh"><h1 style="font-size:clamp(2rem,5vw,3.2rem)"><?= e(t('Messages reçus')) ?></h1><a class="btn ghost sm" href="dashboard.php"><?= e(t('Retour au tableau de bord')) ?></a></div>
<?php if (!$rows) echo '<div class="empty">', e(t('Aucun message pour le moment.')), '</div>';
foreach ($rows as $r): ?><article class="panel" style="margin-bottom:12px"><h3><?= e($r['subject'] ?: t('Sans sujet')) ?></h3>
  <p class="muted" style="margin:4px 0 10px"><?= e($r['name']) ?>, <a href="mailto:<?= e($r['email']) ?>"><?= e($r['email']) ?></a><?= $r['phone'] ? ', ' . e($r['phone']) : '' ?><br><?= e($r['created_at']) ?></p>
  <p style="margin:0"><?= nl2br(e($r['message'])) ?></p></article><?php endforeach ?></div>
<?php foot();
