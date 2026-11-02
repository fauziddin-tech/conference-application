<?php
/*
 * Keuangan: angka pembayaran pendaftaran, verifikasi pembayaran, dan buku kas.
 * Dipakai oleh keuangan.php (admin & bendahara) dan admin/index.php (regsave).
 */

function finAccess($u) { return $u && in_array($u['role'], ['admin', 'bendahara'], true); }

/** Pecah teks biaya ("IDR 1.500.000 / US$ 85") menjadi angka per mata uang. */
function feeParse($text)
{
    $t = (string)$text; $o = ['IDR' => null, 'USD' => null];
    if (preg_match('/(?:IDR|Rp\.?)\s*([\d.,]+)/i', $t, $m)) { $d = preg_replace('/\D/', '', preg_replace('/,\d{1,2}$/', '', $m[1])); if ($d !== '') $o['IDR'] = (float)$d; }
    if (preg_match('/(?:US\$|USD|\$)\s*([\d.,]+)/i', $t, $m)) { $v = str_replace(',', '', $m[1]); if (is_numeric($v)) $o['USD'] = (float)$v; }
    return $o;
}
/** Mata uang pembayaran: tercatat saat verifikasi, atau IDR untuk Indonesia dan USD untuk negara lain. */
function regCurrency($R)
{
    if (!empty($R['pay_currency'])) return $R['pay_currency'];
    $f = feeParse($R['pay_amount'] ?? '');
    $cur = strcasecmp(trim((string)($R['country'] ?? '')), 'Indonesia') === 0 ? 'IDR' : 'USD';
    if ($f[$cur] === null) $cur = $cur === 'IDR' ? 'USD' : 'IDR';
    return $cur;
}
/** Jumlah tagihan dalam mata uang pembayaran. */
function regExpected($R) { $f = feeParse($R['pay_amount'] ?? ''); return (float)($f[regCurrency($R)] ?? 0); }
function money($amt, $cur = 'IDR')
{
    if ($amt === null || $amt === '') return '-';
    return $cur === 'USD' ? 'US$ ' . number_format((float)$amt, 2, '.', ',') : 'Rp ' . number_format((float)$amt, 0, ',', '.');
}
/** Kurs USD→IDR dari pengaturan keuangan (kosong = tidak dikonversi). */
function usdRate() { $r = (float)str_replace([',', ' '], ['.', ''], setting('usd_rate')); return $r > 0 ? $r : 0.0; }
function toIdr($amt, $cur) { return $cur === 'USD' ? (usdRate() ? $amt * usdRate() : null) : (float)$amt; }
/** Angka dari isian formulir (mendukung "1.500.000", "1500000", "85.50"). */
function parseAmount($s, $cur)
{
    $s = trim((string)$s);
    if ($cur === 'IDR') return (float)preg_replace('/\D/', '', preg_replace('/,\d{1,2}$/', '', $s));
    return (float)str_replace(',', '', $s);
}
function finCategories($type) { return array_values(array_filter(array_map('trim', preg_split('/\R/', setting($type === 'in' ? 'fin_cat_in' : 'fin_cat_out'))))); }
/** Notifikasi ke semua bendahara. */
function notifyBendahara($msg) { global $pdo; foreach ($pdo->query("SELECT id FROM users WHERE role='bendahara'")->fetchAll(PDO::FETCH_COLUMN) as $id) notifyUser($id, $msg, 'keuangan.php?p=verifikasi'); }
/** Kirim notifikasi ke admin (tautan $link) dan bendahara (tautan ke halaman verifikasi pembayaran). */
function notifyFinance($msg, $link = '')
{
    global $pdo;
    foreach ($pdo->query("SELECT id,role FROM users WHERE role IN('admin','bendahara')")->fetchAll() as $r)
        notifyUser($r['id'], $msg, $r['role'] === 'admin' ? $link : 'keuangan.php?p=verifikasi');
}

/**
 * Terapkan status pembayaran (dipakai admin & bendahara): simpan status, catatan, mata uang, jumlah diterima,
 * waktu & petugas verifikasi; perbarui status akun; kirim notifikasi (dan email bila $email).
 * Mengembalikan daftar hasil kirim email (bool).
 */
