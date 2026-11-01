<?php require 'inc.php'; chk();
/* Verifikasi publik keaslian sertifikat / LoA / surat (melalui QR code atau kode tiket). */
$c = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)($_GET['c'] ?? ''))); $R = null; $err = '';
if ($c !== '') {
    if (throttled('verify:' . ip(), 30, 600)) $err = 'Terlalu banyak permintaan. Coba lagi nanti.';
    else {
        hit('verify:' . ip());
        $q = $pdo->prepare("SELECT r.*,u.institution FROM registrations r JOIN users u ON u.id=r.user_id WHERE r.ticket=?"); $q->execute([$c]); $R = $q->fetch();
        if (!$R) $err = 'Kode tidak ditemukan. Pastikan kode yang dimasukkan benar.';
    }
}
head('Verifikasi Dokumen'); ?><h2>Verifikasi Dokumen</h2>
<p>Masukkan kode tiket yang tercantum pada sertifikat, LoA, atau surat dari panitia, atau pindai QR code pada dokumen.</p>
<form method="get" class="card narrow"><label>Kode<input name="c" value="<?= e($c) ?>" required placeholder="mis. 3FA9C21B7D04" autocomplete="off"></label><button class="btn">Verifikasi</button></form>
<?php if ($err): ?><p class="msg err"><?= e($err) ?></p><?php endif; ?>
<?php if ($R): $ok = $R['pay_status'] === 'verified'; ?>
<div class="panel verify <?= $ok ? 'ok' : '' ?>"><h3><?= $ok ? '&#10004; Terdaftar resmi' : 'Terdaftar &ndash; menunggu verifikasi' ?></h3>
<p><b><?= e(trim($R['prefix'] . ' ' . $R['full_name'])) ?></b><br><?= e($R['institution']) ?>, <?= e($R['country']) ?></p>
<p>No. registrasi: <?= e(no('R', $R['id'])) ?> &middot; Kategori: <?= e($R['category']) ?> (<?= e($R['attendance']) ?>)</p>
<?php if ($R['article_title'] && $R['paper_status'] === 'accepted'): ?><p>Naskah diterima: <i><?= e($R['article_title']) ?></i></p><?php endif; ?>
<p>Status pembayaran: <span class="badge <?= e($R['pay_status']) ?>"><?= e(lbl($PAY, $R['pay_status'])) ?></span><?php if (certReady($R)): ?> &middot; <span class="badge verified">Sertifikat diterbitkan</span><?php endif; ?></p>
<p><small><?= e(setting('name')) ?> &middot; <?= e(setting('date')) ?></small></p></div><?php endif; foot();
