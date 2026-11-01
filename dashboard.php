<?php require 'inc.php'; $u = need('peserta'); chk();
$R = regOf($u['id']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = post('a');
    if ($a === 'profile') {
        $n = post('name');
        if (!validName($n)) { flash('Nama hanya boleh berisi huruf, spasi, titik, koma, dan tanda hubung.'); go('dashboard.php#profil'); }
        $pdo->prepare("UPDATE users SET name=?,institution=?,phone=? WHERE id=?")->execute([$n, post('institution'), post('phone'), $u['id']]);
        $pw = (string)($_POST['password'] ?? '');
        if ($pw !== '') {
            if (!password_verify((string)($_POST['current'] ?? ''), $u['password'])) { flash('Password saat ini salah; password tidak diubah.'); go('dashboard.php#profil'); }
            if (strlen($pw) < 8) { flash('Password baru minimal 8 karakter; password tidak diubah.'); go('dashboard.php#profil'); }
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($pw, PASSWORD_DEFAULT), $u['id']]);
        }
        flash('Profil diperbarui.'); go('dashboard.php#profil');
    }
    // Unggah naskah revisi / camera-ready (tetap bisa walau pembayaran sudah terverifikasi)
    if (in_array($a, ['revision', 'camera'], true) && $R && $R['article_title']) {
        $ok = $a === 'revision' ? $R['paper_status'] === 'revision' : $R['paper_status'] === 'accepted';
        if (!$ok) { flash('Unggahan tidak tersedia untuk status naskah saat ini.'); go('dashboard.php'); }
        $e = ''; $file = upFile($_FILES['file'] ?? null, ['pdf', 'doc', 'docx'], $e, $a === 'revision' ? 'Revised manuscript' : 'Camera-ready');
        if (!$file) { flash($e); go('dashboard.php#paper'); }
        if ($a === 'revision') {
            rmPriv($R['article_file']);
            $pdo->prepare("UPDATE registrations SET article_file=?,article_name=?,paper_status='submitted' WHERE id=?")->execute([$file[0], $file[1], $R['id']]);
            // reviewer diminta menilai ulang naskah revisi
            $pdo->prepare("UPDATE reviews SET submitted_at=NULL WHERE reg_id=?")->execute([$R['id']]);
            flash('Naskah revisi terkirim dan akan direview ulang.');
        } else {
            rmPriv($R['camera_file']);
            $pdo->prepare("UPDATE registrations SET camera_file=?,camera_name=? WHERE id=?")->execute([$file[0], $file[1], $R['id']]);
            flash('Naskah final (camera-ready) terkirim. Terima kasih.');
        }
        go('dashboard.php#paper');
    }
    go('dashboard.php');
}
$pres = $R && $R['article_title'];
$verified = $R && $R['pay_status'] === 'verified';
$news = items('news');
head('Dashboard'); ?>
<h2>Dashboard Peserta</h2>
<p>Halo, <b><?= e($R ? trim($R['prefix'] . ' ' . $R['full_name']) : $u['name']) ?></b> &middot; ID Akun: <b><?= no('U', $u['id']) ?></b><?php if ($R): ?> &middot; No. Registrasi: <b><?= no('R', $R['id']) ?></b><?php endif; ?></p>

<?php
// --- Progres pendaftaran ---
$st = [];
$st[] = ['Akun dibuat', 'done', ''];
$st[] = ['Formulir pendaftaran', $R ? 'done' : 'now', $R ? '' : 'Lengkapi formulir'];
if ($pres) {
    $ps = $R['paper_status'];
    $st[] = ['Review naskah', $ps === 'accepted' ? 'done' : ($ps === 'rejected' ? 'fail' : 'now'), lbl($PAPER, $ps)];
}
$p = $R['pay_status'] ?? 'unpaid';
$st[] = ['Pembayaran', $p === 'verified' ? 'done' : ($p === 'rejected' ? 'fail' : ($R ? 'now' : '')), $R ? lbl($PAY, $p) : ''];
if ($R && $R['attendance'] === 'Offline') $st[] = ['Check-in acara', $R['checked_in_at'] ? 'done' : '', $R['checked_in_at'] ? date('d/m H:i', strtotime($R['checked_in_at'])) : ''];
$st[] = ['Sertifikat', certReady($R) ? 'done' : '', certReady($R) ? 'Siap diunduh' : ''];
?>
<ol class="progress"><?php foreach ($st as $s): ?><li class="<?= $s[1] ?>"><b><?= e($s[0]) ?></b><?php if ($s[2]): ?><small><?= e($s[2]) ?></small><?php endif; ?></li><?php endforeach; ?></ol>

