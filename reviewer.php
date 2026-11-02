<?php require 'inc.php'; $u = need('reviewer'); chk();
/* Area reviewer: naskah yang ditugaskan, riwayat versi & ronde, formulir review + file berkomentar (blind review). */
$blind = setting('blind_review', '1') === '1';
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $q = $pdo->prepare("SELECT v.id,v.file,v.submitted_at,r.paper_status,r.review_round FROM reviews v JOIN registrations r ON r.id=v.reg_id WHERE v.reg_id=? AND v.reviewer_id=? AND v.round=r.review_round"); $q->execute([$id, $u['id']]); $V = $q->fetch();
    if (!$V) { http_response_code(403); die(t('Access denied')); }
    $back = 'reviewer.php?id=' . $id;
    if (!in_array($V['paper_status'], ['submitted', 'under_review'], true)) { flash(t('The committee has already decided this review round; the review can no longer be changed.')); go($back); }
    $sc = (int)($_POST['score'] ?? 0); $rec = post('recommendation'); $txt = post('to_author');
    $hasFile = !empty($_FILES['rfile']['name']);
    if ($sc < 1 || $sc > 5 || !isset($REC[$rec])) { flash(t('Please complete the score and recommendation.')); go($back); }
    if ($txt === '' && !$hasFile && !$V['file']) { flash(t('Write comments to the author or upload the manuscript file containing your comments.')); go($back); }
    $file = null;
    if ($hasFile) { $e = ''; $file = upFile($_FILES['rfile'], ['doc', 'docx', 'pdf'], $e, t('Comment file')); if (!$file) { flash($e); go($back); } rmPriv($V['file']); }
    if ($txt === '') $txt = 'Comments are written directly in the attached file.';
    $pdo->prepare("UPDATE reviews SET score=?,recommendation=?,to_author=?,to_committee=?,submitted_at=NOW()" . ($file ? ",file=?,file_name=?" : '') . " WHERE id=?")
        ->execute(array_merge([$sc, $rec, $txt, post('to_committee')], $file ? [$file[0], $file[1]] : [], [$V['id']]));
    $pdo->prepare("UPDATE registrations SET paper_status='under_review' WHERE id=? AND paper_status='submitted'")->execute([$id]);
    notifyAdmins(($V['submitted_at'] ? 'Review diperbarui' : 'Review masuk') . ' untuk ' . no('R', $id) . ' (ronde ' . $V['review_round'] . ') oleh ' . $u['name'] . ': ' . lbl($REC, $rec), 'admin/?p=detail&id=' . $id);
    flash(t('Review saved. Thank you!')); go('reviewer.php');
}
head(t('Paper Review'));
if ($id):
    $q = $pdo->prepare("SELECT r.* FROM registrations r WHERE r.id=? AND EXISTS(SELECT 1 FROM reviews v WHERE v.reg_id=r.id AND v.reviewer_id=?)"); $q->execute([$id, $u['id']]); $x = $q->fetch();
    if (!$x): ?><p class="msg err"><?= te('Paper not found or not assigned to you.') ?> <a href="reviewer.php"><?= te('Back') ?></a></p><?php else:
    $mine = reviewsOf($id, (int)$x['review_round'], $u['id']); $V = $mine[0] ?? null;
    $open = $V && in_array($x['paper_status'], ['submitted', 'under_review'], true); ?>
<p><a href="reviewer.php">&larr; <?= te('Paper list') ?></a></p>
<h2><?= te('Paper') ?> <?= no('R', $x['id']) ?> &middot; <?= te('Round %d', (int)$x['review_round']) ?></h2>
<div class="panel"><h3><?= e($x['article_title']) ?></h3>
<?php if (!$blind): ?><p><b><?= te('Author') ?>:</b> <?= e(trim($x['prefix'] . ' ' . $x['full_name'])) ?>, <?= e($x['country']) ?><?= $x['coauthors'] ? '<br><b>Co-authors:</b> ' . nl2br(e($x['coauthors'])) : '' ?></p><?php endif; ?>
<?php if ($x['track']): ?><p><b>Track:</b> <?= e($x['track']) ?></p><?php endif; ?>
<p><b>Keywords:</b> <?= e($x['keywords']) ?></p><p><b>Abstract</b><br><?= nl2br(e($x['abstract'])) ?></p>
<p><a class="btn" href="file.php?id=<?= $x['id'] ?>&k=article">&#8681; <?= te('Download latest paper') ?></a><?= $V && $V['due_date'] ? ' &nbsp; ' . te('Review due') . ': <b>' . e(fdateL($V['due_date'])) . '</b>' . ($V['due_date'] < date('Y-m-d') && !$V['submitted_at'] ? ' <span class="tag spam">' . te('overdue') . '</span>' : '') : '' ?></p></div>

