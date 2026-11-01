<?php
/* ============================================================
 * Bootstrap aplikasi: konfigurasi, sesi, database, helper, layout.
 * ============================================================ */
if (!file_exists(__DIR__ . '/config.php')) { header('Location: install/'); exit; }
require __DIR__ . '/config.php';
require_once __DIR__ . '/lib/schema.php';
require_once __DIR__ . '/lib/paper.php';

$HTTPS = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? '') == '443' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['lifetime' => 0, 'path' => BASE, 'httponly' => true, 'samesite' => 'Lax', 'secure' => $HTTPS]);
    session_start();
}
if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
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

/* ---------- utilitas umum ---------- */
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function token() { if (empty($_SESSION['t'])) $_SESSION['t'] = bin2hex(random_bytes(16)); return $_SESSION['t']; }
function csrf() { return '<input type="hidden" name="t" value="' . token() . '">'; }
function chk() { if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals($_SESSION['t'] ?? '', (string)($_POST['t'] ?? ''))) { http_response_code(400); die('Token tidak valid, muat ulang halaman.'); } }
function post($k) { return trim((string)($_POST[$k] ?? '')); }
function flash($m = null) { if ($m !== null) { $_SESSION['f'] = $m; return; } $f = $_SESSION['f'] ?? ''; unset($_SESSION['f']); return $f; }
function go($to) { header('Location: ' . $to); exit; }
function brand() { $n = setting('name'); return preg_match('/\(([^)]+)\)/', $n, $m) ? $m[1] : $n; }
function isurl($v) { return (bool)preg_match('#^https?://#i', (string)$v); }
function words($s) { return count(preg_split('/\s+/u', trim((string)$s), -1, PREG_SPLIT_NO_EMPTY)); }
function lbl($map, $k) { return $map[$k] ?? $k; }
function no($p, $id) { return $p . str_pad((string)$id, 4, '0', STR_PAD_LEFT); }
function initials($n) { $w = preg_split('/[\s,]+/', (string)$n, -1, PREG_SPLIT_NO_EMPTY); $o = ''; foreach ($w as $x) { if (strpos($x, '.') !== false) continue; $o .= mb_strtoupper(mb_substr($x, 0, 1)); if (mb_strlen($o) >= 2) break; } return $o !== '' ? $o : '?'; }
function host() { return preg_replace('/[^A-Za-z0-9.:-]/', '', $_SERVER['HTTP_HOST'] ?? 'localhost'); }
function absUrl($p = '') { global $HTTPS; return ($HTTPS ? 'https' : 'http') . '://' . host() . BASE . ltrim($p, '/'); }
function fdate($d) { if (!$d) return ''; $t = strtotime($d); if (!$t) return $d; $m = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember']; return date('j', $t) . ' ' . $m[(int)date('n', $t)] . ' ' . date('Y', $t); }
function fdateEn($d) { $t = strtotime((string)$d); return $t ? date('F j, Y', $t) : (string)$d; }

$PAY = ['unpaid' => 'Belum bayar', 'pending' => 'Menunggu verifikasi', 'verified' => 'Terverifikasi', 'rejected' => 'Ditolak'];
$PAPER = ['submitted' => 'Diterima sistem', 'under_review' => 'Dalam review', 'revision' => 'Perlu revisi', 'accepted' => 'Diterima', 'rejected' => 'Ditolak'];
$REC = ['accept' => 'Accept', 'minor' => 'Minor revision', 'major' => 'Major revision', 'reject' => 'Reject'];
$ULBL = ['pending' => 'Menunggu konfirmasi', 'confirmed' => 'Terkonfirmasi', 'rejected' => 'Ditolak'];

/* ---------- pengguna & akses ---------- */
function user()
{
    global $pdo; static $u = false;
    if (empty($_SESSION['uid'])) return null;
    if ($u === false || (int)$u['id'] !== (int)$_SESSION['uid']) { $s = $pdo->prepare("SELECT * FROM users WHERE id=?"); $s->execute([$_SESSION['uid']]); $u = $s->fetch() ?: null; }
    return $u;
}
function need($roles = null)
{
    $u = user();
    if (!$u) { $_SESSION['back'] = $_SERVER['REQUEST_URI'] ?? ''; go(BASE . 'login.php'); }
    if ($roles && !in_array($u['role'], (array)$roles, true)) { http_response_code(403); die('Akses ditolak'); }
    return $u;
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
    if (post('website') !== '') return 'Permintaan ditolak.';
    if (tooFast()) return 'Formulir dikirim terlalu cepat. Tunggu beberapa detik lalu coba lagi.';
    if (!validName($name)) return 'Nama hanya boleh berisi huruf, spasi, titik, koma, dan tanda hubung (tanpa angka, tautan, atau simbol).';
    if (blockedEmail($email)) return 'Gunakan alamat email institusi atau email pribadi permanen (email sementara tidak diterima).';
    if (!captchaOk()) return 'Verifikasi keamanan (captcha) gagal. Muat ulang halaman lalu coba lagi.';
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
function notify($to, $name, $subject, $text)
{
    $body = "Dear " . $name . ",\n\n" . trim($text) . "\n\nYou can view your registration status at:\n" . absUrl('dashboard.php') . "\n\nBest regards,\nOrganizing Committee\n" . setting('name') . "\n" . (setting('email') ? setting('email') . "\n" : '');
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
    if (!$f || ($f['error'] ?? 4) === 4) { $err = "$label: file belum dipilih."; return null; }
    if ($f['error'] !== 0) { $err = "$label: gagal mengunggah (file mungkin melebihi batas server)."; return null; }
    if ($f['size'] > 10485760) { $err = "$label: ukuran maksimal 10 MB."; return null; }
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $exts, true) || !magicOk($f['tmp_name'], $ext)) { $err = "$label: format harus " . strtoupper(implode('/', $exts)) . '.'; return null; }
    $d = __DIR__ . ($public ? '/uploads/' : '/uploads/private/');
    if (!is_dir($d)) { @mkdir($d, 0755, true); if (!$public) @file_put_contents($d . '.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n"); }
    $fn = ($public ? 'dl_' : '') . bin2hex(random_bytes(10)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $d . $fn)) { $err = "$label: gagal menyimpan (cek izin folder uploads)."; return null; }
    return [$fn, substr(basename($f['name']), 0, 190)];
}
function rmPriv($fn) { if ($fn) @unlink(__DIR__ . '/uploads/private/' . basename($fn)); }
/** Gambar publik (foto pembicara, logo sponsor). */
function upImage($f, &$err, $prefix = 'img')
{
    $err = '';
    if (!$f || $f['error'] !== 0 || $f['size'] > 2097152) { $err = 'Gagal: gunakan JPG/PNG/WEBP maksimal 2 MB.'; return ''; }
    $im = @getimagesize($f['tmp_name']);
    $ext = $im ? ([IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$im[2]] ?? null) : null;
    if (!$ext) { $err = 'Gagal: file bukan gambar JPG/PNG/WEBP.'; return ''; }
    $d = __DIR__ . '/uploads/'; if (!is_dir($d)) mkdir($d, 0755, true);
    $fn = $prefix . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $d . $fn)) { $err = 'Gagal menyimpan file (cek izin folder uploads).'; return ''; }
    return $fn;
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
    $u = user(); $H = BASE; $desc = setting('tagline') ?: setting('name');
    if (!$u) { formStamp(); if (captchaOn()) $extra .= '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>'; }
    ?><!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($t) ?> - <?= e(brand()) ?></title>
