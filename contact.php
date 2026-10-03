<?php require __DIR__ . '/lib.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $f = array_map('trim', array_intersect_key($_POST, array_flip(['name', 'phone', 'email', 'subject', 'message'])));
    if (csrf_ok() && mb_strlen($f['name'] ?? '') >= 2 && filter_var($f['email'] ?? '', FILTER_VALIDATE_EMAIL) && mb_strlen($f['message'] ?? '') >= 5) {
        db()->prepare('INSERT INTO feedback (name,phone,email,subject,message) VALUES (?,?,?,?,?)')
            ->execute([mb_substr($f['name'], 0, 50), mb_substr($f['phone'] ?? '', 0, 15), $f['email'], mb_substr($f['subject'] ?? '', 0, 150), mb_substr($f['message'], 0, 500)]);
        flash('Message envoyé. Nous vous répondons rapidement.');
    } else flash('Renseignez votre nom, un email valide et votre message.', 'err');
    redirect('contact.php');
}
head('Contact', 'contact.php'); ?>
<div class="page"><h1 style="font-size:clamp(2rem,5vw,3.4rem)"><?= e(t('Une question ?')) ?></h1>
<div class="two-col"><form method="post" class="panel f"><?= csrf_field() ?>
  <label><?= e(t('Nom')) ?><input name="name" required maxlength="50"></label>
  <div class="two"><label><?= e(t('Téléphone')) ?><input name="phone" type="tel" maxlength="15"></label><label><?= e(t('Email')) ?><input name="email" type="email" required></label></div>
  <label><?= e(t('Sujet')) ?><input name="subject" maxlength="150"></label>
  <label><?= e(t('Message')) ?><textarea name="message" rows="4" required maxlength="500"></textarea></label>
  <button class="btn"><?= e(t('Envoyer le message')) ?></button></form>
<div class="panel mint"><h3><?= e(t('Nous joindre')) ?></h3><p dir="ltr" style="text-align:start">+213 07 77 77 77 77<br>SAHTI@gmail.com</p></div></div></div>
<?php foot();
