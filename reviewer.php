<?php require 'inc.php'; $u = need('reviewer'); chk();
/* Area reviewer: naskah yang ditugaskan, riwayat versi & ronde, formulir review + file berkomentar (blind review). */
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
    $q = $pdo->prepare("SELECT v.id,v.file,v.submitted_at,r.paper_status,r.review_round FROM reviews v JOIN registrations r ON r.id=v.reg_id WHERE v.reg_id=? AND v.reviewer_id=? AND v.round=r.review_round"); $q->execute([$id, $u['id']]); $V = $q->fetch();
    if (!$V) { http_response_code(403); die('Akses ditolak'); }
    $back = 'reviewer.php?id=' . $id;
    if (!in_array($V['paper_status'], ['submitted', 'under_review'], true)) { flash('Ronde review ini sudah diputuskan panitia; review tidak dapat diubah.'); go($back); }
    $sc = (int)($_POST['score'] ?? 0); $rec = post('recommendation'); $txt = post('to_author');
    $hasFile = !empty($_FILES['rfile']['name']);
    if ($sc < 1 || $sc > 5 || !isset($REC[$rec])) { flash('Lengkapi skor dan rekomendasi.'); go($back); }
    if ($txt === '' && !$hasFile && !$V['file']) { flash('Isi komentar untuk penulis atau unggah file naskah yang berisi komentar.'); go($back); }
    $file = null;
    if ($hasFile) { $e = ''; $file = upFile($_FILES['rfile'], ['doc', 'docx', 'pdf'], $e, 'File komentar'); if (!$file) { flash($e); go($back); } rmPriv($V['file']); }
    if ($txt === '') $txt = 'Komentar dituliskan langsung di dalam file terlampir.';
    $pdo->prepare("UPDATE reviews SET score=?,recommendation=?,to_author=?,to_committee=?,submitted_at=NOW()" . ($file ? ",file=?,file_name=?" : '') . " WHERE id=?")
        ->execute(array_merge([$sc, $rec, $txt, post('to_committee')], $file ? [$file[0], $file[1]] : [], [$V['id']]));
    $pdo->prepare("UPDATE registrations SET paper_status='under_review' WHERE id=? AND paper_status='submitted'")->execute([$id]);
    notifyAdmins(($V['submitted_at'] ? 'Review diperbarui' : 'Review masuk') . ' untuk ' . no('R', $id) . ' (ronde ' . $V['review_round'] . ') oleh ' . $u['name'] . ': ' . lbl($REC, $rec), 'admin/?p=detail&id=' . $id);
    flash('Review tersimpan. Terima kasih!'); go('reviewer.php');
}
head('Review Naskah');
if ($id):
    $q = $pdo->prepare("SELECT r.* FROM registrations r WHERE r.id=? AND EXISTS(SELECT 1 FROM reviews v WHERE v.reg_id=r.id AND v.reviewer_id=?)"); $q->execute([$id, $u['id']]); $x = $q->fetch();
    if (!$x): ?><p class="msg err">Naskah tidak ditemukan atau tidak ditugaskan kepada Anda. <a href="reviewer.php">Kembali</a></p><?php else:
    $mine = reviewsOf($id, (int)$x['review_round'], $u['id']); $V = $mine[0] ?? null;
    $open = $V && in_array($x['paper_status'], ['submitted', 'under_review'], true); ?>
<p><a href="reviewer.php">&larr; Daftar naskah</a></p>
<h2>Paper <?= no('R', $x['id']) ?> &middot; Ronde <?= (int)$x['review_round'] ?></h2>
<div class="panel"><h3><?= e($x['article_title']) ?></h3>
<?php if (!$blind): ?><p><b>Penulis:</b> <?= e(trim($x['prefix'] . ' ' . $x['full_name'])) ?>, <?= e($x['country']) ?><?= $x['coauthors'] ? '<br><b>Co-authors:</b> ' . nl2br(e($x['coauthors'])) : '' ?></p><?php endif; ?>
<?php if ($x['track']): ?><p><b>Track:</b> <?= e($x['track']) ?></p><?php endif; ?>
<p><b>Keywords:</b> <?= e($x['keywords']) ?></p><p><b>Abstract</b><br><?= nl2br(e($x['abstract'])) ?></p>
<p><a class="btn" href="file.php?id=<?= $x['id'] ?>&k=article">&#8681; Unduh naskah terbaru</a><?= $V && $V['due_date'] ? ' &nbsp; Tenggat review: <b>' . e(fdate($V['due_date'])) . '</b>' . ($V['due_date'] < date('Y-m-d') && !$V['submitted_at'] ? ' <span class="tag spam">terlambat</span>' : '') : '' ?></p></div>

