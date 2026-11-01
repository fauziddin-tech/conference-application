<?php
/* ============================================================
 * Bootstrap aplikasi: konfigurasi, sesi, database, helper, layout.
 * ============================================================ */
if (!file_exists(__DIR__ . '/config.php')) { header('Location: install/'); exit; }
require __DIR__ . '/config.php';
require_once __DIR__ . '/lib/schema.php';
require_once __DIR__ . '/lib/paper.php';
require_once __DIR__ . '/lib/backup.php';
require_once __DIR__ . '/lib/i18n.php';
require_once __DIR__ . '/lib/image.php';

// Jangan tampilkan detail teknis ke pengunjung (aktifkan define('DEBUG', true) di config.php saat pengembangan).
if (!defined('DEBUG') || !DEBUG) ini_set('display_errors', '0');
ini_set('log_errors', '1');
set_exception_handler(function ($x) {
    error_log('Konferensi: ' . get_class($x) . ': ' . $x->getMessage() . ' @ ' . $x->getFile() . ':' . $x->getLine());
    if (!headers_sent()) { http_response_code(500); header('Content-Type: text/html; charset=utf-8'); }
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Error</title></head><body style="font-family:system-ui,sans-serif;max-width:560px;margin:12vh auto;padding:0 16px;color:#1d3a3a"><h2>Sorry, something went wrong.</h2><p>Please try again in a few minutes. If the problem persists, contact the committee.</p><p style="color:#666">Maaf, terjadi gangguan. Silakan coba lagi beberapa saat lagi.</p></body></html>';
    if (defined('DEBUG') && DEBUG) echo '<pre>' . htmlspecialchars((string)$x) . '</pre>';
    exit;
});

$HTTPS = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? '') == '443' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
if (session_status() === PHP_SESSION_NONE) {
    // Sesi disimpan di folder privat aplikasi dan bertahan 8 jam tanpa aktivitas, agar formulir panjang
    // (registrasi, review) tidak gagal karena sesi dihapus oleh pembersihan sesi bawaan hosting (±24 menit).
    $sd = __DIR__ . '/uploads/private/sessions';
    if (!is_dir($sd)) @mkdir($sd, 0700, true);
    if (is_dir($sd) && is_writable($sd)) session_save_path($sd);
    ini_set('session.gc_maxlifetime', '28800');
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params(['lifetime' => 0, 'path' => BASE, 'httponly' => true, 'samesite' => 'Lax', 'secure' => $HTTPS]);
    session_start();
}
if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}
$pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

/* ---------- pengaturan ---------- */
$SET = null;
function settings_load() { global $pdo, $SET; $SET = $pdo->query("SELECT k,v FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR); }
function setting($k, $d = '') { global $SET; if ($SET === null) settings_load(); return ($SET[$k] ?? '') !== '' ? $SET[$k] : $d; }
function setSetting($k, $v) { global $pdo, $SET; $pdo->prepare("REPLACE INTO settings(k,v) VALUES(?,?)")->execute([$k, (string)$v]); $SET[$k] = (string)$v; }
function rows($k) { $o = []; foreach (preg_split('/\R/', setting($k)) as $l) { $l = trim($l); if ($l !== '') $o[] = array_map('trim', explode('|', $l)); } return $o; }

settings_load();
if ((int)setting('db_version', '1') < DB_VERSION) { migrate($pdo); settings_load(); }
@date_default_timezone_set(setting('timezone', 'Asia/Jakarta'));
// Samakan zona waktu sesi MySQL dengan PHP agar NOW()/TIMESTAMP konsisten (mis. WIB, bukan UTC server).
try { $pdo->exec("SET time_zone='" . date('P') . "'"); } catch (Exception $x) {}

/* ---------- utilitas umum ---------- */
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function token() { if (empty($_SESSION['t'])) $_SESSION['t'] = bin2hex(random_bytes(16)); return $_SESSION['t']; }
function csrf() { return '<input type="hidden" name="t" value="' . token() . '">'; }
function chk()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || hash_equals($_SESSION['t'] ?? '', (string)($_POST['t'] ?? ''))) return;
    $m = t('Your session has expired or the page was open too long. Please try again.');
    if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch') { header('Content-Type: application/json'); echo json_encode(['error' => $m]); exit; }
    flash($m); go($_SERVER['REQUEST_URI'] ?? BASE);
}
function post($k) { return trim((string)($_POST[$k] ?? '')); }
function flash($m = null) { if ($m !== null) { $_SESSION['f'] = $m; return; } $f = $_SESSION['f'] ?? ''; unset($_SESSION['f']); return $f; }
function go($to) { header('Location: ' . $to); exit; }
function brand() { $n = setting('name'); return preg_match('/\(([^)]+)\)/', $n, $m) ? $m[1] : $n; }
function isurl($v) { return (bool)preg_match('#^https?://#i', (string)$v); }
function words($s) { return count(preg_split('/\s+/u', trim((string)$s), -1, PREG_SPLIT_NO_EMPTY)); }
function lbl($map, $k) { return $map[$k] ?? $k; }
function no($p, $id) { return $p . str_pad((string)$id, 4, '0', STR_PAD_LEFT); }
function initials($n) { $w = preg_split('/[\s,]+/', (string)$n, -1, PREG_SPLIT_NO_EMPTY); $o = ''; foreach ($w as $x) { if (strpos($x, '.') !== false) continue; $o .= mb_strtoupper(mb_substr($x, 0, 1)); if (mb_strlen($o) >= 2) break; } return $o !== '' ? $o : '?'; }
/** Host dari permintaan saat ini (TIDAK dipercaya untuk tautan di email/dokumen; pakai siteUrl()). */
function reqHost() { return preg_replace('/[^A-Za-z0-9.:-]/', '', $_SERVER['HTTP_HOST'] ?? 'localhost'); }
function reqUrl() { global $HTTPS; return ($HTTPS ? 'https' : 'http') . '://' . reqHost() . BASE; }
/**
 * Alamat resmi situs (mis. https://picoe.org/). Dipakai untuk semua tautan di email, QR, sertifikat, kalender.
 * Urutan: konstanta SITE_URL di config.php → Pengaturan (terisi otomatis saat admin membuka panel) → alamat permintaan.
 * Mencegah "Host header injection" (tautan reset password diarahkan ke domain penipu).
 */