<meta name="description" content="<?= e(mb_substr($desc, 0, 300)) ?>">
<meta property="og:type" content="website"><meta property="og:title" content="<?= e(setting('name')) ?>"><meta property="og:description" content="<?= e(mb_substr($desc, 0, 300)) ?>"><meta property="og:url" content="<?= e(absUrl()) ?>">
<?php if (setting('logo_url')): ?><meta property="og:image" content="<?= e(imgSrc(setting('logo_url'))) ?>"><link rel="icon" href="<?= e(imgSrc(setting('logo_url'))) ?>"><?php endif; ?>
<meta name="theme-color" content="#1d3a3a">
<link rel="stylesheet" href="<?= $H ?>style.css?v=<?= @filemtime(__DIR__ . "/style.css") ?>"><?= $extra ?></head><body id="top">
<a class="skip" href="#main">Lewati ke konten</a>
<nav><a class="brand" href="<?= $H ?>"><?php if (setting('logo_url')): ?><img src="<?= e(imgSrc(setting('logo_url'))) ?>" alt=""><?php endif; ?><?= e(brand()) ?></a><button class="burger" type="button" aria-label="Menu" aria-expanded="false" onclick="var n=this.parentNode;n.classList.toggle('open');this.setAttribute('aria-expanded',n.classList.contains('open'))">&#9776;</button><div class="links">
<a href="<?= $H ?>#about">About</a><a href="<?= $H ?>#speakers">Speakers</a><a href="<?= $H ?>#cfp">Call for Papers</a><a href="<?= $H ?>#program">Program</a><a href="<?= $H ?>#register">Registration</a><a href="<?= $H ?>#venue">Venue</a><?php if (setting('journals')): ?><a href="<?= $H ?>#publication">Publication</a><?php endif; ?><a href="<?= $H ?>#contact">Contact</a><?php if (setting('submission_url')): ?><a href="<?= e(setting('submission_url')) ?>" target="_blank" rel="noopener">Make Submission</a><?php endif; ?>
<?php if ($u): ?><?php if ($u['role'] === 'admin'): ?><a href="<?= $H ?>admin/">Admin</a><?php elseif ($u['role'] === 'reviewer'): ?><a href="<?= $H ?>reviewer.php">Review</a><?php else: ?><a href="<?= $H ?>dashboard.php">Dashboard</a><?php endif; ?><?php $nn = unreadCount($u['id']); ?><a href="<?= $H ?>notifikasi.php" class="bell" aria-label="Notifikasi<?= $nn ? ' (' . $nn . ' belum dibaca)' : '' ?>">&#128276;<?php if ($nn): ?><b><?= $nn > 99 ? '99+' : $nn ?></b><?php endif; ?></a><a href="<?= $H ?>logout.php?t=<?= token() ?>">Keluar (<?= e(mb_strimwidth($u['name'], 0, 20, '…')) ?>)</a>
<?php else: ?><a href="<?= $H ?>login.php">Masuk</a><a class="btn" href="<?= $H ?>register.php">Daftar</a><?php endif; ?></div></nav>
<?php if (!$u && regOpen()): ?><div id="regm" class="modal" hidden><div class="mbox" role="dialog" aria-modal="true" aria-labelledby="regmt"><button type="button" class="mx" aria-label="Tutup" onclick="document.getElementById('regm').hidden=true">&times;</button>
<h3 id="regmt">Buat Akun Peserta</h3><p><small>Langkah 1 dari 2. Setelah akun dibuat, Anda langsung diarahkan ke formulir pendaftaran konferensi.</small></p>
<p class="msg err merr" hidden></p>
<form method="post" action="<?= $H ?>register.php"><?= csrf() ?>
<label>Nama Lengkap<input name="name" required autocomplete="name"></label>
<label>Email<input type="email" name="email" required autocomplete="email"></label>
<label>Password (min. 8 karakter)<input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
<input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true"><?= captchaHtml() ?>
<button class="btn">Buat Akun &amp; Lanjutkan</button><p><small>Sudah punya akun? <a href="<?= $H ?>login.php">Masuk</a></small></p></form></div></div><?php endif; ?><main id="main">
<?php if ($f = flash()): ?><p class="msg" role="status"><?= e($f) ?></p><?php endif;
}

