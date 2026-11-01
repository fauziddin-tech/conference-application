<?php require 'inc.php'; $u = need('admin'); chk();
/* Check-in peserta di lokasi: pindai QR e-ticket (membuka halaman ini) atau cari manual. */
$c = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)($_REQUEST['c'] ?? '')));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    if (post('a') === 'in') $pdo->prepare("UPDATE registrations SET checked_in_at=NOW() WHERE id=? AND checked_in_at IS NULL")->execute([$id]);
    if (post('a') === 'undo') $pdo->prepare("UPDATE registrations SET checked_in_at=NULL WHERE id=?")->execute([$id]);
    flash(post('a') === 'in' ? 'Check-in berhasil.' : 'Check-in dibatalkan.');
    go('checkin.php?c=' . urlencode($c));
}
$R = null; $list = [];
if ($c !== '') {
    $q = $pdo->prepare("SELECT r.*,u.email,u.institution FROM registrations r JOIN users u ON u.id=r.user_id WHERE r.ticket=? OR CONCAT('R',LPAD(r.id,4,'0'))=?"); $q->execute([$c, $c]); $R = $q->fetch();
}
$s = trim((string)($_GET['q'] ?? ''));
if ($s !== '') { $q = $pdo->prepare("SELECT r.*,u.email,u.institution FROM registrations r JOIN users u ON u.id=r.user_id WHERE r.full_name LIKE ? OR u.email LIKE ? ORDER BY r.full_name LIMIT 30"); $q->execute(['%' . $s . '%', '%' . $s . '%']); $list = $q->fetchAll(); }
$tot = $pdo->query("SELECT COUNT(*) a,SUM(checked_in_at IS NOT NULL) b FROM registrations WHERE pay_status='verified' AND attendance='Offline'")->fetch();
head('Check-in'); ?><h2>Check-in Peserta</h2>
<p><a href="admin/">&larr; Panel admin</a> &middot; Sudah hadir: <b><?= (int)$tot['b'] ?></b> dari <b><?= (int)$tot['a'] ?></b> peserta offline terverifikasi.</p>
<p><small>Pindai QR pada e-ticket peserta dengan kamera ponsel (login sebagai admin) atau ketik kode tiket / nomor registrasi / nama.</small></p>
<div class="dgrid"><form method="get" class="card"><label>Kode tiket atau No. Reg (R0001)<input name="c" value="<?= e($c) ?>" autocomplete="off" autofocus></label><button class="btn">Cari</button></form>
<form method="get" class="card"><label>Nama atau email<input name="q" value="<?= e($s) ?>"></label><button class="btn">Cari</button></form></div>
<?php if ($c !== '' && !$R): ?><p class="msg err">Tiket tidak ditemukan.</p><?php endif; ?>
<?php if ($R): $ok = $R['pay_status'] === 'verified'; ?>
<div class="panel checkin <?= $R['checked_in_at'] ? 'ok' : ($ok ? '' : 'bad') ?>"><h3><?= e(trim($R['prefix'] . ' ' . $R['full_name'])) ?></h3>
<p><?= e($R['institution']) ?>, <?= e($R['country']) ?><br><?= e(no('R', $R['id'])) ?> &middot; <?= e($R['category']) ?> (<?= e($R['attendance']) ?>)<?= $R['dietary'] ? '<br>Kebutuhan khusus: <b>' . e($R['dietary']) . '</b>' : '' ?></p>
<p>Pembayaran: <span class="badge <?= e($R['pay_status']) ?>"><?= e(lbl($PAY, $R['pay_status'])) ?></span></p>
<?php if ($R['checked_in_at']): ?><p class="msg">&#10004; Sudah check-in pada <?= e(date('d M Y H:i', strtotime($R['checked_in_at']))) ?></p>
<form method="post"><?= csrf() ?><input type="hidden" name="a" value="undo"><input type="hidden" name="id" value="<?= $R['id'] ?>"><input type="hidden" name="c" value="<?= e($c) ?>"><button class="link">Batalkan check-in</button></form>
<?php else: ?><?php if (!$ok): ?><p class="msg err">Pembayaran belum terverifikasi. Pastikan status pembayaran sebelum check-in.</p><?php endif; ?>
<form method="post"><?= csrf() ?><input type="hidden" name="a" value="in"><input type="hidden" name="id" value="<?= $R['id'] ?>"><input type="hidden" name="c" value="<?= e($c) ?>"><button class="btn big">Check-in Sekarang</button></form><?php endif; ?></div><?php endif; ?>
<?php if ($list): ?><div class="scroll"><table><tr><th>No.</th><th>Nama</th><th>Institusi</th><th>Bayar</th><th>Hadir</th><th></th></tr><?php foreach ($list as $x): ?><tr><td><?= no('R', $x['id']) ?></td><td><?= e($x['full_name']) ?></td><td><?= e($x['institution']) ?></td><td><span class="badge <?= e($x['pay_status']) ?>"><?= e($x['pay_status']) ?></span></td><td><?= $x['checked_in_at'] ? '&#10004;' : '-' ?></td><td><a href="?c=<?= e($x['ticket']) ?>">Pilih</a></td></tr><?php endforeach; ?></table></div><?php endif; ?>
<?php foot();
