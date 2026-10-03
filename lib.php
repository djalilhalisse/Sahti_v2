<?php
declare(strict_types=1);
date_default_timezone_set('Africa/Algiers');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();

// Fallbacks so the app also runs on PHP builds without the mbstring extension
if (!function_exists('mb_strlen')) {
    function mb_strlen(string $s): int { return preg_match_all('/./su', $s) ?: 0; }
    function mb_substr(string $s, int $start, ?int $len = null): string { return implode('', array_slice(preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [], $start, $len)); }
}

/** Current language: ?lang=fr|ar wins, then the saved cookie, default French. */
function lang(): string {
    static $l;
    if ($l === null) {
        $l = (($_GET['lang'] ?? $_COOKIE['lang'] ?? 'fr') === 'ar') ? 'ar' : 'fr';
        if (isset($_GET['lang']) && !headers_sent()) setcookie('lang', $l, ['expires' => time() + 31536000, 'path' => '/', 'samesite' => 'Lax']);
    }
    return $l;
}
/** Translate a French UI string (the key) into the current language. Falls back to French. */
function t(string $fr): string {
    static $d;
    if (lang() === 'fr') return $fr;
    $d ??= require __DIR__ . '/lang/ar.php';
    return $d[$fr] ?? $fr;
}
function n_doctors(int $n): string {
    if (lang() !== 'ar') return $n . ' médecin(s)';
    return match (true) { $n === 1 => 'طبيب واحد', $n === 2 => 'طبيبان', $n <= 10 => $n . ' أطباء', default => $n . ' طبيبًا' };
}
/** URL of an asset with a version stamp, so browsers never keep an outdated copy. */
function asset(string $p): string { $f = __DIR__ . '/' . $p; return $p . (is_file($f) ? '?v=' . filemtime($f) : ''); }
function lang_switch(): string {
    $to = lang() === 'ar' ? 'fr' : 'ar';
    $q = $_GET; $q['lang'] = $to;
    $href = (string)strtok((string)($_SERVER['REQUEST_URI'] ?? ''), '?') . '?' . http_build_query($q);
    return '<a class="lang" href="' . e($href) . '" hreflang="' . $to . '" lang="' . $to . '" title="' . ($to === 'ar' ? 'العربية' : 'Français') . '">'
         . '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true"><circle cx="10" cy="10" r="7.5"/><path d="M2.5 10h15M10 2.5c2.2 2.2 3.2 4.7 3.2 7.5S12.2 15.3 10 17.5M10 2.5C7.8 4.7 6.8 7.2 6.8 10s1 5.3 3.2 7.5"/></svg>'
         . '<span>' . ($to === 'ar' ? 'العربية' : 'Français') . '</span></a>';
}

lang(); // resolve the language (and save the cookie) before any output

