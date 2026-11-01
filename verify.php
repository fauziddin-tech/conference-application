<?php require 'inc.php'; chk();
/* Verifikasi publik keaslian sertifikat / LoA / surat (melalui QR code atau kode tiket). */
$c = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)($_GET['c'] ?? ''))); $R = null; $err = '';
if ($c !== '') {
    if (throttled('verify:' . ip(), 30, 600)) $err = t('Too many requests. Please try again later.');
    else {
        hit('verify:' . ip());
        $q = $pdo->prepare("SELECT r.*,u.institution FROM registrations r JOIN users u ON u.id=r.user_id WHERE r.ticket=?"); $q->execute([$c]); $R = $q->fetch();
        if (!$R && strpos($c, 'RV') === 0) { $q = $pdo->prepare("SELECT id,name,institution FROM users WHERE cert_code=? AND role='reviewer'"); $q->execute([$c]); $RVW = $q->fetch(); }
        if (!$R && empty($RVW) && preg_match('/^(SP|CM|MD)/', $c)) $OTH = findCertByCode($c);
        if (!$R && empty($RVW) && empty($OTH)) $err = t('Code not found. Please check the code you entered.');
    }
}
head(t('Document Verification')); ?><h2><?= te('Document Verification') ?></h2>
<p><?= te('Enter the code printed on the certificate, LoA or letter from the committee, or scan the QR code on the document.') ?></p>
<form method="get" class="card narrow"><label><?= te('Code') ?><input name="c" value="<?= e($c) ?>" required placeholder="<?= te('e.g.') ?> 3FA9C21B7D04" autocomplete="off"></label><button class="btn"><?= te('Verify') ?></button></form>
<?php if ($err): ?><p class="msg err"><?= e($err) ?></p><?php endif; ?>
<?php if (!empty($OTH)): ?>
<div class="panel verify ok"><h3>&#10004; <?= te('Valid %s certificate', t(['Pembicara' => 'speaker', 'Panitia' => 'committee', 'Moderator' => 'moderator'][$OTH[0]] ?? $OTH[0])) ?></h3>
<p><b><?= e($OTH[1]) ?></b><?= $OTH[2] ? '<br>' . e($OTH[2]) : '' ?></p><p><?= te('Role') ?>: <b><?= e($OTH[3]) ?></b></p>
<p><small><?= e(setting('name')) ?> &middot; <?= e(tx(setting('date'))) ?></small></p></div><?php endif; ?>
<?php if (!empty($RVW)): $n = reviewerDone($RVW['id']); ?>
<div class="panel verify <?= $n ? 'ok' : '' ?>"><h3><?= $n ? '&#10004; ' . te('Valid %s certificate', t('reviewer')) : te('Registered reviewer') ?></h3>
<p><b><?= e($RVW['name']) ?></b><br><?= e($RVW['institution']) ?></p>
<p><?= te('Role') ?>: <b>Reviewer</b> &middot; <?= te('Papers reviewed') ?>: <b><?= $n ?></b></p>
<p><small><?= e(setting('name')) ?> &middot; <?= e(tx(setting('date'))) ?></small></p></div><?php endif; ?>
<?php if ($R): $ok = $R['pay_status'] === 'verified'; ?>
<div class="panel verify <?= $ok ? 'ok' : '' ?>"><h3><?= $ok ? '&#10004; ' . te('Officially registered') : te('Registered – awaiting verification') ?></h3>
<p><b><?= e(trim($R['prefix'] . ' ' . $R['full_name'])) ?></b><br><?= e($R['institution']) ?>, <?= e($R['country']) ?></p>
<p><?= te('Registration No.') ?>: <?= e(no('R', $R['id'])) ?> &middot; <?= te('Category') ?>: <?= e(tx($R['category'])) ?> (<?= e($R['attendance']) ?>)</p>
<?php if ($R['article_title'] && $R['paper_status'] === 'accepted'): ?><p><?= te('Accepted paper') ?>: <i><?= e($R['article_title']) ?></i></p><?php endif; ?>
<p><?= te('Payment status') ?>: <span class="badge <?= e($R['pay_status']) ?>"><?= e(lbl($PAY, $R['pay_status'])) ?></span><?php if (certReady($R)): ?> &middot; <span class="badge verified"><?= te('Certificate issued') ?></span><?php endif; ?></p>
<p><small><?= e(setting('name')) ?> &middot; <?= e(tx(setting('date'))) ?></small></p></div><?php endif; foot();
