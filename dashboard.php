<?php require 'inc.php'; $u = need('peserta'); chk();
$R = regOf($u['id']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = post('a');
    if ($a === 'profile') {
        $n = post('name');
        if (!validName($n)) { flash(t('Names may only contain letters, spaces, periods, commas and hyphens (no numbers, links or symbols).')); go('dashboard.php#profil'); }
        $pdo->prepare("UPDATE users SET name=?,institution=?,phone=? WHERE id=?")->execute([$n, post('institution'), post('phone'), $u['id']]);
        $pw = (string)($_POST['password'] ?? '');
        if ($pw !== '') {
            if (!password_verify((string)($_POST['current'] ?? ''), $u['password'])) { flash(t('Current password is incorrect; the password was not changed.')); go('dashboard.php#profil'); }
            if (strlen($pw) < 8) { flash(t('The new password must be at least 8 characters; the password was not changed.')); go('dashboard.php#profil'); }
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($pw, PASSWORD_DEFAULT), $u['id']]);
        }
        flash(t('Profile updated.')); go('dashboard.php#profil');
    }
    // Unggah naskah revisi / camera-ready (tetap bisa walau pembayaran sudah terverifikasi)
    if (in_array($a, ['revision', 'camera'], true) && $R && $R['article_title']) {
        $ok = $a === 'revision' ? $R['paper_status'] === 'revision' : $R['paper_status'] === 'accepted';
        if (!$ok) { flash(t('Upload is not available for the current paper status.')); go('dashboard.php'); }
        $resp = post('response');
        if ($a === 'revision' && mb_strlen($resp) < 20) { flash(t('Please write a response to the reviewers (min. 20 characters) describing what you revised.')); go('dashboard.php#paper'); }
        $e = ''; $file = upFile($_FILES['file'] ?? null, $a === 'revision' ? paperExts() : ['pdf', 'doc', 'docx'], $e, $a === 'revision' ? 'Revised manuscript' : 'Camera-ready');
        if (!$file) { flash($e); go('dashboard.php#paper'); }
        $reg = no('R', $R['id']);
        if ($a === 'revision') {
            // Ronde review baru: naskah revisi (versi baru) dikirim ke reviewer ronde sebelumnya
            $old = (int)$R['review_round']; $new = $old + 1;
            $pdo->prepare("UPDATE registrations SET review_round=? WHERE id=?")->execute([$new, $R['id']]);
            $R['review_round'] = $new;
            $ver = addPaperFile($R, 'revision', $file, $resp);
            $due = date('Y-m-d', strtotime('+' . max(1, (int)setting('review_days', '14')) . ' days'));
            $pdo->prepare("INSERT IGNORE INTO reviews(reg_id,reviewer_id,round,due_date) SELECT reg_id,reviewer_id,?,? FROM reviews WHERE reg_id=? AND round=?")->execute([$new, $due, $R['id'], $old]);
            $revs = reviewsOf($R['id'], $new);
            $pdo->prepare("UPDATE registrations SET paper_status=? WHERE id=?")->execute([$revs ? 'under_review' : 'submitted', $R['id']]);
            foreach ($revs as $v) {
                notifyUser($v['reviewer_id'], "Naskah revisi $reg (v$ver) siap direview ulang (ronde $new).", 'reviewer.php?id=' . $R['id'], "Revised paper $reg (v$ver) is ready for re-review (round $new).");
                notify($v['email'], $v['name'], "Revised paper $reg ready for re-review", "The author has submitted a revised version (v$ver) of paper $reg \"" . $R['article_title'] . "\" with a response to the reviewers.\n\nPlease review the revision (round $new) by " . fdateEn($due) . ":\n" . absUrl('reviewer.php?id=' . $R['id']));
            }
            notifyAdmins("Revisi masuk $reg (v$ver, ronde $new)" . ($revs ? '' : ' — belum ada reviewer'), 'admin/?p=detail&id=' . $R['id']);
            flash($revs ? t('Revised paper (v%d) submitted and forwarded to the reviewers for round %d.', $ver, $new) : t('Revised paper (v%d) submitted.', $ver));
        } else {
            addPaperFile($R, 'camera', $file);
            notifyAdmins("Naskah final (camera-ready) masuk $reg", 'admin/?p=detail&id=' . $R['id']);
            flash(t('Final (camera-ready) paper submitted. Thank you.'));
        }
        go('dashboard.php#paper');
    }
    go('dashboard.php');
}
$pres = $R && $R['article_title'];
$verified = $R && $R['pay_status'] === 'verified';
$news = items('news');
head(t('Dashboard')); ?>
<h2><?= te('Participant Dashboard') ?></h2>
<p><?= te('Hello') ?>, <b><?= e($R ? trim($R['prefix'] . ' ' . $R['full_name']) : $u['name']) ?></b> &middot; <?= te('Account ID') ?>: <b><?= no('U', $u['id']) ?></b><?php if ($R): ?> &middot; <?= te('Registration No.') ?>: <b><?= no('R', $R['id']) ?></b><?php endif; ?></p>

