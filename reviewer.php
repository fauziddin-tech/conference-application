<?php require 'inc.php'; $u = need('reviewer'); chk();
/* Area reviewer: daftar naskah yang ditugaskan & formulir penilaian (blind review). */
$blind = setting('blind_review', '1') === '1';
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post('a') === 'pw') {
        $pw = (string)($_POST['password'] ?? '');
        if (!password_verify((string)($_POST['current'] ?? ''), $u['password'])) flash('Password saat ini salah.');
        elseif (strlen($pw) < 8) flash('Password baru minimal 8 karakter.');
        else { $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($pw, PASSWORD_DEFAULT), $u['id']]); flash('Password diubah.'); }
        go('reviewer.php');
    }
    $q = $pdo->prepare("SELECT v.id,r.paper_status FROM reviews v JOIN registrations r ON r.id=v.reg_id WHERE v.reg_id=? AND v.reviewer_id=?"); $q->execute([$id, $u['id']]); $V = $q->fetch();
    if (!$V) { http_response_code(403); die('Akses ditolak'); }
    if (in_array($V['paper_status'], ['accepted', 'rejected'], true)) { flash('Keputusan akhir sudah dibuat; review tidak dapat diubah.'); go('reviewer.php?id=' . $id); }
    $sc = (int)($_POST['score'] ?? 0); $rec = post('recommendation');
    if ($sc < 1 || $sc > 5 || !isset($REC[$rec]) || post('to_author') === '') { flash('Lengkapi skor, rekomendasi, dan komentar untuk penulis.'); go('reviewer.php?id=' . $id); }
    $pdo->prepare("UPDATE reviews SET score=?,recommendation=?,to_author=?,to_committee=?,submitted_at=NOW() WHERE id=?")->execute([$sc, $rec, post('to_author'), post('to_committee'), $V['id']]);
    flash('Review tersimpan. Terima kasih!'); go('reviewer.php');
}
head('Review Naskah');
if ($id):
    $q = $pdo->prepare("SELECT r.*,v.score,v.recommendation,v.to_author,v.to_committee,v.submitted_at FROM reviews v JOIN registrations r ON r.id=v.reg_id WHERE v.reg_id=? AND v.reviewer_id=?"); $q->execute([$id, $u['id']]); $x = $q->fetch();
    if (!$x): ?><p class="msg err">Naskah tidak ditemukan atau tidak ditugaskan kepada Anda. <a href="reviewer.php">Kembali</a></p><?php else:
    $final = in_array($x['paper_status'], ['accepted', 'rejected'], true); ?>
<p><a href="reviewer.php">&larr; Daftar naskah</a></p>
<h2>Paper <?= no('R', $x['id']) ?></h2>
<div class="panel"><h3><?= e($x['article_title']) ?></h3>
<?php if (!$blind): ?><p><b>Penulis:</b> <?= e(trim($x['prefix'] . ' ' . $x['full_name'])) ?>, <?= e($x['country']) ?><?= $x['coauthors'] ? '<br><b>Co-authors:</b> ' . nl2br(e($x['coauthors'])) : '' ?></p><?php endif; ?>
<?php if ($x['track']): ?><p><b>Track:</b> <?= e($x['track']) ?></p><?php endif; ?>
<p><b>Keywords:</b> <?= e($x['keywords']) ?></p><p><b>Abstract</b><br><?= nl2br(e($x['abstract'])) ?></p>
<p><a class="btn" href="file.php?id=<?= $x['id'] ?>&k=article">&#8681; Unduh naskah</a></p></div>
<h3>Formulir Review</h3>
<?php if ($final): ?><p class="msg">Keputusan akhir: <b><?= e(lbl($PAPER, $x['paper_status'])) ?></b>. Review terkunci.</p><?php endif; ?>
<form method="post" class="card"><?= csrf() ?><input type="hidden" name="id" value="<?= $x['id'] ?>">
<label>Skor keseluruhan *<select name="score" required <?= $final ? 'disabled' : '' ?>><option value="">--</option><?php foreach ([5 => '5 - Excellent', 4 => '4 - Good', 3 => '3 - Fair', 2 => '2 - Poor', 1 => '1 - Very poor'] as $k => $l): ?><option value="<?= $k ?>" <?= (int)$x['score'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></label>
<label>Rekomendasi *<select name="recommendation" required <?= $final ? 'disabled' : '' ?>><option value="">--</option><?php foreach ($REC as $k => $l): ?><option value="<?= $k ?>" <?= $x['recommendation'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
<label class="full">Komentar untuk penulis * <small>(akan ditampilkan ke penulis tanpa identitas reviewer)</small><textarea name="to_author" rows="8" required <?= $final ? 'disabled' : '' ?>><?= e($x['to_author']) ?></textarea></label>
<label class="full">Catatan rahasia untuk panitia <small>(tidak ditampilkan ke penulis)</small><textarea name="to_committee" rows="4" <?= $final ? 'disabled' : '' ?>><?= e($x['to_committee']) ?></textarea></label>
<?php if (!$final): ?><button class="btn"><?= $x['submitted_at'] ? 'Perbarui Review' : 'Kirim Review' ?></button><?php endif; ?></form>
<?php endif; else:
    $q = $pdo->prepare("SELECT r.id,r.article_title,r.track,r.paper_status,v.submitted_at,v.recommendation,v.created_at FROM reviews v JOIN registrations r ON r.id=v.reg_id WHERE v.reviewer_id=? ORDER BY v.submitted_at IS NOT NULL, v.created_at DESC"); $q->execute([$u['id']]); $L = $q->fetchAll();
    $todo = count(array_filter($L, function ($r) { return !$r['submitted_at']; })); ?>
<h2>Review Naskah</h2><p>Halo, <b><?= e($u['name']) ?></b>. Ada <b><?= $todo ?></b> naskah menunggu review Anda dari total <?= count($L) ?> penugasan.<?= $blind ? ' Proses review bersifat <i>blind</i>: identitas penulis disembunyikan.' : '' ?></p>
<?php if (!$L): ?><p class="panel">Belum ada naskah yang ditugaskan.</p><?php else: ?>
<div class="scroll"><table><tr><th>ID</th><th>Judul</th><th>Track</th><th>Review Anda</th><th>Status naskah</th><th></th></tr>
<?php foreach ($L as $x): ?><tr><td><?= no('R', $x['id']) ?></td><td><?= e($x['article_title']) ?></td><td><?= e($x['track']) ?></td><td><?= $x['submitted_at'] ? '<span class="badge verified">' . e(lbl($REC, $x['recommendation'])) . '</span>' : '<span class="badge pending">Belum</span>' ?></td><td><?= e(lbl($PAPER, $x['paper_status'])) ?></td><td><a href="?id=<?= $x['id'] ?>"><?= $x['submitted_at'] ? 'Lihat' : 'Review' ?></a></td></tr><?php endforeach; ?></table></div><?php endif; ?>
<h3>Ganti Password</h3><form method="post" class="card"><?= csrf() ?><input type="hidden" name="a" value="pw"><label>Password saat ini<input type="password" name="current" required></label><label>Password baru (min. 8)<input type="password" name="password" required minlength="8"></label><button class="btn">Simpan</button></form>
<?php endif; foot();
