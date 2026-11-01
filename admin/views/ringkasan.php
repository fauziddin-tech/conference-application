<?php if (!defined('ADMIN')) exit;
$c = function ($sql) { global $pdo; return (int)$pdo->query($sql)->fetchColumn(); };
$S = [
    ['Akun peserta', $c("SELECT COUNT(*) FROM users WHERE role='peserta'"), '?p=pengguna'],
    ['Formulir terisi', $c("SELECT COUNT(*) FROM registrations"), '?p=peserta'],
    ['Bayar terverifikasi', $c("SELECT COUNT(*) FROM registrations WHERE pay_status='verified'"), '?p=peserta&pay=verified'],
    ['Perlu verifikasi bayar', $c("SELECT COUNT(*) FROM registrations WHERE pay_status='pending'"), '?p=peserta&pay=pending'],
    ['Belum bayar', $c("SELECT COUNT(*) FROM registrations WHERE pay_status IN('unpaid','rejected')"), '?p=peserta&pay=unpaid'],
    ['Naskah masuk', $c("SELECT COUNT(*) FROM registrations WHERE article_title<>''"), '?p=naskah'],
    ['Naskah diterima', $c("SELECT COUNT(*) FROM registrations WHERE article_title<>'' AND paper_status='accepted'"), '?p=naskah&st=accepted'],
    ['Review tertunda', $c("SELECT COUNT(*) FROM reviews WHERE submitted_at IS NULL"), '?p=naskah'],
    ['Check-in', $c("SELECT COUNT(*) FROM registrations WHERE checked_in_at IS NOT NULL"), '../checkin.php'],
    ['Negara', $c("SELECT COUNT(DISTINCT country) FROM registrations"), '#negara'],
];
$bar = function ($rows, $title, $id = '') { $max = max(array_merge([1], array_column($rows, 'n'))); ?>
<div class="card" <?= $id ? 'id="' . $id . '"' : '' ?>><h3><?= e($title) ?></h3><?php if (!$rows): ?><p><small>Belum ada data.</small></p><?php endif; ?><?php foreach ($rows as $r): ?><div class="bar"><span><?= e($r['k'] ?: '-') ?></span><i style="--w:<?= round($r['n'] / $max * 100) ?>%"></i><b><?= (int)$r['n'] ?><?= isset($r['v']) ? ' <small>(' . (int)$r['v'] . ' lunas)</small>' : '' ?></b></div><?php endforeach; ?></div>
<?php };
?>
<div class="stats"><?php foreach ($S as $s): ?><a class="stat" href="<?= $s[2] ?>"><b><?= $s[1] ?></b><span><?= e($s[0]) ?></span></a><?php endforeach; ?></div>
<?php if (setting('cert_open') !== '1' || !regOpen()): ?><p class="msg"><?= !regOpen() ? 'Pendaftaran sedang <b>ditutup</b>. ' : '' ?><?= setting('cert_open') !== '1' ? 'Unduhan sertifikat belum dibuka (aktifkan di Pengaturan setelah acara).' : '' ?></p><?php endif; ?>
<div class="dgrid">
<?php $bar($pdo->query("SELECT category k,COUNT(*) n,SUM(pay_status='verified') v FROM registrations GROUP BY category ORDER BY n DESC")->fetchAll(), 'Per kategori'); ?>
<?php $bar($pdo->query("SELECT country k,COUNT(*) n FROM registrations GROUP BY country ORDER BY n DESC LIMIT 12")->fetchAll(), 'Per negara (12 teratas)', 'negara'); ?>
<?php $bar($pdo->query("SELECT attendance k,COUNT(*) n,SUM(pay_status='verified') v FROM registrations GROUP BY attendance")->fetchAll(), 'Kehadiran'); ?>
<?php $bar(array_map(function ($r) use ($PAPER) { $r['k'] = lbl($PAPER, $r['k']); return $r; }, $pdo->query("SELECT paper_status k,COUNT(*) n FROM registrations WHERE article_title<>'' GROUP BY paper_status")->fetchAll()), 'Status naskah'); ?>
<?php $bar($pdo->query("SELECT track k,COUNT(*) n FROM registrations WHERE article_title<>'' GROUP BY track ORDER BY n DESC")->fetchAll(), 'Naskah per track'); ?>
<?php $bar($pdo->query("SELECT DATE_FORMAT(created_at,'%Y-%m') k,COUNT(*) n FROM registrations GROUP BY k ORDER BY k DESC LIMIT 6")->fetchAll(), 'Pendaftaran per bulan'); ?>
</div>
<h3>Pendaftaran terbaru</h3>
<div class="scroll"><table><tr><th>No.</th><th>Nama</th><th>Kategori</th><th>Bayar</th><th>Waktu</th></tr>
<?php foreach ($pdo->query("SELECT id,full_name,category,pay_status,created_at FROM registrations ORDER BY id DESC LIMIT 8") as $x): ?><tr><td><a href="?p=detail&id=<?= $x['id'] ?>"><?= no('R', $x['id']) ?></a></td><td><?= e($x['full_name']) ?></td><td><?= e($x['category']) ?></td><td><span class="badge <?= e($x['pay_status']) ?>"><?= e(lbl($PAY, $x['pay_status'])) ?></span></td><td><?= e($x['created_at']) ?></td></tr><?php endforeach; ?></table></div>
<p>Ekspor: <a href="?p=exportreg">Data registrasi (CSV)</a> &middot; <a href="?p=export">Akun (CSV)</a> &middot; <a href="?p=exportrev">Review (CSV)</a></p>
