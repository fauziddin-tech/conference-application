<?php require 'inc.php'; chk(); $sent = false; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $m = strtolower(post('email'));
    if (throttled('forgot:' . ip(), 5, 3600)) $err = t('Too many requests. Try again in 1 hour.');
    elseif (!captchaOk()) $err = t('Security check (captcha) failed. Please try again.');
    else {
        hit('forgot:' . ip());
        $s = $pdo->prepare("SELECT * FROM users WHERE email=?"); $s->execute([$m]);
        if ($u = $s->fetch()) {
            $tok = bin2hex(random_bytes(24));
            $pdo->prepare("UPDATE users SET reset_hash=?,reset_exp=? WHERE id=?")->execute([hash('sha256', $tok), time() + 3600, $u['id']]);
            notify($u['email'], $u['name'], 'Password reset', "We received a request to reset your password. Open the link below within 60 minutes to set a new password:\n\n" . absUrl('reset.php?u=' . $u['id'] . '&k=' . $tok) . "\n\nIf you did not request this, you can ignore this email.");
        }
        $sent = true; // pesan sama agar tidak membocorkan email terdaftar
    }
}
head(t('Forgot Password')); ?><h2><?= te('Forgot Password') ?></h2>
<?php if ($sent): ?><p class="msg"><?= te('If that email is registered, a password reset link has been sent. Please also check your spam folder. If you do not receive it, contact the committee') ?><?= setting('email') ? ' (' . e(setting('email')) . ')' : '' ?>.</p>
<?php else: ?><?php if ($err): ?><p class="msg err"><?= e($err) ?></p><?php endif; ?>
<form method="post" class="card narrow"><?= csrf() ?><p><?= te('Enter your account email. We will send you a link to set a new password.') ?></p>
<label><?= te('Email') ?><input type="email" name="email" required autocomplete="email"></label><?= captchaHtml() ?><button class="btn"><?= te('Send Link') ?></button>
<p><a href="login.php"><?= te('Back to log in') ?></a></p></form><?php endif; foot();
