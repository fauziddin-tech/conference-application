<?php require 'inc.php'; chk(); $sent = false; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $m = strtolower(post('email'));
    if (throttled('forgot:' . ip(), 5, 3600)) $err = 'Terlalu banyak permintaan. Coba lagi dalam 1 jam.';
    elseif (!captchaOk()) $err = 'Verifikasi keamanan (captcha) gagal. Coba lagi.';
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
head('Lupa Password'); ?><h2>Lupa Password</h2>
<?php if ($sent): ?><p class="msg">Jika email tersebut terdaftar, tautan untuk mengatur ulang password telah dikirim. Periksa juga folder spam. Bila tidak menerima email, hubungi panitia<?= setting('email') ? ' di ' . e(setting('email')) : '' ?>.</p>
<?php else: ?><?php if ($err): ?><p class="msg err"><?= e($err) ?></p><?php endif; ?>
<form method="post" class="card narrow"><?= csrf() ?><p>Masukkan email akun Anda. Kami akan mengirim tautan untuk membuat password baru.</p>
<label>Email<input type="email" name="email" required autocomplete="email"></label><?= captchaHtml() ?><button class="btn">Kirim Tautan</button>
<p><a href="login.php">Kembali ke halaman masuk</a></p></form><?php endif; foot();
