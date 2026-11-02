<?php require 'inc.php'; $u = need(['admin', 'bendahara']); chk();
/* Keuangan konferensi: ringkasan, verifikasi pembayaran, pemasukan pendaftaran, buku kas, laporan. Untuk admin & bendahara. */
define('ADMIN', 1); // label dialog konfirmasi berbahasa Indonesia
$p = preg_replace('/[^a-z]/', '', (string)($_GET['p'] ?? 'ringkasan')) ?: 'ringkasan';
$CUR = ['IDR', 'USD'];
$METHODS = ['transfer' => 'Transfer bank', 'tunai' => 'Tunai', 'qris' => 'QRIS / e-wallet', 'lainnya' => 'Lainnya'];
$dateOk = function ($d) { return preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$d) ? $d : ''; };
$from = $dateOk($_GET['from'] ?? ''); $to = $dateOk($_GET['to'] ?? '');

/* ---------- data pembayaran terverifikasi (dipakai Pemasukan & ekspor) ---------- */
$paidRows = function () use ($pdo, $from, $to) {
    $w = ["r.pay_status='verified'"]; $par = [];
    if ($from) { $w[] = 'DATE(r.pay_verified_at)>=?'; $par[] = $from; }
    if ($to) { $w[] = 'DATE(r.pay_verified_at)<=?'; $par[] = $to; }
    if (in_array($_GET['cur'] ?? '', ['IDR', 'USD'], true)) { $w[] = 'r.pay_currency=?'; $par[] = $_GET['cur']; }
    if (($_GET['cat'] ?? '') !== '') { $w[] = 'r.category=?'; $par[] = $_GET['cat']; }
    $q = $pdo->prepare("SELECT r.*,u.email,v.name verifier FROM registrations r JOIN users u ON u.id=r.user_id LEFT JOIN users v ON v.id=r.pay_verified_by WHERE " . implode(' AND ', $w) . " ORDER BY r.pay_verified_at DESC,r.id DESC");
    $q->execute($par); return $q->fetchAll();
};
$cashRows = function () use ($pdo) {
    $w = []; $par = [];
    if (preg_match('/^\d{4}-\d{2}$/', $_GET['m'] ?? '')) { $w[] = "DATE_FORMAT(c.entry_date,'%Y-%m')=?"; $par[] = $_GET['m']; }
    if (in_array($_GET['type'] ?? '', ['in', 'out'], true)) { $w[] = 'c.type=?'; $par[] = $_GET['type']; }
    if (($_GET['cat'] ?? '') !== '') { $w[] = 'c.category=?'; $par[] = $_GET['cat']; }
    $q = $pdo->prepare("SELECT c.*,u.name author FROM cash c LEFT JOIN users u ON u.id=c.created_by" . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . " ORDER BY c.entry_date DESC,c.id DESC");
    $q->execute($par); return $q->fetchAll();
};

/* ---------- ekspor CSV ---------- */
if ($p === 'exportin') csvOut('pemasukan-pendaftaran-' . date('Ymd') . '.csv', ['Tgl Verifikasi', 'No Reg', 'Nama', 'Email', 'Negara', 'Kategori', 'Mata Uang', 'Jumlah Diterima', 'Tagihan', 'Pengirim', 'Tgl Bayar', 'Diverifikasi Oleh'],
    array_map(function ($r) { return [$r['pay_verified_at'], no('R', $r['id']), $r['full_name'], $r['email'], $r['country'], $r['category'], $r['pay_currency'], $r['pay_received'], $r['pay_amount'], $r['pay_sender'], $r['pay_date'], $r['verifier']]; }, $paidRows()));
if ($p === 'exportkas') csvOut('buku-kas-' . date('Ymd') . '.csv', ['Tanggal', 'Jenis', 'Kategori', 'Keterangan', 'Metode', 'Mata Uang', 'Masuk', 'Keluar', 'Nota', 'Dicatat Oleh'],
    array_map(function ($c) { return [$c['entry_date'], $c['type'] === 'in' ? 'Masuk' : 'Keluar', $c['category'], $c['description'], $c['method'], $c['currency'], $c['type'] === 'in' ? $c['amount'] : '', $c['type'] === 'out' ? $c['amount'] : '', $c['receipt_file'] ? 'ada' : '', $c['author']]; }, array_reverse($cashRows())));