function applyPayment($old, $ps, $note, $email, $cur = null, $amount = null, $byId = null)
{
    global $pdo;
    $id = (int)$old['id'];
    $cur = in_array($cur, ['IDR', 'USD'], true) ? $cur : regCurrency($old);
    if ($ps === 'verified') {
        $amt = ($amount !== null && trim((string)$amount) !== '') ? parseAmount($amount, $cur) : ($old['pay_status'] === 'verified' && $old['pay_received'] !== null ? (float)$old['pay_received'] : (float)(feeParse($old['pay_amount'])[$cur] ?? 0));
        $pdo->prepare("UPDATE registrations SET pay_status='verified',admin_note=?,pay_currency=?,pay_received=?,pay_verified_at=COALESCE(IF(pay_status='verified',pay_verified_at,NULL),NOW()),pay_verified_by=COALESCE(IF(pay_status='verified',pay_verified_by,NULL),?) WHERE id=?")
            ->execute([$note, $cur, $amt, $byId, $id]);
    } else {
        $pdo->prepare("UPDATE registrations SET pay_status=?,admin_note=?,pay_received=NULL,pay_verified_at=NULL,pay_verified_by=NULL WHERE id=?")->execute([$ps, $note, $id]);
    }
    $pdo->prepare("UPDATE users SET status=? WHERE id=?")->execute([['unpaid' => 'pending', 'pending' => 'pending', 'verified' => 'confirmed', 'rejected' => 'rejected'][$ps] ?? 'pending', $old['user_id']]);
    $sent = [];
    if ($ps !== $old['pay_status'] && in_array($ps, ['verified', 'rejected'], true)) {
        notifyUser($old['user_id'], $ps === 'verified' ? 'Pembayaran ' . no('R', $id) . ' terverifikasi. Kwitansi, e-ticket & surat undangan tersedia.' : 'Bukti bayar ' . no('R', $id) . ' ditolak. Lihat catatan panitia.', 'dashboard.php',
            $ps === 'verified' ? 'Payment ' . no('R', $id) . ' verified. Your receipt, e-ticket & invitation letter are available.' : 'Proof of payment ' . no('R', $id) . ' was rejected. See the committee note.');
        if ($email) {
            $nm = trim(($old['prefix'] === 'Other' ? '' : $old['prefix']) . ' ' . $old['full_name']);
            $nt = $note !== '' ? "\n\nNote from the committee:\n$note" : '';
            if ($ps === 'verified') $sent[] = notify($old['email'], $nm, 'Payment verified', "Your payment for registration " . no('R', $id) . " has been verified. Your receipt, e-ticket (QR code for check-in), and invitation letter are now available in your dashboard." . ($old['attendance'] === 'Online' && setting('online_link') ? "\n\nThe online meeting link is available in your dashboard." : '') . $nt);
            else $sent[] = notify($old['email'], $nm, 'Payment could not be verified', "We could not verify the proof of payment for registration " . no('R', $id) . ". Please check the note below and upload a valid proof of payment from your dashboard." . $nt);
        }
    }
    return $sent;
}

/** Ringkasan angka keuangan (pemasukan pendaftaran, piutang, kas lain). $from/$to: tanggal Y-m-d opsional. */
function finSummary($from = '', $to = '')
{
    global $pdo;
    $s = ['recv' => ['IDR' => 0.0, 'USD' => 0.0], 'recvN' => 0, 'pend' => ['IDR' => 0.0, 'USD' => 0.0], 'pendN' => 0, 'unpaid' => ['IDR' => 0.0, 'USD' => 0.0], 'unpaidN' => 0,
        'in' => ['IDR' => 0.0, 'USD' => 0.0], 'out' => ['IDR' => 0.0, 'USD' => 0.0]];
    foreach ($pdo->query("SELECT id,country,pay_amount,pay_status,pay_currency,pay_received,pay_verified_at FROM registrations")->fetchAll() as $r) {
        $cur = regCurrency($r);
        if ($r['pay_status'] === 'verified') {
            $d = substr((string)$r['pay_verified_at'], 0, 10);
            if (($from && $d < $from) || ($to && $d > $to)) continue;
            $s['recv'][$cur] += (float)$r['pay_received']; $s['recvN']++;
        } elseif ($r['pay_status'] === 'pending') { $s['pend'][$cur] += regExpected($r); $s['pendN']++; }
        else { $s['unpaid'][$cur] += regExpected($r); $s['unpaidN']++; }
    }
    $w = []; $p = [];
    if ($from) { $w[] = 'entry_date>=?'; $p[] = $from; }
    if ($to) { $w[] = 'entry_date<=?'; $p[] = $to; }
    $q = $pdo->prepare("SELECT type,currency,SUM(amount) t FROM cash" . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . " GROUP BY type,currency"); $q->execute($p);
    foreach ($q->fetchAll() as $r) $s[$r['type']][$r['currency']] += (float)$r['t'];
    return $s;
}
/** Jumlahkan [IDR, USD] menjadi setara IDR (null bila ada USD tetapi kurs belum diisi). */
function idrTotal($pair) { return ($pair['USD'] > 0 && !usdRate()) ? null : $pair['IDR'] + $pair['USD'] * usdRate(); }