function siteUrl()
{
    $s = defined('SITE_URL') ? (string)SITE_URL : setting('site_url');
    return preg_match('#^https?://[A-Za-z0-9.:-]+(/.*)?$#', $s) ? rtrim($s, '/') . '/' : reqUrl();
}
function host() { return (string)parse_url(siteUrl(), PHP_URL_HOST) . (parse_url(siteUrl(), PHP_URL_PORT) ? ':' . parse_url(siteUrl(), PHP_URL_PORT) : ''); }
function absUrl($p = '') { return siteUrl() . ltrim($p, '/'); }
function fdate($d) { if (!$d) return ''; $t = strtotime($d); if (!$t) return $d; $m = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember']; return date('j', $t) . ' ' . $m[(int)date('n', $t)] . ' ' . date('Y', $t); }
function fdateEn($d) { $t = strtotime((string)$d); return $t ? date('F j, Y', $t) : (string)$d; }

$PAY = ['unpaid' => t('Unpaid'), 'pending' => t('Awaiting verification'), 'verified' => t('Verified'), 'rejected' => t('Rejected')];
$PAPER = ['submitted' => t('Submitted'), 'under_review' => t('Under review'), 'revision' => t('Revision required'), 'accepted' => t('Accepted'), 'rejected' => t('Rejected')];
$REC = ['accept' => t('Accept'), 'minor' => t('Minor revision'), 'major' => t('Major revision'), 'reject' => t('Reject')];
$ULBL = ['pending' => t('Awaiting confirmation'), 'confirmed' => t('Confirmed'), 'rejected' => t('Rejected')];

/* ---------- pengguna & akses ---------- */
function user()
{
    global $pdo; static $u = false;
    if (empty($_SESSION['uid'])) return null;
    if ($u === false || !$u || (int)$u['id'] !== (int)$_SESSION['uid']) { $s = $pdo->prepare("SELECT * FROM users WHERE id=?"); $s->execute([$_SESSION['uid']]); $u = $s->fetch() ?: null; }
    if ($u) {
        // sesi lama (dibuat sebelum password diganti/direset) dikeluarkan
        if (!isset($_SESSION['sv'])) $_SESSION['sv'] = (int)$u['sess_ver'];
        elseif ((int)$_SESSION['sv'] !== (int)$u['sess_ver']) { unset($_SESSION['uid'], $_SESSION['sv']); $u = null; }
    }
    return $u;
}
function need($roles = null)
{
    $u = user();
    if (!$u) { $_SESSION['back'] = $_SERVER['REQUEST_URI'] ?? ''; go(BASE . 'login.php'); }
    if ($roles && !in_array($u['role'], (array)$roles, true)) { http_response_code(403); die(t('Access denied')); }
    return $u;
}
/** Ganti password & keluarkan semua sesi lain akun ini (sesi saat ini tetap masuk bila $keepCurrent). */
function setPassword($uid, $pw, $keepCurrent = false)
{
    global $pdo;
    $pdo->prepare("UPDATE users SET password=?,sess_ver=sess_ver+1,reset_hash=NULL,reset_exp=NULL WHERE id=?")->execute([password_hash($pw, PASSWORD_DEFAULT), $uid]);
    if ($keepCurrent && (int)($_SESSION['uid'] ?? 0) === (int)$uid) { $q = $pdo->prepare("SELECT sess_ver FROM users WHERE id=?"); $q->execute([$uid]); $_SESSION['sv'] = (int)$q->fetchColumn(); }
}
function homeFor($u) { return ['admin' => 'admin/', 'reviewer' => 'reviewer.php'][$u['role']] ?? 'dashboard.php'; }
function regOf($uid) { global $pdo; $q = $pdo->prepare("SELECT * FROM registrations WHERE user_id=?"); $q->execute([$uid]); return $q->fetch() ?: null; }

/* ---------- pembatasan percobaan (anti brute force) ---------- */
function throttled($k, $max, $win)
{
    global $pdo;
    if (mt_rand(1, 20) === 1) $pdo->prepare("DELETE FROM throttle WHERE ts<?")->execute([time() - 86400]);
    $s = $pdo->prepare("SELECT COUNT(*) FROM throttle WHERE k=? AND ts>?"); $s->execute([$k, time() - $win]);
    return (int)$s->fetchColumn() >= $max;
}
function hit($k) { global $pdo; $pdo->prepare("INSERT INTO throttle(k,ts) VALUES(?,?)")->execute([$k, time()]); }
/** IP pengunjung. Di balik Cloudflare, REMOTE_ADDR adalah IP Cloudflare; gunakan CF-Connecting-IP hanya bila request benar-benar dari Cloudflare. */
function ip()
{
    $r = $_SERVER['REMOTE_ADDR'] ?? '0';
    $cf = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '';
    if ($cf !== '' && filter_var($cf, FILTER_VALIDATE_IP) && fromCloudflare($r)) $r = $cf;
    return substr($r, 0, 45);
}
function ipInCidr($ip, $cidr)
{
    [$net, $bits] = explode('/', $cidr); $a = @inet_pton($ip); $b = @inet_pton($net);
    if ($a === false || $b === false || strlen($a) !== strlen($b)) return false;
    $bytes = intdiv((int)$bits, 8); $rem = (int)$bits % 8;
    if (strncmp($a, $b, $bytes) !== 0) return false;
    if ($rem === 0) return true;
    $m = chr((0xFF << (8 - $rem)) & 0xFF);
    return (($a[$bytes] & $m) === ($b[$bytes] & $m));
}
function fromCloudflare($ip)
{
    foreach (['173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13', '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
        '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32'] as $c) if (ipInCidr($ip, $c)) return true;
    return false;
}

/* ---------- anti-spam ---------- */
/** Nama orang: huruf (semua bahasa), spasi, titik, koma, apostrof, tanda hubung, kurung. Tanpa URL, angka, atau simbol. */
function validName($n)
{
    $n = trim((string)$n);
    return (bool)preg_match("/^[\p{L}\p{M}][\p{L}\p{M}\s.,'’\-()]{1,119}$/u", $n) && !preg_match('#https?|www\.|\.(com|ru|net|org|xyz|top|info)\b#i', $n);
}
/** Domain email sekali pakai / diblokir (diatur di Pengaturan). */
function blockedEmail($m)
{
    $d = strtolower((string)substr(strrchr((string)$m, '@'), 1));
    foreach (preg_split('/[\s,;]+/', strtolower(setting('blocked_domains'))) as $b) {
        $b = trim($b); if ($b === '') continue;
        if ($d === $b || substr($d, -strlen($b) - 1) === '.' . $b) return true;
    }
    return false;
}
function captchaOn() { return setting('turnstile_site') !== '' && setting('turnstile_secret') !== ''; }
function captchaHtml() { return captchaOn() ? '<div class="cf-turnstile full" data-sitekey="' . e(setting('turnstile_site')) . '"></div>' : ''; }
/** Verifikasi Cloudflare Turnstile di server. */
function captchaOk()
{
    if (!captchaOn()) return true;
    $resp = (string)($_POST['cf-turnstile-response'] ?? '');
    if ($resp === '') return false;
    $data = http_build_query(['secret' => setting('turnstile_secret'), 'response' => $resp, 'remoteip' => ip()]);
    $url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify'; $out = false;
    if (function_exists('curl_init')) {
        $c = curl_init($url);
        curl_setopt_array($c, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $data, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8]);
        $out = curl_exec($c); curl_close($c);
    } else {
        $out = @file_get_contents($url, false, stream_context_create(['http' => ['method' => 'POST', 'header' => "Content-Type: application/x-www-form-urlencoded\r\n", 'content' => $data, 'timeout' => 8]]));
    }
    $j = json_decode((string)$out, true);
    return !empty($j['success']);
}
/** Bot biasanya mengirim form dalam < 3 detik setelah halaman dimuat. */
function formStamp() { $_SESSION['ft'] = time(); }
function tooFast() { return time() - (int)($_SESSION['ft'] ?? 0) < 3; }
/** Pemeriksaan pendaftaran akun; mengembalikan pesan error atau ''. */
function spamCheck($name, $email)
{
    if (post('website') !== '') return t('Request rejected.');
    if (tooFast()) return t('The form was submitted too quickly. Wait a few seconds and try again.');
    if (!validName($name)) return t('Names may only contain letters, spaces, periods, commas and hyphens (no numbers, links or symbols).');
    if (blockedEmail($email)) return t('Please use an institutional or permanent personal email address (temporary emails are not accepted).');
    if (!captchaOk()) return t('Security check (captcha) failed. Reload the page and try again.');
    return '';
}