<h3><?= te('Review Form') ?> &ndash; <?= te('Round %d', (int)$x['review_round']) ?></h3>
<?php if (!$V): ?><p class="msg"><?= te('You are not assigned to this round. Your reviews from earlier rounds are shown below.') ?></p>
<?php else: if (!$open): ?><p class="msg"><?= te('Committee decision for this round') ?>: <b><?= e(lbl($PAPER, $x['paper_status'])) ?></b>. <?= te('The review is locked.') ?></p><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="card"><?= csrf() ?><input type="hidden" name="id" value="<?= $x['id'] ?>">
<label><?= te('Overall score') ?> *<select name="score" required <?= $open ? '' : 'disabled' ?>><option value="">--</option><?php foreach ([5 => '5 - Excellent', 4 => '4 - Good', 3 => '3 - Fair', 2 => '2 - Poor', 1 => '1 - Very poor'] as $k => $l): ?><option value="<?= $k ?>" <?= (int)$V['score'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></label>
<label><?= te('Recommendation') ?> *<select name="recommendation" required <?= $open ? '' : 'disabled' ?>><option value="">--</option><?php foreach ($REC as $k => $l): ?><option value="<?= $k ?>" <?= $V['recommendation'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
<label class="full"><?= te('Comments to the author') ?> <small><?= te('shown to the author without your identity; may be left empty if comments are in the file') ?></small><textarea name="to_author" rows="8" <?= $open ? '' : 'disabled' ?>><?= e($V['to_author']) ?></textarea></label>
<label><?= te('Manuscript file with comments') ?> <small><?= te('optional; DOC/DOCX/PDF max. 10 MB. Use Word Comments / Track Changes.') ?> <?= $blind ? te('Remove your name from the document properties to stay anonymous.') : '' ?></small><input type="file" name="rfile" accept=".doc,.docx,.pdf" <?= $open ? '' : 'disabled' ?>><?php if ($V['file']): ?><small><?= te('Submitted file') ?>: <a href="file.php?rv=<?= $V['id'] ?>"><?= e($V['file_name']) ?></a><?= $open ? ' (' . te('upload again to replace') . ')' : '' ?></small><?php endif; ?></label>
<label class="full"><?= te('Confidential note to the committee') ?> <small><?= te('not shown to the author') ?></small><textarea name="to_committee" rows="3" <?= $open ? '' : 'disabled' ?>><?= e($V['to_committee']) ?></textarea></label>
<?php if ($open): ?><button class="btn"><?= $V['submitted_at'] ? te('Update Review') : te('Submit Review') ?></button><?php endif; ?></form><?php endif; ?>

<h3><?= te('Version History & Your Reviews') ?></h3>
<?php paperHistory($x, 'reviewer', $u['id']); ?>
<?php endif; else:
    $q = $pdo->prepare("SELECT r.id,r.article_title,r.track,r.paper_status,r.review_round,v.submitted_at,v.recommendation,v.due_date FROM reviews v JOIN registrations r ON r.id=v.reg_id AND v.round=r.review_round WHERE v.reviewer_id=? ORDER BY v.submitted_at IS NOT NULL, v.due_date, v.created_at DESC"); $q->execute([$u['id']]); $L = $q->fetchAll();
    $todo = count(array_filter($L, function ($r) { return !$r['submitted_at'] && in_array($r['paper_status'], ['submitted', 'under_review'], true); })); ?>
<h2><?= te('Paper Review') ?></h2><p><?= te('Hello') ?>, <b><?= e($u['name']) ?></b>. <?= te('%d paper(s) awaiting your review out of %d assignment(s).', $todo, count($L)) ?><?= $blind ? ' ' . te('The review is blind: author identities are hidden.') : '' ?></p>
<?php $done = reviewerDone($u['id']); ?>
<div class="panel certbox"><div><b><?= te('Reviewer Certificate') ?></b><br><small><?= reviewerCertReady($u['id']) ? te('Thank you for reviewing %d paper(s).', $done) : ($done ? te('The committee has not released reviewer certificates yet.') : te('Available after you submit at least one review.')) ?></small></div>
<?php if (reviewerCertReady($u['id'])): ?><a class="btn js-doc" data-title="<?= te('Reviewer Certificate') ?>" href="doc.php?t=revcert" target="_blank"><?= te('View Certificate') ?></a><?php endif; ?></div>
<?php if (!$L): ?><p class="panel"><?= te('No papers assigned yet.') ?></p><?php else: ?>
<div class="scroll"><table><tr><th>ID</th><th><?= te('Title') ?></th><th>Track</th><th><?= te('Round') ?></th><th><?= te('Due') ?></th><th><?= te('Your review') ?></th><th><?= te('Paper status') ?></th><th></th></tr>
<?php foreach ($L as $x): $late = !$x['submitted_at'] && $x['due_date'] && $x['due_date'] < date('Y-m-d'); ?><tr><td><?= no('R', $x['id']) ?></td><td><?= e($x['article_title']) ?></td><td><?= e(tx($x['track'])) ?></td><td><?= (int)$x['review_round'] ?></td><td><?= $x['due_date'] ? e(fdateL($x['due_date'])) . ($late ? ' <span class="tag spam">' . te('overdue') . '</span>' : '') : '-' ?></td>
<td><?= $x['submitted_at'] ? '<span class="badge verified">' . e(lbl($REC, $x['recommendation'])) . '</span>' : '<span class="badge pending">' . te('Not yet') . '</span>' ?></td><td><?= e(lbl($PAPER, $x['paper_status'])) ?></td><td><a href="?id=<?= $x['id'] ?>"><?= $x['submitted_at'] ? te('View') : te('Review') ?></a></td></tr><?php endforeach; ?></table></div><?php endif; ?>
<p class="panel profile-head"><span><?= te('Update your name, institution, phone, login email and password.') ?></span><a class="btn" href="profile.php"><?= te('Edit Profile') ?></a></p>
<?php endif; foot();
