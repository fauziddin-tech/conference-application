<?php if (!defined('ADMIN')) exit;
$st = (string)($_GET['st'] ?? ''); $par = []; $w = "r.article_title<>''";
if (isset($PAPER[$st])) { $w .= ' AND r.paper_status=?'; $par[] = $st; }
$q = $pdo->prepare("SELECT r.id,r.full_name,r.article_title,r.track,r.paper_status,r.pay_status,r.camera_file,COUNT(v.id) nrev,SUM(v.submitted_at IS NOT NULL) ndone,AVG(v.score) avg,GROUP_CONCAT(v.recommendation) recs FROM registrations r LEFT JOIN reviews v ON v.reg_id=r.id WHERE $w GROUP BY r.id ORDER BY r.id DESC"); $q->execute($par); $L = $q->fetchAll();
$nrev = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='reviewer'")->fetchColumn(); ?>
<p>Naskah: <b><?= count($L) ?></b> &middot; Reviewer terdaftar: <b><?= $nrev ?></b> (<a href="?p=pengguna">kelola</a>) &middot; <a href="?p=exportrev">Ekspor review (CSV)</a></p>
<p class="tabs small"><a href="?p=naskah" class="<?= $st === '' ? 'on' : '' ?>">Semua</a><?php foreach ($PAPER as $k => $l): ?><a href="?p=naskah&st=<?= $k ?>" class="<?= $st === $k ? 'on' : '' ?>"><?= e($l) ?></a><?php endforeach; ?></p>
<div class="scroll"><table><tr><th>ID</th><th>Judul</th><th>Penulis</th><th>Track</th><th>Review</th><th>Rata-rata</th><th>Rekomendasi</th><th>Status</th><th>Bayar</th><th></th></tr>
<?php foreach ($L as $x): ?><tr><td><?= no('R', $x['id']) ?></td><td><?= e($x['article_title']) ?><?= $x['camera_file'] ? ' <span class="tag on">final</span>' : '' ?></td><td><?= e($x['full_name']) ?></td><td><?= e($x['track']) ?></td><td><?= (int)$x['ndone'] ?>/<?= (int)$x['nrev'] ?></td><td><?= $x['avg'] !== null ? number_format((float)$x['avg'], 1) : '-' ?></td>
<td><small><?= e(implode(', ', array_map(function ($r) use ($REC) { return lbl($REC, $r); }, array_filter(explode(',', (string)$x['recs']))))) ?></small></td>
<td><span class="badge <?= e($x['paper_status']) ?>"><?= e(lbl($PAPER, $x['paper_status'])) ?></span></td><td><span class="badge <?= e($x['pay_status']) ?>"><?= e(lbl($PAY, $x['pay_status'])) ?></span></td><td><a href="?p=detail&id=<?= $x['id'] ?>">Kelola</a></td></tr><?php endforeach; ?></table></div>