<?php
// --- Progres pendaftaran ---
$st = [];
$st[] = [t('Account created'), 'done', ''];
$st[] = [t('Registration form'), $R ? 'done' : 'now', $R ? '' : t('Complete the form')];
if ($pres) {
    $ps = $R['paper_status'];
    $st[] = [t('Paper review'), $ps === 'accepted' ? 'done' : ($ps === 'rejected' ? 'fail' : 'now'), lbl($PAPER, $ps)];
}
$p = $R['pay_status'] ?? 'unpaid';
$st[] = [t('Payment'), $p === 'verified' ? 'done' : ($p === 'rejected' ? 'fail' : ($R ? 'now' : '')), $R ? lbl($PAY, $p) : ''];
if ($R && $R['attendance'] === 'Offline') $st[] = [t('Event check-in'), $R['checked_in_at'] ? 'done' : '', $R['checked_in_at'] ? date('d/m H:i', strtotime($R['checked_in_at'])) : ''];
$st[] = [t('Certificate'), certReady($R) ? 'done' : '', certReady($R) ? t('Ready to download') : ''];
?>
<ol class="progress"><?php foreach ($st as $s): ?><li class="<?= $s[1] ?>"><b><?= e($s[0]) ?></b><?php if ($s[2]): ?><small><?= e($s[2]) ?></small><?php endif; ?></li><?php endforeach; ?></ol>

<?php if (!$R): ?><div class="panel"><p><?= te('You have not completed the registration form yet (participant data, paper for presenters, and payment).') ?></p><?php if (regOpen()): ?><a class="btn" href="registrasi.php"><?= te('Complete Registration') ?></a><?php else: ?><p class="msg"><?= e(tx(setting('reg_close_msg'))) ?></p><?php endif; ?></div>
<?php else: ?>
<div class="dgrid">
<div class="panel"><h3><?= te('Registration') ?></h3>
<p><?= te('Category') ?>: <b><?= e(tx($R['category'])) ?></b><br><?= te('Attendance') ?>: <b><?= e($R['attendance']) ?></b><br><?= te('Fee') ?>: <b><?= e($R['pay_amount']) ?></b></p>
<p><?= te('Payment') ?>: <span class="badge <?= e($R['pay_status']) ?>"><?= e(lbl($PAY, $R['pay_status'])) ?></span></p>
<?php if (in_array($R['pay_status'], ['unpaid', 'rejected'], true)): ?><p class="msg"><?= $R['pay_status'] === 'rejected' ? te('Your proof of payment was rejected. Check the committee note and upload it again.') : te('Please pay according to the invoice and upload the proof of payment.') ?></p><?php endif; ?>
<?php if ($R['admin_note']): ?><p><b><?= te('Committee note') ?>:</b><br><?= nl2br(e($R['admin_note'])) ?></p><?php endif; ?>
<?php if (!$verified): ?><a class="btn" href="registrasi.php"><?= in_array($R['pay_status'], ['unpaid', 'rejected'], true) ? te('Upload Proof of Payment / Edit Data') : te('Edit Registration Data') ?></a><?php endif; ?></div>

<div class="panel"><h3><?= te('Documents') ?></h3><ul class="docs">
<li><a class="js-doc" href="doc.php?t=invoice" target="_blank">Invoice</a></li>
<?php if ($verified): ?><li><a class="js-doc" href="doc.php?t=receipt" target="_blank"><?= te('Payment receipt') ?></a></li>
<li><a class="js-doc" href="doc.php?t=badge" target="_blank">E-ticket &amp; name badge (QR)</a></li>
<li><a class="js-doc" href="doc.php?t=invitation" target="_blank"><?= te('Invitation letter (visa / permission)') ?></a></li><?php endif; ?>
<?php if ($pres && $R['paper_status'] === 'accepted'): ?><li><a class="js-doc" href="doc.php?t=loa" target="_blank">Letter of Acceptance (LoA)</a></li><?php endif; ?>
<?php if (certReady($R)): ?><li><a class="js-doc" href="doc.php?t=cert" target="_blank"><b>E-Certificate</b></a></li><?php endif; ?>
</ul><?php if (!$verified): ?><p><small><?= te('The receipt, e-ticket and invitation letter are available once your payment is verified.') ?></small></p><?php elseif (!certReady($R)): ?><p><small><?= te('The e-certificate is available after the event once released by the committee.') ?><?= $R['attendance'] === 'Offline' && setting('cert_checkin', '1') === '1' ? ' ' . te('On-site check-in with your e-ticket QR code is required.') : '' ?></small></p><?php endif; ?></div>