/* ---------- email ---------- */
/** Alamat pengirim & reply-to. Dengan Resend, alamat pengirim harus memakai domain yang sudah diverifikasi di Resend. */
function mailFrom()
{
    $from = setting('mail_from');
    if (!filter_var($from, FILTER_VALIDATE_EMAIL)) $from = 'noreply@' . preg_replace('/^www\./', '', preg_replace('/:\d+$/', '', host()));
    $reply = filter_var(setting('email'), FILTER_VALIDATE_EMAIL) ? setting('email') : $from;
    return [$from, $reply];
}
function resendOn() { return setting('mail_driver') === 'resend' && setting('resend_key') !== ''; }
/** Panggil Resend API (https://resend.com/docs/api-reference). Mengembalikan array respons atau null + $err. */
function resendCall($path, $payload, &$err)
{
    $err = '';
    if (!function_exists('curl_init')) { $err = 'Ekstensi PHP cURL tidak aktif di hosting.'; return null; }
    $c = curl_init((defined('RESEND_API') ? RESEND_API : 'https://api.resend.com') . $path);
    curl_setopt_array($c, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . setting('resend_key'), 'Content-Type: application/json', 'User-Agent: conference-application/1.0'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    $raw = curl_exec($c); $code = (int)curl_getinfo($c, CURLINFO_HTTP_CODE); $cerr = curl_error($c); curl_close($c);
    if ($raw === false) { $err = 'Koneksi ke Resend gagal: ' . $cerr; return null; }
    $j = json_decode((string)$raw, true);
    if ($code >= 200 && $code < 300 && is_array($j)) return $j;
    $err = 'Resend HTTP ' . $code . ': ' . (is_array($j) && isset($j['message']) ? $j['message'] : mb_substr((string)$raw, 0, 200));
    return null;
}
function resendPayload($to, $subject, $body)
{
    [$from, $reply] = mailFrom();
    $name = trim(str_replace(['<', '>', '"', "\r", "\n"], '', setting('mail_from_name') ?: brand()));
    return ['from' => ($name !== '' ? $name . ' <' . $from . '>' : $from), 'to' => [$to], 'reply_to' => $reply, 'subject' => $subject, 'text' => $body];
}
function mailLog($to, $subject, $ok, $err = '', $prov = '')
{
    global $pdo;
    try { $pdo->prepare("INSERT INTO mail_log(to_addr,subject,ok,error,provider) VALUES(?,?,?,?,?)")->execute([$to, mb_substr($subject, 0, 250), $ok ? 1 : 0, $err !== '' ? mb_substr($err, 0, 250) : null, $prov]); } catch (Exception $x) {}
}
/** Kirim satu email teks. Memakai Resend bila diaktifkan di Pengaturan, selain itu mail() bawaan hosting. */
function sendMail($to, $subject, $body, &$err = null)
{
    $err = '';
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) { $err = 'Alamat email tidak valid.'; return false; }
    if (resendOn()) {
        $r = resendCall('/emails', resendPayload($to, $subject, $body), $err);
        $ok = !empty($r['id']);
        if ($r !== null && !$ok) $err = 'Respons Resend tidak berisi id email.';
        mailLog($to, $subject, $ok, $err, 'resend');
        return $ok;
    }
    [$from, $reply] = mailFrom();
    $h = "From: =?UTF-8?B?" . base64_encode(setting('mail_from_name') ?: brand()) . "?= <$from>\r\nReply-To: $reply\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit";
    $ok = function_exists('mail') ? @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $h, '-f' . $from) : false;
    if (!$ok) $err = 'Fungsi mail() hosting gagal mengirim.';
    mailLog($to, $subject, $ok, $err, 'mail');
    return $ok;
}
/** Kirim banyak email. Dengan Resend memakai endpoint batch (maks. 100 per permintaan). Mengembalikan [terkirim, total, error terakhir]. */
function sendBatch($msgs)
{
    $msgs = array_values(array_filter($msgs, function ($m) { return filter_var($m[0], FILTER_VALIDATE_EMAIL); }));
    @set_time_limit(600); $sent = 0; $last = '';
    if (!resendOn()) { foreach ($msgs as $m) { if (sendMail($m[0], $m[1], $m[2], $e)) $sent++; else $last = $e; } return [$sent, count($msgs), $last]; }
    foreach (array_chunk($msgs, 100) as $i => $chunk) {
        if ($i) usleep(600000); // batas laju Resend (±2 permintaan/detik)
        $r = resendCall('/emails/batch', array_map(function ($m) { return resendPayload($m[0], $m[1], $m[2]); }, $chunk), $err);
        $ok = $r !== null && isset($r['data']) && is_array($r['data']);
        if (!$ok) $last = $err ?: 'Respons batch Resend tidak dikenali.';
        foreach ($chunk as $m) mailLog($m[0], $m[1], $ok, $ok ? '' : $last, 'resend');
        if ($ok) $sent += count($chunk);
    }
    return [$sent, count($msgs), $last];
}
/** Kirim email berformat standar: salam + isi + tanda tangan panitia. */
function notify($to, $name, $subject, $text, $link = 'dashboard.php')
{
    $body = "Dear " . $name . ",\n\n" . trim($text) . "\n\n" . ($link === 'dashboard.php' ? "You can view your registration status at:" : "Open:") . "\n" . absUrl($link) . "\n\nBest regards,\nOrganizing Committee\n" . setting('name') . "\n" . (setting('email') ? setting('email') . "\n" : '');
    return sendMail($to, '[' . brand() . '] ' . $subject, $body);
}