<h3>Formulir Review &ndash; Ronde <?= (int)$x['review_round'] ?></h3>
<?php if (!$V): ?><p class="msg">Anda tidak ditugaskan pada ronde ini. Riwayat review Anda di ronde sebelumnya ada di bawah.</p>
<?php else: if (!$open): ?><p class="msg">Keputusan panitia untuk ronde ini: <b><?= e(lbl($PAPER, $x['paper_status'])) ?></b>. Review terkunci.</p><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="card"><?= csrf() ?><input type="hidden" name="id" value="<?= $x['id'] ?>">
<label>Skor keseluruhan *<select name="score" required <?= $open ? '' : 'disabled' ?>><option value="">--</option><?php foreach ([5 => '5 - Excellent', 4 => '4 - Good', 3 => '3 - Fair', 2 => '2 - Poor', 1 => '1 - Very poor'] as $k => $l): ?><option value="<?= $k ?>" <?= (int)$V['score'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></label>
<label>Rekomendasi *<select name="recommendation" required <?= $open ? '' : 'disabled' ?>><option value="">--</option><?php foreach ($REC as $k => $l): ?><option value="<?= $k ?>" <?= $V['recommendation'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
<label class="full">Komentar untuk penulis <small>(ditampilkan ke penulis tanpa identitas Anda; boleh kosong bila komentar ditulis di file)</small><textarea name="to_author" rows="8" <?= $open ? '' : 'disabled' ?>><?= e($V['to_author']) ?></textarea></label>
<label>File naskah berisi komentar <small>(opsional; DOC/DOCX/PDF maks. 10 MB. Tulis komentar dengan fitur <i>Comment</i>/<i>Track Changes</i> di Word. <?= $blind ? 'Hapus nama Anda dari properti dokumen agar tetap anonim.' : '' ?>)</small><input type="file" name="rfile" accept=".doc,.docx,.pdf" <?= $open ? '' : 'disabled' ?>><?php if ($V['file']): ?><small>File terkirim: <a href="file.php?rv=<?= $V['id'] ?>"><?= e($V['file_name']) ?></a><?= $open ? ' (unggah lagi untuk mengganti)' : '' ?></small><?php endif; ?></label>
<label class="full">Catatan rahasia untuk panitia <small>(tidak ditampilkan ke penulis)</small><textarea name="to_committee" rows="3" <?= $open ? '' : 'disabled' ?>><?= e($V['to_committee']) ?></textarea></label>
<?php if ($open): ?><button class="btn"><?= $V['submitted_at'] ? 'Perbarui Review' : 'Kirim Review' ?></button><?php endif; ?></form><?php endif; ?>

<h3>Riwayat Versi &amp; Review Anda</h3>
<?php paperHistory($x, 'reviewer', $u['id']); ?>
<?php endif; else:
    $q = $pdo->prepare("SELECT r.id,r.article_title,r.track,r.paper_status,r.review_round,v.submitted_at,v.recommendation,v.due_date FROM reviews v JOIN registrations r ON r.id=v.reg_id AND v.round=r.review_round WHERE v.reviewer_id=? ORDER BY v.submitted_at IS NOT NULL, v.due_date, v.created_at DESC"); $q->execute([$u['id']]); $L = $q->fetchAll();
    $todo = count(array_filter($L, function ($r) { return !$r['submitted_at'] && in_array($r['paper_status'], ['submitted', 'under_review'], true); })); ?>
<h2>Review Naskah</h2><p>Halo, <b><?= e($u['name']) ?></b>. Ada <b><?= $todo ?></b> naskah menunggu review Anda dari total <?= count($L) ?> penugasan.<?= $blind ? ' Proses review bersifat <i>blind</i>: identitas penulis disembunyikan.' : '' ?></p>
<?php if (!$L): ?><p class="panel">Belum ada naskah yang ditugaskan.</p><?php else: ?>
<div class="scroll"><table><tr><th>ID</th><th>Judul</th><th>Track</th><th>Ronde</th><th>Tenggat</th><th>Review Anda</th><th>Status naskah</th><th></th></tr>
<?php foreach ($L as $x): $late = !$x['submitted_at'] && $x['due_date'] && $x['due_date'] < date('Y-m-d'); ?><tr><td><?= no('R', $x['id']) ?></td><td><?= e($x['article_title']) ?></td><td><?= e($x['track']) ?></td><td><?= (int)$x['review_round'] ?></td><td><?= $x['due_date'] ? e(fdate($x['due_date'])) . ($late ? ' <span class="tag spam">terlambat</span>' : '') : '-' ?></td>
<td><?= $x['submitted_at'] ? '<span class="badge verified">' . e(lbl($REC, $x['recommendation'])) . '</span>' : '<span class="badge pending">Belum</span>' ?></td><td><?= e(lbl($PAPER, $x['paper_status'])) ?></td><td><a href="?id=<?= $x['id'] ?>"><?= $x['submitted_at'] ? 'Lihat' : 'Review' ?></a></td></tr><?php endforeach; ?></table></div><?php endif; ?>
<h3>Ganti Password</h3><form method="post" class="card"><?= csrf() ?><input type="hidden" name="a" value="pw"><label>Password saat ini<input type="password" name="current" required></label><label>Password baru (min. 8)<input type="password" name="password" required minlength="8"></label><button class="btn">Simpan</button></form>
<?php endif; foot();