<?php if ($verified && $R['attendance'] === 'Online' && (setting('online_link') || setting('online_info'))): ?>
<div class="panel"><h3><?= te('Online Access') ?></h3><?php if (setting('online_info')): ?><p><?= nl2br(e(tx(setting('online_info')))) ?></p><?php endif; ?><?php if (isurl(setting('online_link'))): ?><p><a class="btn" href="<?= e(setting('online_link')) ?>" target="_blank" rel="noopener"><?= te('Join Online Session') ?></a></p><?php endif; ?></div><?php endif; ?>
</div>

<?php if ($pres): ?><h3 id="paper"><?= te('Paper') ?></h3><div class="panel">
<p><i><?= e($R['article_title']) ?></i><?= $R['track'] ? '<br><small>Track: ' . e(tx($R['track'])) . '</small>' : '' ?></p>
<p><?= te('Status') ?>: <span class="badge <?= e($R['paper_status']) ?>"><?= e(lbl($PAPER, $R['paper_status'])) ?></span> &middot; <?= te('Review round') ?>: <b><?= (int)$R['review_round'] ?></b></p>
<?php if ($R['paper_status'] === 'revision'): ?>
<div class="revbox"><h4><?= te('Submit Revision') ?></h4><p><small><?= te('Read the reviewers\' comments and files in the history below. Revise your paper, then upload the new version with a point-by-point response. The revision is forwarded automatically to the same reviewers.') ?></small></p>
<form method="post" enctype="multipart/form-data" class="card"><?= csrf() ?><input type="hidden" name="a" value="revision">
<label class="full"><?= te('Response to reviewers') ?> * <small>(<?= te('what you revised, per comment') ?>)</small><textarea name="response" rows="6" required minlength="20" placeholder="<?= te('Reviewer 1, comment 1: revised in section ...') ?>&#10;Reviewer 2: ..."></textarea></label>
<label><?= te('Revised paper') ?> * <small>(<?= paperExtLabel() ?>, max 10 MB; <?= te('please highlight changes with Track Changes or colour') ?>)</small><input type="file" name="file" accept="<?= '.' . implode(',.', paperExts()) ?>" required></label>
<button class="btn"><?= te('Submit Revision') ?></button></form></div><?php endif; ?>
<?php if ($R['paper_status'] === 'accepted'): ?><form method="post" enctype="multipart/form-data" class="inline-form"><?= csrf() ?><input type="hidden" name="a" value="camera"><label><?= te('Upload final / camera-ready paper (PDF/DOC/DOCX)') ?><input type="file" name="file" accept=".pdf,.doc,.docx" required></label><button class="btn"><?= $R['camera_file'] ? te('Replace Final File') : te('Submit Final File') ?></button></form><?php endif; ?>
<h4><?= te('Paper & Review History') ?></h4>
<?php paperHistory($R, 'author'); ?>
</div><?php endif; ?>
<?php endif; ?>

<?php if ($news): ?><h3><?= te('Announcements') ?></h3><?php foreach (array_slice($news, 0, 5) as $n): ?><div class="panel news-item"><small><?= e(fdateL($n['created_at'])) ?></small><h4><?= e(tx($n['title'])) ?></h4><p><?= nl2br(e(tx($n['body']))) ?></p><?php if (isurl($n['url'])): ?><a href="<?= e($n['url']) ?>" target="_blank" rel="noopener"><?= te('Read more') ?> &rarr;</a><?php endif; ?></div><?php endforeach; ?><?php endif; ?>

<h3><?= te('Program') ?></h3><?php agenda(); ?>

<h3 id="profil"><?= te('Profile & Security') ?></h3><form method="post" class="card"><?= csrf() ?><input type="hidden" name="a" value="profile">
<label><?= te('Email') ?><input value="<?= e($u['email']) ?>" disabled></label>
<label><?= te('Name') ?><input name="name" required value="<?= e($u['name']) ?>"></label>
<label><?= te('Institution') ?><input name="institution" value="<?= e($u['institution']) ?>"></label>
<label><?= te('Phone') ?><input name="phone" value="<?= e($u['phone']) ?>"></label>
<label><?= te('Current password (required to change password)') ?><input type="password" name="current" autocomplete="current-password"></label>
<label><?= te('New password (leave empty to keep)') ?><input type="password" name="password" minlength="8" autocomplete="new-password"></label>
<button class="btn"><?= te('Save') ?></button></form><?php foot();