/* ---------- konten ---------- */
function items($kind)
{
    global $pdo;
    $order = $kind === 'date' ? 'extra,sort,id' : ($kind === 'news' ? 'created_at DESC,id DESC' : 'sort,id');
    $s = $pdo->prepare("SELECT * FROM items WHERE kind=? ORDER BY $order"); $s->execute([$kind]);
    return $s->fetchAll();
}
function fees()
{
    $o = [];
    foreach (rows('fees') as $r) { $r = array_pad($r, 5, ''); $o[] = ['cat' => $r[0], 'idr' => $r[1], 'usd' => $r[2], 'eidr' => $r[3], 'eusd' => $r[4]]; }
    return $o;
}
function earlyOn() { $d = setting('early_deadline'); return $d !== '' && date('Y-m-d') <= $d; }
function hasEarly() { foreach (fees() as $f) if ($f['eidr'] !== '' || $f['eusd'] !== '') return true; return false; }
/** Biaya yang berlaku hari ini: [IDR, USD, apakah early bird]. */
function feeNow($f)
{
    $e = earlyOn() && ($f['eidr'] !== '' || $f['eusd'] !== '');
    return [$e && $f['eidr'] !== '' ? $f['eidr'] : $f['idr'], $e && $f['eusd'] !== '' ? $f['eusd'] : $f['usd'], $e];
}
function feeText($f) { [$i, $u] = feeNow($f); return trim($i . ($u !== '' ? ' / ' . $u : '')); }
function isPres($c) { return stripos((string)$c, 'presenter') !== false; }
function modeOf($c) { return stripos((string)$c, 'online') !== false ? 'Online' : (stripos((string)$c, 'offline') !== false ? 'Offline' : ''); }
function regOpen() { return setting('reg_open', '1') === '1'; }
/** Sertifikat bisa diunduh bila: pembayaran terverifikasi, admin membuka sertifikat, dan (online / sudah check-in / check-in tidak diwajibkan). */
function certReady($R)
{
    return $R && $R['pay_status'] === 'verified' && setting('cert_open') === '1'
        && ($R['attendance'] === 'Online' || setting('cert_checkin', '1') !== '1' || !empty($R['checked_in_at']));
}

/* ---------- unggahan file ---------- */
function magicOk($p, $ext)
{
    $h = bin2hex((string)file_get_contents($p, false, null, 0, 8));
    $m = ['pdf' => ['25504446'], 'docx' => ['504b0304'], 'pptx' => ['504b0304'], 'xlsx' => ['504b0304'], 'zip' => ['504b0304'], 'doc' => ['d0cf11e0'], 'ppt' => ['d0cf11e0'], 'jpg' => ['ffd8ff'], 'jpeg' => ['ffd8ff'], 'png' => ['89504e47']];
    foreach ($m[$ext] ?? [] as $x) if (strpos($h, $x) === 0) return true;
    return false;
}
function upFile($f, $exts, &$err, $label, $public = false)
{
    $err = '';
    if (!$f || ($f['error'] ?? 4) === 4) { $err = t('%s: no file selected.', $label); return null; }
    if (in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) { $err = t('%s: the file exceeds the server upload limit (%s).', $label, ini_get('upload_max_filesize')); return null; }
    if ($f['error'] !== 0) { $err = t('%s: upload failed (the file may exceed the server limit).', $label); return null; }
    if ($f['size'] > 10485760) { $err = t('%s: maximum size is 10 MB.', $label); return null; }
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $exts, true) || !magicOk($f['tmp_name'], $ext)) { $err = t('%s: the format must be %s.', $label, strtoupper(implode('/', $exts))); return null; }
    $d = __DIR__ . ($public ? '/uploads/' : '/uploads/private/');
    if (!is_dir($d)) { @mkdir($d, 0755, true); if (!$public) @file_put_contents($d . '.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n"); }
    $fn = ($public ? 'dl_' : '') . bin2hex(random_bytes(10)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $d . $fn)) { $err = t('%s: could not be saved (check the uploads folder permissions).', $label); return null; }
    if ($ext === 'docx' && !$public) stripDocx($d . $fn); // buang font tertanam & perkecil PNG besar
    return [$fn, substr(basename($f['name']), 0, 190)];
}
/**
 * Bersihkan .docx: buang font tertanam dan perkecil PNG yang jauh di atas ukuran tampil (500 dpi),
 * memakai DocxFontStripper (dari plugin OJS docx-font-stripper-ojs33). File asli dipertahankan bila
 * hasilnya gagal divalidasi; unggahan tidak pernah gagal karena langkah ini.
 */
