<?php if (!defined('ADMIN')) exit;
$qs = trim((string)($_GET['q'] ?? '')); $pay = (string)($_GET['pay'] ?? ''); $cat = (string)($_GET['cat'] ?? ''); $att = (string)($_GET['att'] ?? '');
$w = []; $par = [];
if ($qs !== '') { $w[] = "(r.full_name LIKE ? OR u.email LIKE ? OR u.institution LIKE ? OR r.article_title LIKE ? OR r.country LIKE ? OR r.ticket=? OR CONCAT('R',LPAD(r.id,4,'0'))=?)"; $par = array_merge(array_fill(0, 5, '%' . $qs . '%'), [strtoupper($qs), strtoupper($qs)]); }
if ($pay === 'unpaid') $w[] = "r.pay_status IN('unpaid','rejected')"; elseif (isset($PAY[$pay])) { $w[] = 'r.pay_status=?'; $par[] = $pay; }
if ($cat !== '') { $w[] = 'r.category=?'; $par[] = $cat; }
if (in_array($att, ['Online', 'Offline'], true)) { $w[] = 'r.attendance=?'; $par[] = $att; }
$sq = $pdo->prepare("SELECT r.*,u.email,u.institution FROM registrations r JOIN users u ON u.id=r.user_id " . ($w ? 'WHERE ' . implode(' AND ', $w) : '') . " ORDER BY r.id DESC"); $sq->execute($par); $rg = $sq->fetchAll();
$belum = (int)$pdo->query("SELECT COUNT(*) FROM users u WHERE u.role='peserta' AND NOT EXISTS(SELECT 1 FROM registrations r WHERE r.user_id=u.id)")->fetchColumn(); ?>
<p>Ditampilkan: <b><?= count($rg) ?></b> pendaftar<?php if ($belum): ?> &middot; <a href="?p=pengguna"><?= $belum ?> akun belum melengkapi pendaftaran</a><?php endif; ?> &middot; <a href="?p=exportreg">Ekspor CSV</a></p>
<form method="get" class="filters"><input type="hidden" name="p" value="peserta"><input name="q" value="<?= e($qs) ?>" placeholder="Cari nama, email, institusi, negara, judul, kode...">
<select name="pay"><option value="">Semua status bayar</option><?php foreach ($PAY as $k => $l): ?><option value="<?= $k ?>" <?= $pay === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
<select name="cat"><option value="">Semua kategori</option><?php foreach (fees() as $f): ?><option <?= $cat === $f['cat'] ? 'selected' : '' ?>><?= e($f['cat']) ?></option><?php endforeach; ?></select>
<select name="att"><option value="">Online &amp; offline</option><option <?= $att === 'Offline' ? 'selected' : '' ?>>Offline</option><option <?= $att === 'Online' ? 'selected' : '' ?>>Online</option></select>
<button class="btn">Filter</button><?php if ($w): ?> <a href="?p=peserta">Reset</a><?php endif; ?></form>
<div class="scroll"><table><tr><th>No. Reg</th><th>Nama</th><th>Email / HP</th><th>Institusi, Negara</th><th>Kategori</th><th>Hadir</th><th>Bayar</th><th>Naskah</th><th>Check-in</th><th></th></tr>
<?php foreach ($rg as $x): ?><tr><td><?= no('R', $x['id']) ?></td><td><?= e($x['prefix'] . ' ' . $x['full_name']) ?></td><td><?= e($x['email']) ?><br><small><?= e($x['phone']) ?></small></td><td><?= e($x['institution']) ?><br><small><?= e($x['country']) ?></small></td><td><?= e($x['category']) ?></td><td><?= e($x['attendance']) ?></td>
<td><span class="badge <?= e($x['pay_status']) ?>"><?= e(lbl($PAY, $x['pay_status'])) ?></span></td><td><?= $x['article_title'] ? '<span class="badge ' . e($x['paper_status']) . '">' . e(lbl($PAPER, $x['paper_status'])) . '</span>' : '-' ?></td><td><?= $x['checked_in_at'] ? '&#10004;' : '-' ?></td>
<td class="nowrap"><a href="?p=detail&id=<?= $x['id'] ?>">Detail</a> &nbsp; <form method="post" class="inline" data-confirm="Hapus peserta <?= no('R', $x['id']) ?> &ndash; <?= e($x['full_name']) ?> beserta akun, pembayaran<?= $x['pay_status'] === 'verified' ? ' (' . e(money($x['pay_received'], $x['pay_currency'] ?: 'IDR')) . ')' : '' ?>, bukti bayar, dan naskahnya? Tindakan ini tidak bisa dibatalkan."><?= csrf() ?><input type="hidden" name="a" value="del"><input type="hidden" name="id" value="<?= $x['user_id'] ?>"><input type="hidden" name="back" value="peserta"><button class="link">Hapus</button></form></td></tr><?php endforeach; ?></table></div>