const DOC_SQL = 'SELECT d.id,d.name,d.image,d.speciality,d.fees_da,c.address,c.open_time,c.close_time FROM doctors d JOIN clinics c ON c.doctor_id=d.id';
const JOURS = ['dim.', 'lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.'];
const JOURS_L = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
const MOIS = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
const JOURS_AR = ['أحد', 'اثنين', 'ثلاثاء', 'أربعاء', 'خميس', 'جمعة', 'سبت'];
const JOURS_L_AR = ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
const MOIS_AR = ['جان', 'فيف', 'مارس', 'أفر', 'ماي', 'جوان', 'جويل', 'أوت', 'سبت', 'أكت', 'نوف', 'ديس'];
const MOIS_L_AR = ['جانفي', 'فيفري', 'مارس', 'أفريل', 'ماي', 'جوان', 'جويلية', 'أوت', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
const MOIS_L = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

function db(): PDO {
    static $pdo;
    return $pdo ??= new PDO(
        'mysql:host=' . (getenv('DB_HOST') ?: 'localhost') . ';dbname=' . (getenv('DB_NAME') ?: 'sahti') . ';charset=utf8mb4',
        getenv('DB_USER') ?: 'root', getenv('DB_PASS') ?: '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
    );
}
function e(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function csrf_field(): string { $_SESSION['t'] ??= bin2hex(random_bytes(16)); return '<input type="hidden" name="_t" value="' . $_SESSION['t'] . '">'; }
function csrf_ok(): bool { $t = $_SESSION['t'] ?? ''; return $t !== '' && hash_equals($t, (string)($_POST['_t'] ?? '')); }
function flash(?string $msg = null, string $type = 'ok'): ?array {
    if ($msg !== null) { $_SESSION['f'] = [$msg, $type]; return null; }
    $f = $_SESSION['f'] ?? null; unset($_SESSION['f']); return $f;
}
function user(): ?array { return $_SESSION['user'] ?? null; }
function require_login(bool $admin = false): array {
    $u = user();
    if (!$u) { flash('Connectez-vous pour accéder à votre espace.', 'err'); redirect('login.php'); }
    if ($admin && $u['role'] !== 'admin') { http_response_code(403); exit(t("Accès réservé à l'administrateur.")); }
    return $u;
}
function redirect(string $to): never { header("Location: $to"); exit; }
function clean_phone(?string $p): string { return preg_replace('/[\s.\-]/', '', (string)$p); }
function fr(string $date, string $part): string {
    $t = strtotime($date);
    $ar = lang() === 'ar';
    return match ($part) { 'd' => ($ar ? JOURS_AR : JOURS)[(int)date('w', $t)], 'dl' => ($ar ? JOURS_L_AR : JOURS_L)[(int)date('w', $t)], 'n' => date('j', $t),
        'm' => ($ar ? MOIS_AR : MOIS)[(int)date('n', $t) - 1], 'ml' => ($ar ? MOIS_L_AR : MOIS_L)[(int)date('n', $t) - 1] };
}
function find_doctor(int $id): ?array {
    $st = db()->prepare(DOC_SQL . ' WHERE d.id=?'); $st->execute([$id]); return $st->fetch() ?: null;
}
/** 30-minute slots between opening and closing time, as H:i:00 */
function slots(array $d): array {
    $r = [];
    for ($t = strtotime($d['open_time']); $t < strtotime($d['close_time']); $t += 1800) $r[] = date('H:i:00', $t);
    return $r;
}
function next_slot(array $d): string {
    static $taken;
    if ($taken === null) {
        $taken = [];
        foreach (db()->query("SELECT doctor_id,appt_date,appt_time FROM appointments WHERE status='booked' AND appt_date>CURDATE()") as $r)
            $taken[$r['doctor_id']][$r['appt_date']][$r['appt_time']] = 1;
    }
    for ($i = 1; $i <= 14; $i++) {
        $dt = date('Y-m-d', strtotime("+$i day"));
        foreach (slots($d) as $s)
            if (empty($taken[$d['id']][$dt][$s])) return ($i === 1 ? t('Demain') : fr($dt, 'd') . ' ' . fr($dt, 'n')) . ' ' . substr($s, 0, 5);
    }
    return t('Complet');
}
function doctor_card(array $d): void { ?>
<article class="dc"><a class="ph" href="book.php?doctor=<?= $d['id'] ?>"><img loading="lazy" src="assets/img/doctors/<?= e($d['image']) ?>" alt=""></a>
  <div class="bd"><div><h3><?= e($d['name']) ?></h3><p class="sp"><?= e(t($d['speciality'])) ?>, <?= e(t($d['address'])) ?></p></div>
  <div class="meta"><span class="nx"><?= e(next_slot($d)) ?></span><b><?= (int)$d['fees_da'] ?> <?= e(t('DA')) ?></b></div>
  <a class="btn" href="book.php?doctor=<?= $d['id'] ?>"><?= e(t('Réserver')) ?></a></div></article>
<?php }

function head(string $title, string $active = ''): void {
    $nav = ['index.php' => 'Accueil', 'doctors.php' => 'Médecins', 'cancel.php' => 'Annuler', 'contact.php' => 'Contact'];
    $nav += user() ? ['dashboard.php' => 'Tableau de bord'] : ['login.php' => 'Espace médecin'];
    $ar = lang() === 'ar';
    echo '<!doctype html><html lang="' . lang() . '" dir="' . ($ar ? 'rtl' : 'ltr') . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">'
       . '<title>' . e(t($title)) . ' | SAHTI</title><meta name="theme-color" content="#3B3FF2">'
       . '<link rel="icon" type="image/svg+xml" href="' . asset('assets/img/logo-mark.svg') . '">'
       . '<link rel="icon" type="image/png" sizes="32x32" href="' . asset('assets/img/favicon-32.png') . '">'
       . '<link rel="apple-touch-icon" href="' . asset('assets/img/apple-touch-icon.png') . '">'
       . '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500..800&family=Figtree:wght@400..700&family=Readex+Pro:wght@400..700&display=swap">'
       . '<link rel="stylesheet" href="' . asset('assets/style.css') . '"><script src="' . asset('assets/select.js') . '" defer></script></head><body>'
       . '<div class="nav"><div class="pill"><a class="brand" href="index.php" aria-label="SAHTI">'
       . '<svg viewBox="0 0 64 64" aria-hidden="true"><rect width="64" height="64" rx="18" class="lg-bg"/><g transform="translate(32 31) scale(1.2) translate(-32 -31)"><path class="lg-heart" d="M32 43.5C17 33.5 15.5 24.5 20.5 20c4.5-3.8 9.5-1.5 11.5 2.5 2-4 7-6.3 11.5-2.5 5 4.5 3.5 13.5-11.5 23.5Z"/><path class="lg-pulse" d="M17 30.5h8.5l3-6 4 11.5 3.5-8 2 2.5H47"/></g><path class="lg-cup" d="M15 48.5c7 7.5 27 7.5 34 0"/></svg>'
       . '<span>sahti</span><small lang="ar" dir="rtl">صحتي</small></a><nav class="links">';
    foreach ($nav as $href => $label) echo '<a href="' . $href . '"' . ($href === $active ? ' class="on"' : '') . '>' . e(t($label)) . '</a>';
    echo '</nav>', lang_switch(), '</div></div><main class="w">';
    if ($f = flash()) echo '<div class="note ' . e($f[1]) . '" role="status" style="margin-top:20px">' . e(t($f[0])) . '</div>';
}
function foot(): void {
    echo '</main><footer><div class="w">' . e(t('SAHTI : accédez rapidement aux disponibilités de vos professionnels de santé.')) . '</div></footer></body></html>';
}
