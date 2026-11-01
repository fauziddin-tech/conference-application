<?php require 'inc.php'; chk(); $err = ''; $ajax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
if (($u = user()) && $_SERVER['REQUEST_METHOD'] !== 'POST') go(homeFor($u));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $n = post('name'); $m = strtolower(post('email')); $pw = (string)($_POST['password'] ?? '');
    if (!regOpen()) $err = setting('reg_close_msg');
    elseif (throttled('reg:' . ip(), 5, 3600)) $err = 'Terlalu banyak pendaftaran dari jaringan Anda. Coba lagi nanti.';
    elseif ($n === '' || !filter_var($m, FILTER_VALIDATE_EMAIL)) $err = 'Nama dan email valid wajib diisi.';
    elseif ($sp = spamCheck($n, $m)) { $err = $sp; hit('reg:' . ip()); }
    elseif (strlen($pw) < 8) $err = 'Password minimal 8 karakter.';
    else {
        $s = $pdo->prepare("SELECT id FROM users WHERE email=?"); $s->execute([$m]);
        if ($s->fetch()) $err = 'Email sudah terdaftar. Silakan masuk atau gunakan "Lupa password".';
        else {
            hit('reg:' . ip());
            $pdo->prepare("INSERT INTO users(name,email,password,role,status) VALUES(?,?,?,'peserta','pending')")->execute([$n, $m, password_hash($pw, PASSWORD_DEFAULT)]);
            session_regenerate_id(true); $_SESSION['uid'] = $pdo->lastInsertId();
            notify($m, $n, 'Account created', "Thank you for creating an account for " . setting('name') . ".\n\nNext step: complete the conference registration form (participant data, paper for presenters, and payment).\n" . absUrl('registrasi.php'));
            flash('Akun berhasil dibuat. Lanjutkan dengan melengkapi formulir pendaftaran konferensi.');
            if ($ajax) { header('Content-Type: application/json'); echo json_encode(['ok' => 1, 'to' => BASE . 'registrasi.php']); exit; }
            go('registrasi.php');
        }
    }
    if ($err && $ajax) { header('Content-Type: application/json'); echo json_encode(['error' => $err]); exit; }
}
head('Daftar'); ?><h2>Buat Akun Peserta</h2>
<?php if (!regOpen()): ?><p class="msg"><?= e(setting('reg_close_msg')) ?></p><?php else: ?>
<p><b>Langkah 1 dari 2.</b> Buat akun terlebih dahulu. Setelah itu Anda langsung diarahkan ke formulir pendaftaran konferensi.</p>
<?php if ($err): ?><p class="msg err"><?= e($err) ?></p><?php endif; ?>
<form method="post" class="card narrow"><?= csrf() ?>
<label>Nama Lengkap<input name="name" required autocomplete="name" value="<?= e($_POST['name'] ?? '') ?>"></label>
<label>Email<input type="email" name="email" required autocomplete="email" value="<?= e($_POST['email'] ?? '') ?>"></label>
<label>Password (min. 8 karakter)<input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
<input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true"><?= captchaHtml() ?>
<button class="btn">Buat Akun &amp; Lanjutkan</button>
<p>Sudah punya akun? <a href="login.php">Masuk</a></p></form><?php endif; foot();