<?php if (!$R): ?><div class="panel"><p>Anda belum melengkapi formulir pendaftaran (data peserta, naskah untuk presenter, dan pembayaran).</p><?php if (regOpen()): ?><a class="btn" href="registrasi.php">Lengkapi Pendaftaran</a><?php else: ?><p class="msg"><?= e(setting('reg_close_msg')) ?></p><?php endif; ?></div>
<?php else: ?>
<div class="dgrid">
<div class="panel"><h3>Pendaftaran</h3>
<p>Kategori: <b><?= e($R['category']) ?></b><br>Kehadiran: <b><?= e($R['attendance']) ?></b><br>Biaya: <b><?= e($R['pay_amount']) ?></b></p>
<p>Pembayaran: <span class="badge <?= e($R['pay_status']) ?>"><?= e(lbl($PAY, $R['pay_status'])) ?></span></p>
<?php if (in_array($R['pay_status'], ['unpaid', 'rejected'], true)): ?><p class="msg"><?= $R['pay_status'] === 'rejected' ? 'Bukti pembayaran ditolak. Periksa catatan panitia lalu unggah ulang.' : 'Silakan lakukan pembayaran sesuai invoice lalu unggah bukti bayar.' ?></p><?php endif; ?>
<?php if ($R['admin_note']): ?><p><b>Catatan panitia:</b><br><?= nl2br(e($R['admin_note'])) ?></p><?php endif; ?>
<?php if (!$verified): ?><a class="btn" href="registrasi.php"><?= in_array($R['pay_status'], ['unpaid', 'rejected'], true) ? 'Unggah Bukti Bayar / Ubah Data' : 'Ubah Data Pendaftaran' ?></a><?php endif; ?></div>

<div class="panel"><h3>Dokumen</h3><ul class="docs">
<li><a href="doc.php?t=invoice" target="_blank">Invoice</a></li>
<?php if ($verified): ?><li><a href="doc.php?t=receipt" target="_blank">Kwitansi / Payment receipt</a></li>
<li><a href="doc.php?t=badge" target="_blank">E-ticket &amp; name badge (QR)</a></li>
<li><a href="doc.php?t=invitation" target="_blank">Invitation letter (visa / izin)</a></li><?php endif; ?>
<?php if ($pres && $R['paper_status'] === 'accepted'): ?><li><a href="doc.php?t=loa" target="_blank">Letter of Acceptance (LoA)</a></li><?php endif; ?>
<?php if (certReady($R)): ?><li><a href="doc.php?t=cert" target="_blank"><b>E-Certificate</b></a></li><?php endif; ?>
</ul><?php if (!$verified): ?><p><small>Kwitansi, e-ticket, dan surat undangan tersedia setelah pembayaran terverifikasi.</small></p><?php elseif (!certReady($R)): ?><p><small>E-certificate tersedia setelah acara<?= $R['attendance'] === 'Offline' && setting('cert_checkin', '1') === '1' ? ' (wajib check-in di lokasi dengan menunjukkan QR e-ticket)' : '' ?> dan dibuka oleh panitia.</small></p><?php endif; ?></div>