function foot()
{
    $H = BASE; $em = setting('email'); ?></main><footer><div class="fgrid">
<div><b><?= e(setting('name')) ?></b><p><?= e(setting('tagline')) ?></p><?php if (setting('organizer')): ?><p><small>Diselenggarakan oleh <?= e(setting('organizer')) ?></small></p><?php endif; ?></div>
<div><h4>Tautan</h4><a href="<?= $H ?>#about">About</a><a href="<?= $H ?>#dates">Important Dates</a><a href="<?= $H ?>#program">Program</a><a href="<?= $H ?>#register">Registration</a><a href="<?= $H ?>#faq">FAQ</a><a href="<?= $H ?>verify.php">Verifikasi Sertifikat</a><?php if (setting('event_start')): ?><a href="<?= $H ?>calendar.php">Tambahkan ke Kalender</a><?php endif; ?></div>
<div><h4>Kontak</h4><p><?= e(setting('secretariat')) ?></p><?php if ($em): ?><p><a href="mailto:<?= e($em) ?>"><?= e($em) ?></a></p><?php endif; ?></div></div>
<div class="copy">&copy; <?= date('Y') ?> <?= e(setting('name')) ?></div></footer><a href="#top" class="totop" aria-label="Ke atas">&uarr;</a>
<script>
(function(){var l=document.querySelectorAll('.fade');if(!('IntersectionObserver' in window)){l.forEach(function(e){e.classList.add('in')});return}var o=new IntersectionObserver(function(a){a.forEach(function(x){if(x.isIntersecting){x.target.classList.add('in');o.unobserve(x.target)}})},{threshold:.08});l.forEach(function(e){o.observe(e)})})();
/* tab hari pada agenda */
document.querySelectorAll('.daytabs').forEach(function(w){var b=w.querySelectorAll('[data-day]'),p=w.querySelectorAll('.daypane');b.forEach(function(x){x.addEventListener('click',function(){b.forEach(function(y){y.classList.toggle('on',y===x);y.setAttribute('aria-selected',y===x)});p.forEach(function(q){q.hidden=q.dataset.pane!==x.dataset.day})})})});
/* hitung mundur */
document.querySelectorAll('[data-countdown]').forEach(function(c){var t=Date.parse(c.dataset.countdown);if(isNaN(t))return;function u(){var d=Math.max(0,t-Date.now())/1000;if(d<=0){c.innerHTML='<b>The event has started!</b>';return}var v=[Math.floor(d/86400),Math.floor(d%86400/3600),Math.floor(d%3600/60),Math.floor(d%60)];c.querySelectorAll('b').forEach(function(b,i){b.textContent=v[i]})}u();setInterval(u,1000)});
</script>
<script>(function(){var m=document.getElementById('regm');if(!m)return;
var op=function(e){if(/register\.php$/.test(location.pathname))return;e.preventDefault();m.hidden=false;var i=m.querySelector('input[name=name]');if(i)i.focus()};
document.querySelectorAll('a[href$="register.php"]').forEach(function(a){a.addEventListener('click',op)});
m.addEventListener('click',function(e){if(e.target===m)m.hidden=true});
document.addEventListener('keydown',function(e){if(e.key==='Escape')m.hidden=true});
var f=m.querySelector('form'),er=m.querySelector('.merr');
f.addEventListener('submit',function(e){e.preventDefault();er.hidden=true;var b=f.querySelector('button.btn');b.disabled=true;
fetch(f.action,{method:'POST',body:new FormData(f),headers:{'X-Requested-With':'fetch'},credentials:'same-origin'}).then(function(r){return r.json()}).then(function(j){if(j.ok){location.href=j.to}else{er.textContent=j.error||'Gagal membuat akun.';er.hidden=false;b.disabled=false;if(window.turnstile)turnstile.reset()}}).catch(function(){er.textContent='Terjadi kesalahan. Muat ulang halaman lalu coba lagi.';er.hidden=false;b.disabled=false})})})();</script></body></html><?php
}