function stripDocx($path)
{
    if (setting('docx_strip', '1') !== '1' || !class_exists('ZipArchive') || !class_exists('DOMDocument')) return null;
    try {
        require_once __DIR__ . '/lib/DocxFontStripper.php';
        $r = (new DocxFontStripper(['downscaleImages' => true, 'imageTargetDpi' => 500]))->stripInPlace($path);
        if ($r->status !== DocxFontStripResult::UNCHANGED) error_log('[DocxFontStripper] ' . basename($path) . ': ' . $r->summary());
        if ($r->isStripped()) {
            setSetting('docx_strip_count', (int)setting('docx_strip_count', '0') + 1);
            setSetting('docx_strip_saved', (int)setting('docx_strip_saved', '0') + max(0, $r->sizeBefore - $r->sizeAfter));
        }
        return $r;
    } catch (Throwable $x) { error_log('[DocxFontStripper] ' . basename($path) . ': ' . $x->getMessage()); return null; }
}
function rmPriv($fn) { if ($fn) @unlink(__DIR__ . '/uploads/private/' . basename($fn)); }
/** Gambar publik (foto pembicara, logo sponsor). */
function upImage($f, &$err, $prefix = 'img', $maxMb = 8, $optimize = true)
{
    $err = '';
    if ($f && in_array($f['error'] ?? 0, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) { $err = 'Gagal: file melebihi batas unggah server (upload_max_filesize = ' . ini_get('upload_max_filesize') . '). Naikkan batasnya di cPanel → MultiPHP INI Editor (mis. 16M), atau perkecil file.'; return ''; }
    if (!$f || $f['error'] !== 0 || $f['size'] > $maxMb * 1048576) { $err = 'Gagal: gunakan JPG/PNG/WEBP maksimal ' . $maxMb . ' MB.'; return ''; }
    $im = @getimagesize($f['tmp_name']);
    $ext = $im ? ([IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$im[2]] ?? null) : null;
    if (!$ext) { $err = 'Gagal: file bukan gambar JPG/PNG/WEBP.'; return ''; }
    $d = __DIR__ . '/uploads/'; if (!is_dir($d)) mkdir($d, 0755, true);
    $fn = $prefix . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $d . $fn)) { $err = 'Gagal menyimpan file (cek izin folder uploads).'; return ''; }
    // perkecil & kompres otomatis (foto pembicara, logo, dll.) agar halaman ringan
    return $optimize ? optimizeImage($d . $fn, imgMax($prefix)) : $fn;
}
function rmImage($fn) { if ($fn && !isurl($fn)) @unlink(__DIR__ . '/uploads/' . basename($fn)); }
function imgSrc($v) { return isurl($v) ? $v : BASE . 'uploads/' . rawurlencode(basename((string)$v)); }

/* ---------- ekspor CSV (aman untuk Excel) ---------- */
function csvOut($name, $head, $rows)
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $name);
    $o = fopen('php://output', 'w');
    fwrite($o, "\xEF\xBB\xBF");
    fputcsv($o, $head);
    foreach ($rows as $r) {
        $r = array_map(function ($v) { $v = (string)$v; return preg_match('/^[=@\t\r]|^[+\-](?![\d\s().\-]*$)/', $v) ? "'" . $v : $v; }, array_values($r));
        fputcsv($o, $r);
    }
    exit;
}

