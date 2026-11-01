<?php require 'inc.php'; chk(); $err = '';
$id = (int)($_GET['u'] ?? 0); $k = (string)($_GET['k'] ?? '');
$s = $pdo->prepare("SELECT * FROM users WHERE id=?"); $s->execute([$id]); $u = $s->fetch();
$valid = $u && $u['reset_hash'] && $k !== '' && hash_equals($u['reset_hash'], hash('sha256', $k)) && (int)$u['reset_exp'] >= time();
if ($valid && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $pw = (string)($_POST['password'] ?? '');
    if (strlen($pw) < 8) $err = t('Password must be at least 8 characters.');
    elseif ($pw !== ($_POST['password2'] ?? '')) $err = t('The passwords do not match.');
    else {
        setPassword($id, $pw);
        flash(t('Your password has been changed. Please log in with the new password.')); go('login.php');
    }
}
head(t('Reset Password')); ?><h2><?= te('Reset Password') ?></h2>
<?php if (!$valid): ?><p class="msg err"><?= te('This link is invalid or has expired.') ?> <a href="forgot.php"><?= te('Request a new link') ?></a>.</p>
<?php else: ?><?php if ($err): ?><p class="msg err"><?= e($err) ?></p><?php endif; ?>
<form method="post" class="card narrow"><?= csrf() ?><label><?= te('New password (min. 8 characters)') ?><input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
<label><?= te('Repeat new password') ?><input type="password" name="password2" required minlength="8" autocomplete="new-password"></label><button class="btn"><?= te('Save Password') ?></button></form><?php endif; foot();