/* ---------- aksi ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = post('a'); $id = (int)($_POST['id'] ?? 0); $msg = 'Tersimpan.'; $back = '?p=' . $p;
    if ($a === 'verify') {
        $q = $pdo->prepare("SELECT r.*,u.email FROM registrations r JOIN users u ON u.id=r.user_id WHERE r.id=?"); $q->execute([$id]); $old = $q->fetch();
        $dec = post('decision');
        if (!$old || !in_array($dec, ['verified', 'rejected'], true)) $msg = 'Data tidak valid.';
        elseif ($dec === 'rejected' && post('note') === '') $msg = 'Tulis alasan penolakan di kolom catatan agar peserta tahu apa yang harus diperbaiki.';
        else {
            $sent = applyPayment($old, $dec, post('note'), !empty($_POST['email']), post('cur'), $_POST['amount'] ?? null, $u['id']);
            $msg = no('R', $id) . ' ' . ($dec === 'verified' ? 'diverifikasi (' . money($dec === 'verified' ? parseAmount($_POST['amount'] ?? '', post('cur') ?: regCurrency($old)) : 0, post('cur') ?: regCurrency($old)) . ').' : 'ditolak.') . ($sent ? ' Email ' . (array_filter($sent) ? 'terkirim.' : 'gagal terkirim.') : '');
        }
        $back = '?p=verifikasi' . (isset($_GET['s']) ? '&s=' . urlencode($_GET['s']) : '');
    } elseif ($a === 'remind') {
        $rows = $pdo->query("SELECT r.id,r.prefix,r.full_name,r.pay_amount,r.pay_status,r.country,u.email FROM registrations r JOIN users u ON u.id=r.user_id WHERE r.pay_status IN('unpaid','rejected')")->fetchAll();
        [$n, $tot] = sendBatch(array_map(function ($r) { $nm = trim(($r['prefix'] === 'Other' ? '' : $r['prefix']) . ' ' . $r['full_name']);
            return [$r['email'], '[' . brand() . '] Payment reminder ' . no('R', $r['id']), "Dear $nm,\n\nThis is a friendly reminder that the registration fee for " . no('R', $r['id']) . " (" . money(regExpected($r), regCurrency($r)) . ") has not been completed yet." . ($r['pay_status'] === 'rejected' ? " Your previous proof of payment could not be verified; please check the note in your dashboard." : '') . "\n\nPlease make the payment according to the invoice and upload the proof of payment from your dashboard:\n" . absUrl('dashboard.php') . "\n\nIf you have already paid, please upload the proof so we can verify it.\n\nBest regards,\nOrganizing Committee\n" . setting('name')]; }, $rows));
        $msg = "Email pengingat terkirim: $n dari $tot peserta."; $back = '?p=verifikasi&s=unpaid';
    } elseif ($a === 'cashsave') {
        $back = '?p=kas';
        $d = $dateOk(post('entry_date')); $type = post('type') === 'in' ? 'in' : 'out'; $cur = in_array(post('currency'), $CUR, true) ? post('currency') : 'IDR';
        $amt = parseAmount(post('amount'), $cur); $cat = post('category'); $desc = post('description');
        $q = $pdo->prepare("SELECT * FROM cash WHERE id=?"); $q->execute([$id]); $old = $q->fetch();
        if (!$d || $amt <= 0 || $desc === '') { $msg = 'Tanggal, keterangan, dan jumlah (> 0) wajib diisi.'; $back = '?p=kas' . ($old ? '&edit=' . $id : ''); }
        else {
            $file = null; $e = '';
            if (!empty($_FILES['receipt']['name'])) { $file = upFile($_FILES['receipt'], ['pdf', 'jpg', 'jpeg', 'png'], $e, 'Nota'); if (!$file) $msg = $e . ' Transaksi tetap disimpan tanpa nota.'; }
            $rf = $old['receipt_file'] ?? null; $rn = $old['receipt_name'] ?? null;
            if ($file) { rmPriv($rf); [$rf, $rn] = $file; } elseif (!empty($_POST['receipt_rm'])) { rmPriv($rf); $rf = $rn = null; }
            $method = isset($METHODS[post('method')]) ? post('method') : 'transfer';
            if ($old) $pdo->prepare("UPDATE cash SET entry_date=?,type=?,category=?,description=?,amount=?,currency=?,method=?,receipt_file=?,receipt_name=? WHERE id=?")->execute([$d, $type, $cat, $desc, $amt, $cur, $method, $rf, $rn, $id]);
            else $pdo->prepare("INSERT INTO cash(entry_date,type,category,description,amount,currency,method,receipt_file,receipt_name,created_by) VALUES(?,?,?,?,?,?,?,?,?,?)")->execute([$d, $type, $cat, $desc, $amt, $cur, $method, $rf, $rn, $u['id']]);
            if (!$file || $msg === 'Tersimpan.') $msg = ($old ? 'Transaksi diperbarui: ' : 'Transaksi dicatat: ') . ($type === 'in' ? 'masuk ' : 'keluar ') . money($amt, $cur) . '.';
        }
    } elseif ($a === 'cashdel') {
        $q = $pdo->prepare("SELECT receipt_file FROM cash WHERE id=?"); $q->execute([$id]);
        if (($rf = $q->fetchColumn()) !== false) { rmPriv($rf); $pdo->prepare("DELETE FROM cash WHERE id=?")->execute([$id]); $msg = 'Transaksi dihapus.'; }
        $back = '?p=kas';
    } elseif ($a === 'finset') {
        $r = trim(str_replace(['.', ','], ['', '.'], post('usd_rate')));
        setSetting('usd_rate', $r === '' ? '' : (string)(float)$r);
        foreach (['fin_cat_in', 'fin_cat_out'] as $k) setSetting($k, implode("\n", array_unique(array_filter(array_map('trim', preg_split('/\R/', (string)($_POST[$k] ?? '')))))));
        $msg = 'Pengaturan keuangan disimpan.'; $back = '?p=pengaturan';
    }
    flash($msg); go($back);
}

$TABS = ['ringkasan' => 'Ringkasan', 'verifikasi' => 'Verifikasi Pembayaran', 'pemasukan' => 'Pemasukan', 'kas' => 'Buku Kas', 'laporan' => 'Laporan', 'pengaturan' => 'Pengaturan'];
$pendN = (int)$pdo->query("SELECT COUNT(*) FROM registrations WHERE pay_status='pending'")->fetchColumn();
head('Keuangan'); ?>
<h2 class="noprint">Keuangan</h2>
<div class="tabs noprint"><?php if ($u['role'] === 'admin'): ?><a href="admin/">&larr; Panel Admin</a><?php endif; ?><?php foreach ($TABS as $k => $l): ?><a href="?p=<?= $k ?>" class="<?= $p === $k ? 'on' : '' ?>"><?= e($l) ?><?= $k === 'verifikasi' && $pendN ? ' <b class="cnt">' . $pendN . '</b>' : '' ?></a><?php endforeach; ?></div>
<?php
$pair = function ($a) { $o = []; foreach (['IDR', 'USD'] as $c) if ($a[$c] > 0) $o[] = money($a[$c], $c); return $o ? implode(' + ', $o) : money(0); };
$eq = function ($a) { $v = idrTotal($a); return ($a['USD'] > 0) ? ($v === null ? '<small class="muted">isi kurs USD untuk total setara IDR</small>' : '<small>≈ ' . money($v) . '</small>') : ''; };

if ($p === 'ringkasan'):
    $S = finSummary();
    $bal = ['IDR' => $S['recv']['IDR'] + $S['in']['IDR'] - $S['out']['IDR'], 'USD' => $S['recv']['USD'] + $S['in']['USD'] - $S['out']['USD']];
    $byCat = []; foreach ($pdo->query("SELECT category,country,pay_amount,pay_status,pay_currency,pay_received FROM registrations")->fetchAll() as $r) {
        $c = $r['category'] ?: '-'; $byCat[$c] = $byCat[$c] ?? ['n' => 0, 'paid' => 0, 'recv' => ['IDR' => 0, 'USD' => 0], 'due' => ['IDR' => 0, 'USD' => 0]];
        $byCat[$c]['n']++; $cur = regCurrency($r);
        if ($r['pay_status'] === 'verified') { $byCat[$c]['paid']++; $byCat[$c]['recv'][$cur] += (float)$r['pay_received']; } else $byCat[$c]['due'][$cur] += regExpected($r);
    }
    $outCat = $pdo->query("SELECT category,currency,SUM(amount) t FROM cash WHERE type='out' GROUP BY category,currency ORDER BY t DESC")->fetchAll(); ?>
<div class="stats fin">
<div class="stat"><span>Pemasukan pendaftaran (<?= $S['recvN'] ?> lunas)</span><b><?= $pair($S['recv']) ?></b><?= $eq($S['recv']) ?></div>
<a class="stat warn" href="?p=verifikasi"><span>Menunggu verifikasi (<?= $S['pendN'] ?>)</span><b><?= $pair($S['pend']) ?></b></a>
<a class="stat" href="?p=verifikasi&s=unpaid"><span>Belum bayar / ditolak (<?= $S['unpaidN'] ?>)</span><b><?= $pair($S['unpaid']) ?></b></a>
<a class="stat" href="?p=kas&type=in"><span>Pemasukan lain (sponsor, dll.)</span><b><?= $pair($S['in']) ?></b></a>
<a class="stat out" href="?p=kas&type=out"><span>Pengeluaran</span><b><?= $pair($S['out']) ?></b></a>
<div class="stat bal"><span>Saldo kas</span><b><?= $pair(['IDR' => $bal['IDR'], 'USD' => $bal['USD']]) ?></b><?= $eq(['IDR' => $bal['IDR'], 'USD' => max(0, $bal['USD'])]) ?></div>
</div>
<?php if (!usdRate() && ($S['recv']['USD'] || $S['in']['USD'] || $S['out']['USD'])): ?><p class="msg">Ada transaksi dalam USD. Isi <a href="?p=pengaturan">kurs USD → IDR</a> agar total gabungan dapat dihitung.</p><?php endif; ?>
<div class="dgrid">
<div class="card"><h3>Per kategori pendaftaran</h3><div class="scroll"><table><tr><th>Kategori</th><th>Daftar</th><th>Lunas</th><th>Diterima</th><th>Belum diterima</th></tr>
<?php foreach ($byCat as $c => $v): ?><tr><td><?= e($c) ?></td><td><?= $v['n'] ?></td><td><?= $v['paid'] ?></td><td class="nowrap"><?= $pair($v['recv']) ?></td><td class="nowrap"><?= $pair($v['due']) ?></td></tr><?php endforeach; ?></table></div></div>
<div class="card"><h3>Pengeluaran per kategori</h3><?php if (!$outCat): ?><p><small>Belum ada pengeluaran. Catat di <a href="?p=kas">Buku Kas</a>.</small></p><?php endif; ?>
<?php $mx = max(array_merge([1], array_map(function ($r) { return (float)toIdr($r['t'], $r['currency']); }, $outCat))); foreach ($outCat as $r): ?><div class="bar"><span><?= e($r['category'] ?: '-') ?></span><i style="--w:<?= round((float)toIdr($r['t'], $r['currency']) / $mx * 100) ?>%"></i><b><?= money($r['t'], $r['currency']) ?></b></div><?php endforeach; ?></div>
</div>

<?php elseif ($p === 'verifikasi'):
    $s = in_array($_GET['s'] ?? '', ['pending', 'unpaid'], true) ? $_GET['s'] : 'pending';
    $L = $pdo->query("SELECT r.*,u.email FROM registrations r JOIN users u ON u.id=r.user_id WHERE " . ($s === 'pending' ? "r.pay_status='pending'" : "r.pay_status IN('unpaid','rejected')") . " ORDER BY r.updated_at")->fetchAll(); ?>
<p class="tabs small"><a href="?p=verifikasi" class="<?= $s === 'pending' ? 'on' : '' ?>">Menunggu verifikasi (<?= $pendN ?>)</a><a href="?p=verifikasi&s=unpaid" class="<?= $s === 'unpaid' ? 'on' : '' ?>">Belum bayar / ditolak</a></p>
<?php if ($s === 'pending'): ?>
<?php if (!$L): ?><p class="panel">Tidak ada bukti bayar yang menunggu verifikasi.</p><?php endif; ?>
<?php foreach ($L as $x): $xc = regCurrency($x); ?>
<div class="card vcard"><div class="vinfo"><b><?= no('R', $x['id']) ?> &middot; <?= e(trim($x['prefix'] . ' ' . $x['full_name'])) ?></b><br>
<small><?= e($x['category']) ?> &middot; <?= e($x['country']) ?> &middot; <?= e($x['email']) ?></small><br>
Tagihan: <b><?= e($x['pay_amount']) ?></b><br>Pengirim: <b><?= e($x['pay_sender'] ?: '-') ?></b> &middot; Tanggal bayar: <b><?= e($x['pay_date'] ? fdate($x['pay_date']) : '-') ?></b><br>
<?php if ($x['pay_file']): ?><a class="btn ghost2 js-doc" data-title="Bukti bayar <?= no('R', $x['id']) ?>" href="file.php?id=<?= $x['id'] ?>&k=pay&view=1" target="_blank">Lihat bukti bayar</a><?php endif; ?>
<a class="js-doc" data-title="Invoice <?= no('R', $x['id']) ?>" href="doc.php?t=invoice&id=<?= $x['id'] ?>" target="_blank">Invoice</a></div>
<form method="post" class="vform"><?= csrf() ?><input type="hidden" name="a" value="verify"><input type="hidden" name="id" value="<?= $x['id'] ?>">
<label>Mata uang<select name="cur"><?php foreach ($CUR as $c): ?><option <?= $xc === $c ? 'selected' : '' ?>><?= $c ?></option><?php endforeach; ?></select></label>
<label>Jumlah diterima<input name="amount" inputmode="decimal" value="<?= e(regExpected($x) + 0) ?>"></label>
<label class="full">Catatan untuk peserta <small>(wajib bila ditolak)</small><input name="note" value="<?= e($x['admin_note']) ?>"></label>
<label class="chk full"><input type="checkbox" name="email" value="1" checked> Kirim email ke peserta</label>
<div class="full vbtn"><button class="btn" name="decision" value="verified">&#10004; Terverifikasi</button><button class="btn danger" name="decision" value="rejected">Tolak</button></div></form></div>
<?php endforeach; ?>
<?php else: ?>
<form method="post" data-confirm="Kirim email pengingat pembayaran ke <?= count($L) ?> peserta yang belum bayar / bukti ditolak?" class="filters"><?= csrf() ?><input type="hidden" name="a" value="remind"><button class="btn" <?= $L ? '' : 'disabled' ?>>Kirim Email Pengingat (<?= count($L) ?>)</button></form>
<div class="scroll"><table><tr><th>No. Reg</th><th>Nama</th><th>Kategori</th><th>Tagihan</th><th>Status</th><th>Daftar</th><th>Kontak</th></tr>
<?php foreach ($L as $x): ?><tr><td><?= no('R', $x['id']) ?></td><td><?= e($x['full_name']) ?></td><td><?= e($x['category']) ?></td><td class="nowrap"><?= money(regExpected($x), regCurrency($x)) ?></td><td><span class="badge <?= e($x['pay_status']) ?>"><?= e(lbl($PAY, $x['pay_status'])) ?></span></td><td><small><?= e(fdate($x['created_at'])) ?></small></td>
<td class="nowrap"><a href="mailto:<?= e($x['email']) ?>">email</a><?php if ($x['phone']): ?> &middot; <a href="https://wa.me/<?= preg_replace('/\D/', '', preg_replace('/^0/', '62', $x['phone'])) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?></td></tr><?php endforeach; ?></table></div>
<?php endif; ?>

<?php elseif ($p === 'pemasukan'):
    $L = $paidRows(); $tot = ['IDR' => 0, 'USD' => 0]; foreach ($L as $x) $tot[$x['pay_currency'] ?: 'IDR'] += (float)$x['pay_received'];
    $q = $_GET; unset($q['p']); $qs = http_build_query($q); ?>
<form method="get" class="filters"><input type="hidden" name="p" value="pemasukan"><label class="chk">Dari <input type="date" name="from" value="<?= e($from) ?>"></label><label class="chk">Sampai <input type="date" name="to" value="<?= e($to) ?>"></label>
<select name="cur"><option value="">Semua mata uang</option><?php foreach ($CUR as $c): ?><option <?= ($_GET['cur'] ?? '') === $c ? 'selected' : '' ?>><?= $c ?></option><?php endforeach; ?></select>
<select name="cat"><option value="">Semua kategori</option><?php foreach (fees() as $f): ?><option <?= ($_GET['cat'] ?? '') === $f['cat'] ? 'selected' : '' ?>><?= e($f['cat']) ?></option><?php endforeach; ?></select><button class="btn">Filter</button> <a class="btn ghost2" href="?p=exportin&<?= e($qs) ?>">Ekspor CSV (Excel)</a></form>
<p><b><?= count($L) ?></b> pembayaran &middot; Total: <b><?= $pair($tot) ?></b> <?= $eq($tot) ?></p>
<div class="scroll"><table><tr><th>Tgl verifikasi</th><th>No. Reg</th><th>Nama</th><th>Kategori</th><th>Diterima</th><th>Pengirim</th><th>Tgl bayar</th><th>Verifikator</th><th></th></tr>
<?php foreach ($L as $x): ?><tr><td class="nowrap"><?= e(substr((string)$x['pay_verified_at'], 0, 10)) ?></td><td><?= no('R', $x['id']) ?></td><td><?= e($x['full_name']) ?></td><td><?= e($x['category']) ?></td><td class="nowrap"><b><?= money($x['pay_received'], $x['pay_currency'] ?: 'IDR') ?></b></td><td><?= e($x['pay_sender']) ?></td><td class="nowrap"><?= e($x['pay_date']) ?></td><td><small><?= e($x['verifier'] ?: '-') ?></small></td>
<td class="nowrap"><a class="js-doc" data-title="Kwitansi <?= no('R', $x['id']) ?>" href="doc.php?t=receipt&id=<?= $x['id'] ?>" target="_blank">Kwitansi</a><?php if ($x['pay_file']): ?> &middot; <a class="js-doc" data-title="Bukti bayar" href="file.php?id=<?= $x['id'] ?>&k=pay&view=1" target="_blank">Bukti</a><?php endif; ?></td></tr><?php endforeach; ?></table></div>

<?php elseif ($p === 'kas'):
    $eid = (int)($_GET['edit'] ?? 0); $E = null; if ($eid) { $q = $pdo->prepare("SELECT * FROM cash WHERE id=?"); $q->execute([$eid]); $E = $q->fetch() ?: null; }
    $L = $cashRows(); $tin = ['IDR' => 0, 'USD' => 0]; $tout = ['IDR' => 0, 'USD' => 0]; foreach ($L as $c) { if ($c['type'] === 'in') $tin[$c['currency']] += $c['amount']; else $tout[$c['currency']] += $c['amount']; }
    $months = $pdo->query("SELECT DISTINCT DATE_FORMAT(entry_date,'%Y-%m') m FROM cash ORDER BY m DESC")->fetchAll(PDO::FETCH_COLUMN);
    $type = $E['type'] ?? (($_GET['type'] ?? '') === 'in' ? 'in' : 'out'); ?>
<h3><?= $E ? 'Edit Transaksi' : 'Catat Transaksi' ?></h3>
<p><small>Pembayaran pendaftaran <b>tidak perlu</b> dicatat di sini; jumlahnya otomatis masuk dari Verifikasi Pembayaran. Catat di sini pemasukan lain (sponsor, bantuan institusi) dan semua pengeluaran.</small></p>
<form method="post" enctype="multipart/form-data" class="card"><?= csrf() ?><input type="hidden" name="a" value="cashsave"><input type="hidden" name="id" value="<?= $E['id'] ?? 0 ?>">
<label>Jenis<select name="type" id="ctype"><option value="out" <?= $type === 'out' ? 'selected' : '' ?>>Pengeluaran</option><option value="in" <?= $type === 'in' ? 'selected' : '' ?>>Pemasukan lain</option></select></label>
<label>Tanggal<input type="date" name="entry_date" required value="<?= e($E['entry_date'] ?? date('Y-m-d')) ?>"></label>
<label>Kategori<select name="category" id="ccat"></select></label>
<label class="full">Keterangan *<input name="description" required maxlength="500" value="<?= e($E['description'] ?? '') ?>" placeholder="mis. Konsumsi makan siang 150 peserta (Hotel Pangeran)"></label>
<label>Jumlah *<input name="amount" required inputmode="decimal" value="<?= $E ? e($E['amount'] + 0) : '' ?>" placeholder="1.500.000"></label>
<label>Mata uang<select name="currency"><?php foreach ($CUR as $c): ?><option <?= ($E['currency'] ?? 'IDR') === $c ? 'selected' : '' ?>><?= $c ?></option><?php endforeach; ?></select></label>
<label>Metode<select name="method"><?php foreach ($METHODS as $k => $l): ?><option value="<?= $k ?>" <?= ($E['method'] ?? 'transfer') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></label>
<label>Nota / bukti <small>(PDF/JPG/PNG, opsional)</small><input type="file" name="receipt" accept=".pdf,.jpg,.jpeg,.png"><?php if (!empty($E['receipt_file'])): ?><small>Tersimpan: <a class="js-doc" data-title="Nota" href="file.php?cash=<?= $E['id'] ?>&view=1" target="_blank"><?= e($E['receipt_name']) ?></a> &middot; <label class="chk" style="display:inline-flex"><input type="checkbox" name="receipt_rm" value="1"> hapus</label></small><?php endif; ?></label>
<div class="full"><button class="btn"><?= $E ? 'Simpan Perubahan' : 'Catat' ?></button><?php if ($E): ?> <a href="?p=kas">Batal</a><?php endif; ?></div></form>
<script>(function(){var C={in:<?= json_encode(finCategories('in')) ?>,out:<?= json_encode(finCategories('out')) ?>},t=document.getElementById('ctype'),c=document.getElementById('ccat'),cur=<?= json_encode($E['category'] ?? '') ?>;
function fill(){var l=C[t.value].slice();if(cur&&l.indexOf(cur)<0)l.unshift(cur);c.innerHTML='';l.forEach(function(v){var o=document.createElement('option');o.textContent=v;if(v===cur)o.selected=true;c.appendChild(o)})}t.addEventListener('change',function(){cur='';fill()});fill()})();</script>
<h3>Daftar Transaksi</h3>
<form method="get" class="filters"><input type="hidden" name="p" value="kas"><select name="m"><option value="">Semua bulan</option><?php foreach ($months as $m): ?><option value="<?= $m ?>" <?= ($_GET['m'] ?? '') === $m ? 'selected' : '' ?>><?= e(fdate($m . '-01') ? substr(fdate($m . '-01'), 2) : $m) ?></option><?php endforeach; ?></select>
<select name="type"><option value="">Masuk &amp; keluar</option><option value="in" <?= ($_GET['type'] ?? '') === 'in' ? 'selected' : '' ?>>Pemasukan lain</option><option value="out" <?= ($_GET['type'] ?? '') === 'out' ? 'selected' : '' ?>>Pengeluaran</option></select>
<select name="cat"><option value="">Semua kategori</option><?php foreach (array_unique(array_merge(finCategories('in'), finCategories('out'))) as $c): ?><option <?= ($_GET['cat'] ?? '') === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select>
<button class="btn">Filter</button> <a class="btn ghost2" href="?p=exportkas&<?= e(http_build_query(array_diff_key($_GET, ['p' => 1, 'edit' => 1]))) ?>">Ekspor CSV (Excel)</a></form>
<p>Masuk: <b class="pos"><?= $pair($tin) ?></b> &middot; Keluar: <b class="neg"><?= $pair($tout) ?></b> <small>(sesuai filter; tidak termasuk pembayaran pendaftaran)</small></p>
<div class="scroll"><table><tr><th>Tanggal</th><th>Jenis</th><th>Kategori</th><th>Keterangan</th><th>Metode</th><th>Jumlah</th><th>Nota</th><th>Dicatat</th><th></th></tr>
<?php foreach ($L as $c): ?><tr><td class="nowrap"><?= e($c['entry_date']) ?></td><td><span class="badge <?= $c['type'] === 'in' ? 'verified' : 'rejected' ?>"><?= $c['type'] === 'in' ? 'Masuk' : 'Keluar' ?></span></td><td><?= e($c['category']) ?></td><td><?= e($c['description']) ?></td><td><small><?= e($METHODS[$c['method']] ?? $c['method']) ?></small></td>
<td class="nowrap <?= $c['type'] === 'in' ? 'pos' : 'neg' ?>"><b><?= ($c['type'] === 'in' ? '+ ' : '− ') . money($c['amount'], $c['currency']) ?></b></td><td><?= $c['receipt_file'] ? '<a class="js-doc" data-title="Nota" href="file.php?cash=' . $c['id'] . '&view=1" target="_blank">lihat</a>' : '<small class="muted">-</small>' ?></td><td><small><?= e($c['author'] ?: '-') ?></small></td>
<td class="nowrap"><a href="?p=kas&edit=<?= $c['id'] ?>">Edit</a> &nbsp; <form method="post" class="inline" data-confirm="Hapus transaksi ini (<?= e(money($c['amount'], $c['currency'])) ?>)?"><?= csrf() ?><input type="hidden" name="a" value="cashdel"><input type="hidden" name="id" value="<?= $c['id'] ?>"><button class="link">Hapus</button></form></td></tr><?php endforeach; ?>
<?php if (!$L): ?><tr><td colspan="9"><small>Belum ada transaksi.</small></td></tr><?php endif; ?></table></div>

<?php elseif ($p === 'laporan'):
    $S = finSummary($from, $to);
    $w = ["pay_status='verified'"]; $par = []; if ($from) { $w[] = 'DATE(pay_verified_at)>=?'; $par[] = $from; } if ($to) { $w[] = 'DATE(pay_verified_at)<=?'; $par[] = $to; }
    $q = $pdo->prepare("SELECT category,pay_currency cur,COUNT(*) n,SUM(pay_received) t FROM registrations WHERE " . implode(' AND ', $w) . " GROUP BY category,pay_currency ORDER BY category"); $q->execute($par); $regCat = [];
    foreach ($q->fetchAll() as $r) { $k = $r['category'] ?: '-'; $regCat[$k] = $regCat[$k] ?? ['n' => 0, 'IDR' => 0, 'USD' => 0]; $regCat[$k]['n'] += $r['n']; $regCat[$k][$r['cur'] ?: 'IDR'] += $r['t']; }
    $cw = []; $cp = []; if ($from) { $cw[] = 'entry_date>=?'; $cp[] = $from; } if ($to) { $cw[] = 'entry_date<=?'; $cp[] = $to; }
    $q = $pdo->prepare("SELECT type,category,currency,SUM(amount) t FROM cash" . ($cw ? ' WHERE ' . implode(' AND ', $cw) : '') . " GROUP BY type,category,currency ORDER BY category"); $q->execute($cp);
    $cash = ['in' => [], 'out' => []]; foreach ($q->fetchAll() as $r) { $k = $r['category'] ?: '-'; $cash[$r['type']][$k] = $cash[$r['type']][$k] ?? ['IDR' => 0, 'USD' => 0]; $cash[$r['type']][$k][$r['currency']] += $r['t']; }
    $TIN = ['IDR' => $S['recv']['IDR'] + $S['in']['IDR'], 'USD' => $S['recv']['USD'] + $S['in']['USD']];
    $BAL = ['IDR' => $TIN['IDR'] - $S['out']['IDR'], 'USD' => $TIN['USD'] - $S['out']['USD']];
    $num = function ($v, $c) { return $v ? money($v, $c) : '-'; };
    $signers = rows('signers'); $chair = $signers[0] ?? ['', 'Ketua Panitia']; ?>
<form method="get" class="filters noprint"><input type="hidden" name="p" value="laporan"><label class="chk">Periode dari <input type="date" name="from" value="<?= e($from) ?>"></label><label class="chk">sampai <input type="date" name="to" value="<?= e($to) ?>"></label><button class="btn">Tampilkan</button> <button type="button" class="btn ghost2" onclick="window.print()">Cetak / Simpan PDF</button></form>
<div class="card report">
<div class="rhead"><?php if (setting('logo_url')): ?><img src="<?= e(imgSrc(setting('logo_url'))) ?>" alt=""><?php endif; ?><div><b>LAPORAN KEUANGAN</b><br><?= e(setting('name')) ?><br><small>Periode: <?= $from ? e(fdate($from)) : 'awal' ?> s.d. <?= $to ? e(fdate($to)) : e(fdate(date('Y-m-d'))) ?> &middot; dicetak <?= e(fdate(date('Y-m-d'))) ?></small></div></div>
<table class="rtab"><tr><th>Uraian</th><th class="r">Jumlah</th><th class="r">IDR</th><th class="r">USD</th></tr>
<tr class="rsec"><td colspan="4">A. PEMASUKAN</td></tr>
<tr class="rsub"><td colspan="4">1. Pendaftaran peserta (terverifikasi)</td></tr>
<?php foreach ($regCat as $c => $v): ?><tr><td class="ind"><?= e($c) ?></td><td class="r"><?= $v['n'] ?> org</td><td class="r"><?= $num($v['IDR'], 'IDR') ?></td><td class="r"><?= $num($v['USD'], 'USD') ?></td></tr><?php endforeach; ?>
<?php if (!$regCat): ?><tr><td class="ind" colspan="4"><small>Belum ada pembayaran terverifikasi pada periode ini.</small></td></tr><?php endif; ?>
<tr class="rsub"><td colspan="4">2. Pemasukan lain</td></tr>
<?php foreach ($cash['in'] as $c => $v): ?><tr><td class="ind"><?= e($c) ?></td><td></td><td class="r"><?= $num($v['IDR'], 'IDR') ?></td><td class="r"><?= $num($v['USD'], 'USD') ?></td></tr><?php endforeach; ?>
<?php if (!$cash['in']): ?><tr><td class="ind" colspan="4"><small>-</small></td></tr><?php endif; ?>
<tr class="rtot"><td>Total Pemasukan</td><td></td><td class="r"><?= money($TIN['IDR']) ?></td><td class="r"><?= money($TIN['USD'], 'USD') ?></td></tr>
<tr class="rsec"><td colspan="4">B. PENGELUARAN</td></tr>
<?php foreach ($cash['out'] as $c => $v): ?><tr><td class="ind"><?= e($c) ?></td><td></td><td class="r"><?= $num($v['IDR'], 'IDR') ?></td><td class="r"><?= $num($v['USD'], 'USD') ?></td></tr><?php endforeach; ?>
<?php if (!$cash['out']): ?><tr><td class="ind" colspan="4"><small>-</small></td></tr><?php endif; ?>
<tr class="rtot"><td>Total Pengeluaran</td><td></td><td class="r"><?= money($S['out']['IDR']) ?></td><td class="r"><?= money($S['out']['USD'], 'USD') ?></td></tr>
<tr class="rgrand"><td>SALDO (A − B)</td><td></td><td class="r"><?= money($BAL['IDR']) ?></td><td class="r"><?= money($BAL['USD'], 'USD') ?></td></tr>
<?php if (usdRate() && ($BAL['USD'] || $TIN['USD'] || $S['out']['USD'])): ?><tr><td colspan="2"><small>Setara IDR (kurs 1 USD = <?= money(usdRate()) ?>)</small></td><td class="r" colspan="2"><b><?= money(idrTotal($BAL)) ?></b></td></tr><?php endif; ?>
<tr class="rsub"><td colspan="4">Catatan: tagihan belum diterima — menunggu verifikasi <?= $S['pendN'] ?> peserta (<?= $pair($S['pend']) ?>), belum bayar/ditolak <?= $S['unpaidN'] ?> peserta (<?= $pair($S['unpaid']) ?>).</td></tr>
</table>
<div class="rsign"><div><p>Mengetahui,<br><?= e($chair[1] ?? 'Ketua Panitia') ?></p><div class="sg"></div><p><b><u><?= e($chair[0] ?? '') ?: '(........................................)' ?></u></b></p></div>
<div><p><?= e(fdate(date('Y-m-d'))) ?><br>Bendahara</p><div class="sg"></div><p><b><u><?= $u['role'] === 'bendahara' ? e($u['name']) : '(........................................)' ?></u></b></p></div></div>
</div>

<?php else: ?>
<form method="post" class="card"><?= csrf() ?><input type="hidden" name="a" value="finset">
<label>Kurs 1 USD dalam Rupiah <small>(untuk total gabungan setara IDR; kosongkan bila tidak dipakai)</small><input name="usd_rate" inputmode="decimal" value="<?= usdRate() ? e(number_format(usdRate(), 0, ',', '.')) : '' ?>" placeholder="16.250"></label>
<label class="full">Kategori pengeluaran <small>(satu per baris)</small><textarea name="fin_cat_out" rows="8"><?= e(setting('fin_cat_out')) ?></textarea></label>
<label class="full">Kategori pemasukan lain <small>(satu per baris)</small><textarea name="fin_cat_in" rows="4"><?= e(setting('fin_cat_in')) ?></textarea></label>
<button class="btn">Simpan</button></form>
<p><small>Akun bendahara dibuat oleh admin di Panel Admin &rarr; Akun &rarr; Tambah Akun (peran Bendahara). Bendahara hanya dapat membuka halaman Keuangan, bukti bayar, invoice, dan kwitansi.</small></p>
<?php endif; foot();