/* ---------- layout ---------- */
function head($t, $extra = '')
{
    $u = user(); $H = BASE; $L = lang(); $desc = setting('tagline') ?: setting('name');
    if (!$u) { formStamp(); if (captchaOn()) $extra .= '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>'; }
    ?><!DOCTYPE html><html lang="<?= $L ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($t) ?> - <?= e(brand()) ?></title>
<meta name="description" content="<?= e(mb_substr($desc, 0, 300)) ?>">
<meta property="og:type" content="website"><meta property="og:title" content="<?= e(setting('name')) ?>"><meta property="og:description" content="<?= e(mb_substr($desc, 0, 300)) ?>"><meta property="og:url" content="<?= e(absUrl()) ?>"><meta property="og:locale" content="<?= $L === 'id' ? 'id_ID' : 'en_US' ?>">
<?php foreach (array_keys(LANGS) as $l): ?><link rel="alternate" hreflang="<?= $l ?>" href="<?= e(absUrl(ltrim(substr(langUrl($l), strlen(BASE)), '/'))) ?>"><?php endforeach; ?>
<?php $ogi = setting('poster') ?: setting('logo_url'); if ($ogi): $ogu = isurl($ogi) ? $ogi : absUrl('uploads/' . rawurlencode(basename($ogi))); ?><meta property="og:image" content="<?= e($ogu) ?>"><meta name="twitter:card" content="<?= setting('poster') ? 'summary_large_image' : 'summary' ?>"><meta name="twitter:image" content="<?= e($ogu) ?>"><?php endif; ?>
<?php if (setting('logo_url')): ?><link rel="icon" href="<?= e(imgSrc(setting('logo_url'))) ?>"><?php endif; ?>
<meta name="theme-color" content="#1d3a3a">
<link rel="stylesheet" href="<?= $H ?>style.css?v=<?= @filemtime(__DIR__ . "/style.css") ?>"><?= $extra ?></head><body id="top"<?= defined('ADMIN') ? ' class="adm"' : '' ?>>
<a class="skip" href="#main"><?= te('Skip to content') ?></a>
<nav><a class="brand" href="<?= $H ?>"><?php if (setting('logo_url')): ?><img src="<?= e(imgSrc(setting('logo_url'))) ?>" alt=""><?php endif; ?><?= e(brand()) ?></a><button class="burger" type="button" aria-label="Menu" aria-expanded="false" onclick="var n=this.parentNode;n.classList.toggle('open');this.setAttribute('aria-expanded',n.classList.contains('open'))">&#9776;</button><div class="links">
<a href="<?= $H ?>#about"><?= te('About') ?></a><a href="<?= $H ?>#speakers"><?= te('Speakers') ?></a><a href="<?= $H ?>#cfp"><?= te('Call for Papers') ?></a><a href="<?= $H ?>#program"><?= te('Program') ?></a><a href="<?= $H ?>#register"><?= te('Registration') ?></a><a href="<?= $H ?>#venue"><?= te('Venue') ?></a><?php if (setting('journals')): ?><a href="<?= $H ?>#publication"><?= te('Publication') ?></a><?php endif; ?><a href="<?= $H ?>#contact"><?= te('Contact') ?></a><?php if (isurl(setting('submission_url'))): ?><a href="<?= e(setting('submission_url')) ?>" target="_blank" rel="noopener"><?= te('Make Submission') ?></a><?php endif; ?>
<?php if ($u): ?><?php if ($u['role'] === 'admin'): ?><a href="<?= $H ?>admin/">Admin</a><?php elseif ($u['role'] === 'reviewer'): ?><a href="<?= $H ?>reviewer.php"><?= te('Review') ?></a><?php else: ?><a href="<?= $H ?>dashboard.php"><?= te('Dashboard') ?></a><?php endif; ?><?php $nn = unreadCount($u['id']); ?><a href="<?= $H ?>notifikasi.php" class="bell" aria-label="<?= te('Notifications') ?><?= $nn ? ' (' . te('%d unread', $nn) . ')' : '' ?>"><svg class="ic" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg><span class="bl"><?= te('Notifications') ?></span><?php if ($nn): ?><b><?= $nn > 99 ? '99+' : $nn ?></b><?php endif; ?></a><a href="<?= $H ?>logout.php?t=<?= token() ?>"><?= te('Log out') ?> (<?= e(mb_strimwidth($u['name'], 0, 20, '…')) ?>)</a>
<?php else: ?><a href="<?= $H ?>login.php"><?= te('Log in') ?></a><a class="btn" href="<?= $H ?>register.php"><?= te('Register') ?></a><?php endif; ?>
<span class="langsw" role="group" aria-label="Language / Bahasa"><?php foreach (LANGS as $l => $n): ?><a href="<?= e(langUrl($l)) ?>" hreflang="<?= $l ?>" lang="<?= $l ?>" title="<?= e($n) ?>"<?= $l === $L ? ' class="on" aria-current="true"' : '' ?>><?= strtoupper($l) ?></a><?php endforeach; ?></span></div></nav>
<?php if (!$u): ?><div id="logm" class="modal" hidden><div class="mbox" role="dialog" aria-modal="true" aria-labelledby="logmt"><button type="button" class="mx" aria-label="<?= te('Close') ?>" onclick="document.getElementById('logm').hidden=true">&times;</button>
<h3 id="logmt"><?= te('Log in') ?></h3><p><small><?= te('Participants, reviewers and the committee log in here.') ?></small></p>
<p class="msg err merr" hidden></p>
<form method="post" action="<?= $H ?>login.php"><?= csrf() ?>
<label><?= te('Email') ?><input type="email" name="email" required autocomplete="email"></label>
<label><?= te('Password') ?><input type="password" name="password" required autocomplete="current-password"></label>
<button class="btn"><?= te('Log in') ?></button><p><small><a href="<?= $H ?>forgot.php"><?= te('Forgot password?') ?></a><?php if (regOpen()): ?> &middot; <?= te('No account yet?') ?> <a href="<?= $H ?>register.php"><?= te('Register') ?></a><?php endif; ?></small></p></form></div></div><?php endif; ?>
<?php if (!$u && regOpen()): ?><div id="regm" class="modal" hidden><div class="mbox" role="dialog" aria-modal="true" aria-labelledby="regmt"><button type="button" class="mx" aria-label="<?= te('Close') ?>" onclick="document.getElementById('regm').hidden=true">&times;</button>
<h3 id="regmt"><?= te('Create Participant Account') ?></h3><p><small><?= te('Step 1 of 2. After creating your account you will be taken straight to the conference registration form.') ?></small></p>
<p class="msg err merr" hidden></p>
<form method="post" action="<?= $H ?>register.php"><?= csrf() ?>
<label><?= te('Full name') ?><input name="name" required autocomplete="name"></label>
<label><?= te('Email') ?><input type="email" name="email" required autocomplete="email"></label>
<label><?= te('Password (min. 8 characters)') ?><input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
<input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true"><?= captchaHtml() ?>
<button class="btn"><?= te('Create Account & Continue') ?></button><p><small><?= te('Already have an account?') ?> <a href="<?= $H ?>login.php"><?= te('Log in') ?></a></small></p></form></div></div><?php endif; ?><main id="main">
<?php if ($f = flash()): ?><p class="msg" role="status"><?= e($f) ?></p><?php endif;
}