<?php if ($verified && $R['attendance'] === 'Online' && (setting('online_link') || setting('online_info'))): ?>
<div class="panel"><h3>Akses Online</h3><?php if (setting('online_info')): ?><p><?= nl2br(e(setting('online_info'))) ?></p><?php endif; ?><?php if (isurl(setting('online_link'))): ?><p><a class="btn" href="<?= e(setting('online_link')) ?>" target="_blank" rel="noopener">Gabung Sesi Online</a></p><?php endif; ?></div><?php endif; ?>
</div>

<?php if ($pres): ?><h3 id="paper">Naskah</h3><div class="panel">
<p><i><?= e($R['article_title']) ?></i><?= $R['track'] ? '<br><small>Track: ' . e($R['track']) . '</small>' : '' ?></p>
<p>Status: <span class="badge <?= e($R['paper_status']) ?>"><?= e(lbl($PAPER, $R['paper_status'])) ?></span> &middot; File: <a href="file.php?id=<?= $R['id'] ?>&k=article"><?= e($R['article_name']) ?></a><?php if ($R['camera_file']): ?> &middot; Camera-ready: <a href="file.php?id=<?= $R['id'] ?>&k=camera"><?= e($R['camera_name']) ?></a><?php endif; ?></p>
<?php if (in_array($R['paper_status'], ['revision', 'accepted', 'rejected'], true)):
    $rv = $pdo->prepare("SELECT score,recommendation,to_author FROM reviews WHERE reg_id=? AND submitted_at IS NOT NULL AND to_author<>'' ORDER BY id"); $rv->execute([$R['id']]); $rv = $rv->fetchAll();
    if ($rv): ?><h4>Komentar reviewer</h4><?php foreach ($rv as $i => $r): ?><div class="review"><b>Reviewer <?= $i + 1 ?></b><p><?= nl2br(e($r['to_author'])) ?></p></div><?php endforeach; endif; endif; ?>
<?php if ($R['paper_status'] === 'revision'): ?><form method="post" enctype="multipart/form-data" class="inline-form"><?= csrf() ?><input type="hidden" name="a" value="revision"><label>Unggah naskah revisi (PDF/DOC/DOCX, maks. 10 MB)<input type="file" name="file" accept=".pdf,.doc,.docx" required></label><button class="btn">Kirim Revisi</button></form><?php endif; ?>
<?php if ($R['paper_status'] === 'accepted'): ?><form method="post" enctype="multipart/form-data" class="inline-form"><?= csrf() ?><input type="hidden" name="a" value="camera"><label>Unggah naskah final / camera-ready (PDF/DOC/DOCX)<input type="file" name="file" accept=".pdf,.doc,.docx" required></label><button class="btn"><?= $R['camera_file'] ? 'Ganti File Final' : 'Kirim File Final' ?></button></form><?php endif; ?>
</div><?php endif; ?>
<?php endif; ?>

<?php if ($news): ?><h3>Pengumuman</h3><?php foreach (array_slice($news, 0, 5) as $n): ?><div class="panel news-item"><small><?= e(fdate($n['created_at'])) ?></small><h4><?= e($n['title']) ?></h4><p><?= nl2br(e($n['body'])) ?></p><?php if (isurl($n['url'])): ?><a href="<?= e($n['url']) ?>" target="_blank" rel="noopener">Selengkapnya &rarr;</a><?php endif; ?></div><?php endforeach; ?><?php endif; ?>

<h3>Agenda</h3><?php agenda(); ?>

<h3 id="profil">Profil &amp; Keamanan</h3><form method="post" class="card"><?= csrf() ?><input type="hidden" name="a" value="profile">
<label>Email<input value="<?= e($u['email']) ?>" disabled></label>
<label>Nama<input name="name" required value="<?= e($u['name']) ?>"></label>
<label>Institusi<input name="institution" value="<?= e($u['institution']) ?>"></label>
<label>No. HP<input name="phone" value="<?= e($u['phone']) ?>"></label>
<label>Password saat ini (wajib jika mengganti password)<input type="password" name="current" autocomplete="current-password"></label>
<label>Password baru (kosongkan jika tidak diubah)<input type="password" name="password" minlength="8" autocomplete="new-password"></label>
<button class="btn">Simpan</button></form><?php foot();