/** Jadwal acara, dikelompokkan per hari dengan tab. */
function agenda()
{
    global $pdo;
    $r = $pdo->query("SELECT * FROM agenda ORDER BY day,time,id")->fetchAll();
    if (!$r) { echo '<p class="center">Agenda akan segera diumumkan.</p>'; return; }
    $G = []; foreach ($r as $a) $G[$a['day']][] = $a;
    $multi = count($G) > 1; $i = 0;
    echo '<div class="daytabs">';
    if ($multi) { echo '<div class="tabs" role="tablist">'; foreach (array_keys($G) as $k => $d) echo '<button type="button" role="tab" class="tab' . ($k ? '' : ' on') . '" aria-selected="' . ($k ? 'false' : 'true') . '" data-day="d' . $k . '">' . e($d) . '</button>'; echo '</div>'; }
    foreach ($G as $d => $list) {
        echo '<div class="daypane" data-pane="d' . $i . '"' . ($i && $multi ? ' hidden' : '') . '>' . ($multi ? '' : '<h3>' . e($d) . '</h3>');
        echo '<div class="scroll"><table class="prog"><tr><th>Waktu</th><th>Sesi</th><th>Pembicara</th><th>Ruang / Track</th></tr>';
        foreach ($list as $a) echo '<tr><td class="nowrap">' . e($a['time']) . '</td><td><b>' . e($a['title']) . '</b>' . (!empty($a['description']) ? '<br><small>' . nl2br(e($a['description'])) . '</small>' : '') . '</td><td>' . e($a['speaker']) . '</td><td>' . e(trim($a['room'] . ((!empty($a['track']) && $a['room']) ? ' · ' : '') . ($a['track'] ?? ''))) . '</td></tr>';
        echo '</table></div></div>'; $i++;
    }
    echo '</div>';
}