function foot()
{
    $H = BASE; $em = setting('email'); ?></main><footer><div class="fgrid">
<div><b><?= e(setting('name')) ?></b><p><?= e(setting('tagline')) ?></p><?php if (setting('organizer')): ?><p><small><?= te('Organized by') ?> <?= e(tx(setting('organizer'))) ?></small></p><?php endif; ?></div>
<div><h4><?= te('Links') ?></h4><a href="<?= $H ?>#about"><?= te('About') ?></a><a href="<?= $H ?>#dates"><?= te('Important Dates') ?></a><a href="<?= $H ?>#program"><?= te('Program') ?></a><a href="<?= $H ?>#register"><?= te('Registration') ?></a><a href="<?= $H ?>#faq">FAQ</a><a href="<?= $H ?>verify.php"><?= te('Verify Certificate') ?></a><?php if (setting('event_start')): ?><a href="<?= $H ?>calendar.php"><?= te('Add to Calendar') ?></a><?php endif; ?></div>
<div><h4><?= te('Contact') ?></h4><p><?= e(tx(setting('secretariat'))) ?></p><?php if ($em): ?><p><a href="mailto:<?= e($em) ?>"><?= e($em) ?></a></p><?php endif; ?></div></div>
<div class="copy">&copy; <?= date('Y') ?> <?= e(setting('name')) ?></div></footer><a href="#top" class="totop" aria-label="<?= te('Back to top') ?>">&uarr;</a>
<script>
(function(){var l=document.querySelectorAll('.fade');if(!('IntersectionObserver' in window)){l.forEach(function(e){e.classList.add('in')});return}var o=new IntersectionObserver(function(a){a.forEach(function(x){if(x.isIntersecting){x.target.classList.add('in');o.unobserve(x.target)}})},{threshold:.08});l.forEach(function(e){o.observe(e)})})();
/* tab hari pada agenda */
document.querySelectorAll('.daytabs').forEach(function(w){var b=w.querySelectorAll('[data-day]'),p=w.querySelectorAll('.daypane');b.forEach(function(x){x.addEventListener('click',function(){b.forEach(function(y){y.classList.toggle('on',y===x);y.setAttribute('aria-selected',y===x)});p.forEach(function(q){q.hidden=q.dataset.pane!==x.dataset.day})})})});
/* hitung mundur */
document.querySelectorAll('[data-countdown]').forEach(function(c){var t=Date.parse(c.dataset.countdown);if(isNaN(t))return;function u(){var d=Math.max(0,t-Date.now())/1000;if(d<=0){c.innerHTML='<b>'+<?= json_encode(t('The event has started!')) ?>+'</b>';return}var v=[Math.floor(d/86400),Math.floor(d%86400/3600),Math.floor(d%3600/60),Math.floor(d%60)];c.querySelectorAll('b').forEach(function(b,i){b.textContent=v[i]})}u();setInterval(u,1000)});
</script>
<script>(function(){var m=document.getElementById('regm');if(!m)return;
var op=function(e){if(/register\.php$/.test(location.pathname))return;e.preventDefault();m.hidden=false;var i=m.querySelector('input[name=name]');if(i)i.focus()};
document.querySelectorAll('a[href$="register.php"]').forEach(function(a){a.addEventListener('click',op)});
m.addEventListener('click',function(e){if(e.target===m)m.hidden=true});
document.addEventListener('keydown',function(e){if(e.key==='Escape')m.hidden=true});
var f=m.querySelector('form'),er=m.querySelector('.merr');
f.addEventListener('submit',function(e){e.preventDefault();er.hidden=true;var b=f.querySelector('button.btn');b.disabled=true;
fetch(f.action,{method:'POST',body:new FormData(f),headers:{'X-Requested-With':'fetch'},credentials:'same-origin'}).then(function(r){return r.json()}).then(function(j){if(j.ok){location.href=j.to}else{er.textContent=j.error||<?= json_encode(t('Could not create the account.')) ?>;er.hidden=false;b.disabled=false;if(window.turnstile)turnstile.reset()}}).catch(function(){er.textContent=<?= json_encode(t('Something went wrong. Reload the page and try again.')) ?>;er.hidden=false;b.disabled=false})})})();</script>
<script>(function(){var m=document.getElementById('logm');if(!m)return;var r=document.getElementById('regm');
function show(e){if(/login\.php$/.test(location.pathname))return;e.preventDefault();if(r)r.hidden=true;m.hidden=false;var i=m.querySelector('input[name=email]');if(i)i.focus()}
document.querySelectorAll('a[href$="login.php"]').forEach(function(a){a.addEventListener('click',show)});
document.querySelectorAll('a[href$="register.php"]').forEach(function(a){a.addEventListener('click',function(){m.hidden=true})});
m.addEventListener('click',function(e){if(e.target===m)m.hidden=true});document.addEventListener('keydown',function(e){if(e.key==='Escape')m.hidden=true});
var f=m.querySelector('form'),er=m.querySelector('.merr');
f.addEventListener('submit',function(e){e.preventDefault();er.hidden=true;var b=f.querySelector('button.btn');b.disabled=true;
fetch(f.action,{method:'POST',body:new FormData(f),headers:{'X-Requested-With':'fetch'},credentials:'same-origin'}).then(function(x){return x.json()}).then(function(j){if(j.ok){location.href=j.to}else{er.textContent=j.error||<?= json_encode(t('Log in failed.')) ?>;er.hidden=false;b.disabled=false}}).catch(function(){er.textContent=<?= json_encode(t('Something went wrong. Reload the page and try again.')) ?>;er.hidden=false;b.disabled=false})})})();</script>
<?php $adm = defined('ADMIN'); ?><div id="cfm" class="modal" hidden><div class="mbox cbox" role="alertdialog" aria-modal="true" aria-labelledby="cfmt" aria-describedby="cfmm">
<div class="cic" aria-hidden="true"><svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg></div>
<h3 id="cfmt"><?= $adm ? 'Konfirmasi' : te('Confirm') ?></h3><p id="cfmm"></p>
<div class="cact"><button type="button" class="btn ghost2" id="cfmn"><?= $adm ? 'Batal' : te('Cancel') ?></button><button type="button" class="btn" id="cfmy" data-ok="<?= $adm ? 'Ya, lanjutkan' : te('Yes, continue') ?>" data-del="<?= $adm ? 'Ya, hapus' : te('Yes, delete') ?>"></button></div></div></div>
<script>(function(){var m=document.getElementById('cfm'),t=document.getElementById('cfmm'),y=document.getElementById('cfmy'),n=document.getElementById('cfmn'),f=null,last=null;
document.addEventListener('submit',function(e){var x=e.target;if(!x.dataset||!x.dataset.confirm)return;if(x.dataset.go==='1'){x.dataset.go='';return}
e.preventDefault();e.stopImmediatePropagation();f=x;last=document.activeElement;t.textContent=x.dataset.confirm;var d=/^(hapus|delete|reset|remove)/i.test(x.dataset.confirm);
y.classList.toggle('danger',d);y.textContent=d?y.dataset.del:y.dataset.ok;m.hidden=false;y.focus()},true);
function close(){m.hidden=true;f=null;if(last&&last.focus)last.focus()}
n.onclick=close;m.addEventListener('click',function(e){if(e.target===m)close()});document.addEventListener('keydown',function(e){if(!m.hidden&&e.key==='Escape')close()});
y.onclick=function(){var x=f;m.hidden=true;f=null;if(!x)return;x.dataset.go='1';if(x.requestSubmit)x.requestSubmit();else x.submit()}})();</script>
<div id="docm" class="modal docm" hidden><div class="dbox" role="dialog" aria-modal="true" aria-labelledby="docmt">
<div class="dbar"><b id="docmt"><?= te('Preview') ?></b><span><button type="button" class="btn" id="docmp"><?= te('Print / Save PDF') ?></button> <a class="btn ghost2" id="docmo" href="#" target="_blank" rel="noopener"><?= te('Open in new tab') ?></a> <button type="button" class="mx" id="docmx" aria-label="<?= te('Close') ?>">&times;</button></span></div>
<iframe id="docmf" title="<?= te('Document preview') ?>" src="about:blank"></iframe></div></div>
<script>(function(){var m=document.getElementById('docm'),f=document.getElementById('docmf'),o=document.getElementById('docmo'),t=document.getElementById('docmt');
window.openDoc=function(url,title){f.src=url;o.href=url;t.textContent=title||<?= json_encode(t('Preview')) ?>;m.hidden=false;document.body.style.overflow='hidden'};
function close(){m.hidden=true;f.src='about:blank';document.body.style.overflow=''}
document.getElementById('docmx').onclick=close;m.addEventListener('click',function(e){if(e.target===m)close()});document.addEventListener('keydown',function(e){if(e.key==='Escape'&&!m.hidden)close()});
document.getElementById('docmp').onclick=function(){try{f.contentWindow.focus();f.contentWindow.print()}catch(x){window.open(f.src)}};
document.addEventListener('click',function(e){var a=e.target.closest('a.js-doc');if(!a||e.ctrlKey||e.metaKey||e.shiftKey)return;e.preventDefault();openDoc(a.href,a.dataset.title||a.textContent.trim())})})();</script></body></html><?php
}

