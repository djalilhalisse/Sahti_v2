<?php require __DIR__ . '/lib.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['tries'] = array_values(array_filter($_SESSION['tries'] ?? [], fn($t) => $t > time() - 600));
    if (count($_SESSION['tries']) >= 5) {
        flash('Trop de tentatives. Réessayez dans 10 minutes.', 'err');
    } else {
        $ok = false;
        if (csrf_ok() && ($tid = (int)($_POST['id'] ?? 0)) > 0) {
            $st = db()->prepare("UPDATE appointments SET status='cancelled' WHERE id=? AND phone=? AND status='booked'");
            $st->execute([$tid, clean_phone($_POST['phone'] ?? '')]);
            $ok = $st->rowCount() === 1;
        }
        if ($ok) flash('Rendez-vous annulé. Le créneau est de nouveau libre.');
        else { $_SESSION['tries'][] = time(); flash('Aucun rendez-vous actif ne correspond à ce billet et à ce numéro. Vérifiez les deux.', 'err'); }
    }
    redirect('cancel.php');
}
head('Annuler un rendez-vous', 'cancel.php'); ?>
<div class="page"><h1 style="font-size:clamp(2rem,5vw,3.4rem)"><?= e(t('Annuler un rendez-vous')) ?></h1>
<div class="two-col"><form method="post" class="panel f"><?= csrf_field() ?>
  <label><?= e(t('Numéro de billet')) ?><input name="id" type="number" min="1" required value="<?= e($_GET['id'] ?? '') ?>"></label>
  <label><?= e(t('Téléphone de la réservation')) ?><input name="phone" type="tel" required></label>
  <button class="btn red"><?= e(t('Annuler le rendez-vous')) ?></button></form>
<div class="panel mint"><h3><?= e(t('Où trouver mon billet ?')) ?></h3><p><?= e(t("Le numéro s'affiche à la fin de votre réservation. Le créneau annulé redevient disponible pour les autres patients.")) ?></p></div></div></div>
<?php foot();
