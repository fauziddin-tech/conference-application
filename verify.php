<?php require 'inc.php'; chk();
/* Verifikasi publik keaslian sertifikat / LoA / surat (melalui QR code atau kode tiket). */
$c = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)($_GET['c'] ?? ''))); $R = null; $err = '';
if ($c !== '') {
    if (throttled('verify:' . ip(), 30, 600)) $err = 'Terlalu banyak permintaan. Coba lagi nanti.';
    else {
        hit('verify:' . ip());
        $q = $pdo->prepare("SELECT r.*,u.institution FROM registrations r JOIN users u ON u.id=r.user_id WHERE r.ticket=?"); $q->execute([$c]); $R = $q->fetch();
        if (!$R && strpos($c, 'RV') === 0) { $q = $pdo->prepare("SELECT id,name,institution FROM users WHERE cert_code=? AND role='reviewer'"); $q->execute([$c]); $RVW = $q->fetch(); }
        if (!$R && empty($RVW) && preg_match('/^(SP|CM|MD)/', $c)) $OTH = findCertByCode($c);
        if (!$R && empty($RVW) && empty($OTH)) $err = 'Kode tidak ditemukan. Pastikan kode yang dimasukkan benar.';
    }
}
head('Verifikasi Dokumen'); ?><h2>Verifikasi Dokumen</h2>
<p>Masukkan kode tiket yang tercantum pada sertifikat, LoA, atau surat dari panitia, atau pindai QR code pada dokumen.</p>
<form method="get" class="card narrow"><label>Kode<input name="c" value="<?= e($c) ?>" required placeholder="mis. 3FA9C21B7D04" autocomplete="off"></label><button class="btn">Verifikasi</button></form>
<?php if ($err): ?><p class="msg err"><?= e($err) ?></p><?php endif; ?>
<?php if (!empty($OTH)): ?>
<div class="panel verify ok"><h3>&#10004; Sertifikat <?= e(strtolower($OTH[0])) ?> sah</h3>
<p><b><?= e($OTH[1]) ?></b><?= $OTH[2] ? '<br>' . e($OTH[2]) : '' ?></p><p>Peran: <b><?= e($OTH[3]) ?></b></p>
<p><small><?= e(setting('name')) ?> &middot; <?= e(setting('date')) ?></small></p></div><?php endif; ?>
<?php if (!empty($RVW)): $n = reviewerDone($RVW['id']); ?>
<div class="panel verify <?= $n ? 'ok' : '' ?>"><h3><?= $n ? '&#10004; Sertifikat reviewer sah' : 'Reviewer terdaftar' ?></h3>
<p><b><?= e($RVW['name']) ?></b><br><?= e($RVW['institution']) ?></p>
<p>Peran: <b>Reviewer</b> &middot; Naskah yang direview: <b><?= $n ?></b></p>
<p><small><?= e(setting('name')) ?> &middot; <?= e(setting('date')) ?></small></p></div><?php endif; ?>
<?php if ($R): $ok = $R['pay_status'] === 'verified'; ?>
<div class="panel verify <?= $ok ? 'ok' : '' ?>"><h3><?= $ok ? '&#10004; Terdaftar resmi' : 'Terdaftar &ndash; menunggu verifikasi' ?></h3>
<p><b><?= e(trim($R['prefix'] . ' ' . $R['full_name'])) ?></b><br><?= e($R['institution']) ?>, <?= e($R['country']) ?></p>
<p>No. registrasi: <?= e(no('R', $R['id'])) ?> &middot; Kategori: <?= e($R['category']) ?> (<?= e($R['attendance']) ?>)</p>
<?php if ($R['article_title'] && $R['paper_status'] === 'accepted'): ?><p>Naskah diterima: <i><?= e($R['article_title']) ?></i></p><?php endif; ?>
<p>Status pembayaran: <span class="badge <?= e($R['pay_status']) ?>"><?= e(lbl($PAY, $R['pay_status'])) ?></span><?php if (certReady($R)): ?> &middot; <span class="badge verified">Sertifikat diterbitkan</span><?php endif; ?></p>
<p><small><?= e(setting('name')) ?> &middot; <?= e(setting('date')) ?></small></p></div><?php endif; foot();