/** Semua sesi agenda, diurutkan alami (Day 2 sebelum Day 10), lalu waktu. */
function agendaRows()
{
    global $pdo;
    $r = $pdo->query("SELECT * FROM agenda")->fetchAll();
    usort($r, function ($a, $b) { return strnatcasecmp($a['day'], $b['day']) ?: strnatcasecmp($a['time'], $b['time']) ?: $a['id'] <=> $b['id']; });
    return $r;
}
/** Pembersihan harian: log email > 180 hari, notifikasi terbaca > 120 hari, data pembatas percobaan > 1 hari. */
function housekeeping()
{
    global $pdo;
    if (setting('housekeep_at') === date('Y-m-d')) return;
    try {
        $pdo->exec("DELETE FROM mail_log WHERE created_at < NOW() - INTERVAL 180 DAY");
        $pdo->exec("DELETE FROM notifications WHERE is_read=1 AND created_at < NOW() - INTERVAL 120 DAY");
        $pdo->prepare("DELETE FROM throttle WHERE ts < ?")->execute([time() - 86400]);
        foreach (glob(__DIR__ . '/uploads/private/*.part') ?: [] as $f) if (filemtime($f) < time() - 86400) @unlink($f);
    } catch (Exception $x) { error_log('housekeeping: ' . $x->getMessage()); }
    setSetting('housekeep_at', date('Y-m-d'));
}
/** Jadwal acara, dikelompokkan per hari dengan tab. */
function agenda()
{
    global $pdo;
    $r = agendaRows();
    if (!$r) { echo '<p class="center">' . te('The program will be announced soon.') . '</p>'; return; }
    $G = []; foreach ($r as $a) $G[$a['day']][] = $a;
    $multi = count($G) > 1; $i = 0;
    echo '<div class="daytabs">';
    if ($multi) { echo '<div class="tabs" role="tablist">'; foreach (array_keys($G) as $k => $d) echo '<button type="button" role="tab" class="tab' . ($k ? '' : ' on') . '" aria-selected="' . ($k ? 'false' : 'true') . '" data-day="d' . $k . '">' . e(tx($d)) . '</button>'; echo '</div>'; }
    foreach ($G as $d => $list) {
        echo '<div class="daypane" data-pane="d' . $i . '"' . ($i && $multi ? ' hidden' : '') . '>' . ($multi ? '' : '<h3>' . e(tx($d)) . '</h3>');
        echo '<div class="scroll"><table class="prog"><tr><th>' . te('Time') . '</th><th>' . te('Session') . '</th><th>' . te('Speaker') . '</th><th>' . te('Room / Track') . '</th></tr>';
        foreach ($list as $a) echo '<tr><td class="nowrap">' . e($a['time']) . '</td><td><b>' . e(tx($a['title'])) . '</b>' . (!empty($a['description']) ? '<br><small>' . nl2br(e(tx($a['description']))) . '</small>' : '') . '</td><td>' . e($a['speaker']) . '</td><td>' . e(trim($a['room'] . ((!empty($a['track']) && $a['room']) ? ' · ' : '') . tx($a['track'] ?? ''))) . '</td></tr>';
        echo '</table></div></div>'; $i++;
    }
    echo '</div>';
}
