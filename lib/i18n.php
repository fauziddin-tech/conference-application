<?php
/*
 * Dwibahasa (English default, Bahasa Indonesia).
 *  - t('English text')  : teks antarmuka; terjemahan Indonesia ada di lang/id.php
 *  - tx($kontenAdmin)   : konten yang ditulis admin, diterjemahkan otomatis (Google Translate) saat disimpan
 *                         lalu di-cache di tabel `translations`; admin dapat mengoreksi manual.
 */
const LANGS = ['en' => 'English', 'id' => 'Bahasa Indonesia'];

function lang()
{
    static $l = null;
    if ($l !== null) return $l;
    $q = (string)($_GET['lang'] ?? '');
    if (isset(LANGS[$q])) {
        if (!headers_sent()) setcookie('lang', $q, ['expires' => time() + 31536000, 'path' => BASE, 'samesite' => 'Lax', 'secure' => !empty($GLOBALS['HTTPS'])]);
        $_COOKIE['lang'] = $q;
    }
    $c = (string)($_COOKIE['lang'] ?? '');
    return $l = isset(LANGS[$c]) ? $c : 'en';
}
/** Teks antarmuka. Sumber dalam bahasa Inggris; mendukung format sprintf. */
function t($s, ...$a)
{
    static $D = null;
    $r = $s;
    if (lang() === 'id') { if ($D === null) $D = require __DIR__ . '/../lang/id.php'; $r = $D[$s] ?? $s; }
    return $a ? vsprintf($r, $a) : $r;
}
/** t() yang sudah di-escape untuk HTML. */
function te($s, ...$a) { return e(t($s, ...$a)); }
/** URL halaman saat ini dengan bahasa lain. */
function langUrl($l)
{
    $q = $_GET; $q['lang'] = $l;
    return strtok($_SERVER['REQUEST_URI'] ?? BASE, '?') . '?' . http_build_query($q);
}
/** Bulan dalam bahasa aktif. */
function fdateL($d, $l = null)
{
    $t = strtotime((string)$d); if (!$t) return (string)$d;
    return ($l ?? lang()) === 'id' ? fdate($d) : date('F j, Y', $t);
}

/* ---------- terjemahan konten admin ---------- */
function trKey($s) { return md5(trim((string)$s)); }
/** Konten admin dalam bahasa aktif (atau $l). Bila belum diterjemahkan, tampil apa adanya. */
function tx($s, $l = null)
{
    global $pdo; static $M = [];
    $s = (string)$s; $l = $l ?? lang();
    if (trim($s) === '') return $s;
    if (!isset($M[$l])) {
        try { $q = $pdo->prepare("SELECT h,txt FROM translations WHERE lang=?"); $q->execute([$l]); $M[$l] = $q->fetchAll(PDO::FETCH_KEY_PAIR); }
        catch (Exception $x) { $M[$l] = []; }
    }
    return $M[$l][trKey($s)] ?? $s;
}
/** Semua teks konten yang perlu diterjemahkan (unik, tidak kosong). */
function contentStrings()
{
    global $pdo;
    $o = [];
    foreach (['about', 'cfp_info', 'venue_info', 'reg_close_msg', 'payment_info', 'invoice_note', 'online_info', 'date', 'time', 'organizer', 'secretariat'] as $k) $o[] = setting($k);
    foreach (['benefits', 'speakers', 'fees'] as $k) foreach (rows($k) as $r) $o[] = $r[0];
    $fields = ['date' => ['title', 'body'], 'topic' => ['title', 'body'], 'committee' => ['subtitle', 'extra'], 'sponsor' => ['extra'], 'faq' => ['title', 'body'], 'news' => ['title', 'body'], 'download' => ['title'], 'moderator' => ['subtitle']];
    foreach ($pdo->query("SELECT kind,title,subtitle,body,extra FROM items") as $it) foreach ($fields[$it['kind']] ?? [] as $f) $o[] = $it[$f];
    foreach ($pdo->query("SELECT day,title,track,description FROM agenda") as $a) { $o[] = $a['day']; $o[] = $a['title']; $o[] = $a['track']; $o[] = $a['description']; }
    $u = [];
    foreach ($o as $s) { $s = trim((string)$s); if ($s !== '' && !preg_match('/^[\d\s.,:\/\-+()]+$/', $s)) $u[trKey($s)] = $s; }
    return $u;
}
/**
 * Terjemahkan banyak teks sekaligus ke $to memakai Google Translate (endpoint gratis, tanpa API key).
 * Mengembalikan daftar [teks, bahasa sumber] sesuai urutan, atau null + $err.
 */
