<?php require 'inc.php'; chk(); $err = '';
$id = (int)($_GET['u'] ?? 0); $k = (string)($_GET['k'] ?? '');
$s = $pdo->prepare("SELECT * FROM users WHERE id=?"); $s->execute([$id]); $u = $s->fetch();
$valid = $u && $u['reset_hash'] && $k !== '' && hash_equals($u['reset_hash'], hash('sha256', $k)) && (int)$u['reset_exp'] >= time();
if ($valid && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $pw = (string)($_POST['password'] ?? '');
    if (strlen($pw) < 8) $err = 'Password minimal 8 karakter.';
    elseif ($pw !== ($_POST['password2'] ?? '')) $err = 'Konfirmasi password tidak sama.';
    else {
        $pdo->prepare("UPDATE users SET password=?,reset_hash=NULL,reset_exp=NULL WHERE id=?")->execute([password_hash($pw, PASSWORD_DEFAULT), $id]);
        flash('Password berhasil diubah. Silakan masuk dengan password baru.'); go('login.php');
    }
}
head('Atur Ulang Password'); ?><h2>Atur Ulang Password</h2>
<?php if (!$valid): ?><p class="msg err">Tautan tidak valid atau sudah kedaluwarsa. <a href="forgot.php">Minta tautan baru</a>.</p>
<?php else: ?><?php if ($err): ?><p class="msg err"><?= e($err) ?></p><?php endif; ?>
<form method="post" class="card narrow"><?= csrf() ?><label>Password baru (min. 8 karakter)<input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
<label>Ulangi password baru<input type="password" name="password2" required minlength="8" autocomplete="new-password"></label><button class="btn">Simpan Password</button></form><?php endif; foot();
