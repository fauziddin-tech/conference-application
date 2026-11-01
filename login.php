<?php require 'inc.php'; chk(); $err = '';
if ($u = user()) go(homeFor($u));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $m = strtolower(post('email')); $k = 'login:' . ip();
    if (throttled($k, 8, 900)) $err = 'Terlalu banyak percobaan masuk. Coba lagi dalam 15 menit.';
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
            go($to);
        }
        hit($k); $err = 'Email atau password salah.';
    }
}
head('Masuk'); ?><h2>Masuk</h2><?php if ($err): ?><p class="msg err"><?= e($err) ?></p><?php endif; ?>
<form method="post" class="card narrow"><?= csrf() ?><label>Email<input type="email" name="email" required autocomplete="email" value="<?= e($_POST['email'] ?? '') ?>"></label>
<label>Password<input type="password" name="password" required autocomplete="current-password"></label><button class="btn">Masuk</button>
<p><a href="forgot.php">Lupa password?</a> &middot; Belum punya akun? <a href="register.php">Daftar</a></p></form><?php foot();