function gtranslateBatch(array $texts, $to, &$err)
{
    $err = '';
    if (!$texts) return [];
    if (!function_exists('curl_init')) { $err = 'Ekstensi cURL tidak aktif.'; return null; }
    $url = (defined('TRANSLATE_API') ? TRANSLATE_API : 'https://translate.googleapis.com/translate_a/t') . '?client=gtx&dj=1&sl=auto&tl=' . $to;
    $body = '';
    foreach ($texts as $t) $body .= ($body === '' ? '' : '&') . 'q=' . rawurlencode($t);
    $c = curl_init($url);
    curl_setopt_array($c, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25, CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded;charset=utf-8'], CURLOPT_USERAGENT => 'Mozilla/5.0 (conference-application)']);
    $raw = curl_exec($c); $code = (int)curl_getinfo($c, CURLINFO_HTTP_CODE); $ce = curl_error($c); curl_close($c);
    if ($raw === false) { $err = 'Koneksi ke layanan terjemahan gagal: ' . $ce; return null; }
    $j = json_decode((string)$raw, true);
    if (in_array($code, [302, 403, 429], true)) { $err = 'Google Translate sedang membatasi permintaan (HTTP ' . $code . '). Coba lagi beberapa menit lagi.'; return null; }
    if ($code !== 200 || !is_array($j)) { $err = 'Layanan terjemahan menolak permintaan (HTTP ' . $code . ').'; return null; }
    // satu teks: ["hasil","id"] ; banyak teks: [["hasil","id"], ...]
    if (count($texts) === 1 && isset($j[0]) && is_string($j[0])) $j = [$j];
    $out = []; foreach ($j as $r) $out[] = is_array($r) ? [(string)($r[0] ?? ''), (string)($r[1] ?? '')] : [(string)$r, ''];
    if (count($out) !== count($texts)) { $err = 'Jumlah hasil terjemahan tidak sesuai.'; return null; }
    return $out;
}
/** Bagi teks menjadi kelompok kecil (maks. 25 teks / ±4.500 karakter per permintaan). */
function trChunks(array $texts)
{
    $o = []; $cur = []; $len = 0;
    foreach ($texts as $h => $t) {
        $l = mb_strlen($t);
        if ($cur && (count($cur) >= 25 || $len + $l > 4500)) { $o[] = $cur; $cur = []; $len = 0; }
        $cur[$h] = $t; $len += $l;
    }
    if ($cur) $o[] = $cur;
    return $o;
}
/**
 * Terjemahkan konten yang belum ada di cache (EN & ID). $force: ulangi semua kecuali yang diedit manual.
 * Mengembalikan ['done' => n, 'left' => n, 'error' => '...'].
 */
function translateMissing($budget = 20, $force = false)
{
    global $pdo;
    $res = ['done' => 0, 'left' => 0, 'error' => ''];
    if (setting('auto_translate', '1') !== '1') return $res;
    @set_time_limit($budget + 40);
    $have = [];
    foreach ($pdo->query("SELECT h,lang,manual FROM translations") as $r) $have[$r['h']][$r['lang']] = (int)$r['manual'];
    $need = [];
    foreach (contentStrings() as $h => $s) {
        $miss = [];
        foreach (array_keys(LANGS) as $l) if (!isset($have[$h][$l]) || ($force && empty($have[$h][$l]))) $miss[] = $l;
        if ($miss) $need[$h] = ['s' => $s, 'miss' => $miss];
    }
    if (!$need) return $res;
    $start = microtime(true);
    $put = $pdo->prepare("REPLACE INTO translations(h,lang,src,txt,src_lang,manual) VALUES(?,?,?,?,?,0)");
    $done = [];
    foreach (trChunks(array_map(function ($n) { return $n['s']; }, $need)) as $i => $chunk) {
        if (microtime(true) - $start > $budget) break;
        if ($i) usleep(400000);
        // 1) ke Inggris (sekaligus mendeteksi bahasa sumber)
        $en = gtranslateBatch(array_values($chunk), 'en', $err);
        if ($en === null) { $res['error'] = $err; break; }
        $hs = array_keys($chunk); $toId = [];
        foreach ($hs as $k => $h) {
            $src = substr(strtolower($en[$k][1]), 0, 2); $s = $chunk[$h];
            $need[$h]['src'] = $src; $need[$h]['en'] = $src === 'en' ? $s : $en[$k][0];
            if (in_array('id', $need[$h]['miss'], true)) { if ($src === 'id') $need[$h]['id'] = $s; else $toId[$h] = $s; }
        }
        // 2) ke Indonesia untuk teks yang sumbernya bukan bahasa Indonesia
        if ($toId) {
            usleep(400000);
            $id = gtranslateBatch(array_values($toId), 'id', $err);
            if ($id === null) { $res['error'] = $err; }
            else foreach (array_keys($toId) as $k => $h) $need[$h]['id'] = $id[$k][0];
        }
        foreach ($hs as $h) {
            $ok = true;
            foreach ($need[$h]['miss'] as $l) { if (isset($need[$h][$l])) $put->execute([$h, $l, $need[$h]['s'], $need[$h][$l], $need[$h]['src']]); else $ok = false; }
            if ($ok) $done[$h] = true;
        }
        if ($res['error'] !== '') break;
    }
    $res['done'] = count($done); $res['left'] = count($need) - count($done);
    return $res;
}
/** Hapus terjemahan untuk teks yang sudah tidak dipakai. */
function translationsPrune()
{
    global $pdo;
    $keep = array_keys(contentStrings());
    $n = 0;
    foreach ($pdo->query("SELECT DISTINCT h FROM translations")->fetchAll(PDO::FETCH_COLUMN) as $h) if (!in_array($h, $keep, true)) { $pdo->prepare("DELETE FROM translations WHERE h=?")->execute([$h]); $n++; }
    return $n;
}
