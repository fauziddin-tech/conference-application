<?php require 'inc.php'; chk(); $err = ''; $ajax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
if (($u = user()) && !$ajax) go(homeFor($u));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $m = strtolower(post('email')); $k = 'login:' . ip();
    if (throttled($k, 8, 900)) $err = t('Too many login attempts. Try again in 15 minutes.');
    else {
        $s = $pdo->prepare("SELECT * FROM users WHERE email=?"); $s->execute([$m]); $u = $s->fetch();
        if ($u && password_verify((string)($_POST['password'] ?? ''), $u['password'])) {
            session_regenerate_id(true); $_SESSION['uid'] = $u['id'];
            if (password_needs_rehash($u['password'], PASSWORD_DEFAULT)) $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($_POST['password'], PASSWORD_DEFAULT), $u['id']]);
            $pdo->prepare("UPDATE users SET last_login=NOW() WHERE id=?")->execute([$u['id']]);
            $to = homeFor($u);
            if ($u['role'] === 'peserta' && !regOf($u['id'])) $to = 'registrasi.php';
            $back = $_SESSION['back'] ?? ''; unset($_SESSION['back']);
            if ($back && strpos($back, BASE) === 0 && strpos($back, '//') === false) $to = $back;
            if ($ajax) { header('Content-Type: application/json'); echo json_encode(['ok' => 1, 'to' => strpos($to, '/') === 0 ? $to : BASE . $to]); exit; }
            go($to);
        }
        hit($k); $err = t('Incorrect email or password.');
    }
    if ($ajax) { header('Content-Type: application/json'); echo json_encode(['error' => $err]); exit; }
}
head(t('Log in')); ?><h2><?= te('Log in') ?></h2><?php if ($err): ?><p class="msg err"><?= e($err) ?></p><?php endif; ?>
<form method="post" class="card narrow"><?= csrf() ?><label><?= te('Email') ?><input type="email" name="email" required autocomplete="email" value="<?= e($_POST['email'] ?? '') ?>"></label>
<label><?= te('Password') ?><input type="password" name="password" required autocomplete="current-password"></label><button class="btn"><?= te('Log in') ?></button>
<p><a href="forgot.php"><?= te('Forgot password?') ?></a> &middot; <?= te('No account yet?') ?> <a href="register.php"><?= te('Register') ?></a></p></form><?php foot();
