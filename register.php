<?php require 'inc.php'; chk(); $err = ''; $ajax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
if (($u = user()) && $_SERVER['REQUEST_METHOD'] !== 'POST') go(homeFor($u));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $n = post('name'); $m = strtolower(post('email')); $pw = (string)($_POST['password'] ?? '');
    if (!regOpen()) $err = tx(setting('reg_close_msg'));
    elseif (throttled('reg:' . ip(), 5, 3600)) $err = t('Too many registrations from your network. Please try again later.');
    elseif ($n === '' || !filter_var($m, FILTER_VALIDATE_EMAIL)) $err = t('A name and a valid email are required.');
    elseif ($sp = spamCheck($n, $m)) { $err = $sp; hit('reg:' . ip()); }
    elseif (strlen($pw) < 8) $err = t('Password must be at least 8 characters.');
    else {
        $s = $pdo->prepare("SELECT id FROM users WHERE email=?"); $s->execute([$m]);
        if ($s->fetch()) $err = t('This email is already registered. Please log in or use "Forgot password".');
        else {
            hit('reg:' . ip());
            $pdo->prepare("INSERT INTO users(name,email,password,role,status) VALUES(?,?,?,'peserta','pending')")->execute([$n, $m, password_hash($pw, PASSWORD_DEFAULT)]);
            session_regenerate_id(true); $_SESSION['uid'] = $pdo->lastInsertId(); $_SESSION['sv'] = 0;
            notify($m, $n, 'Account created', "Thank you for creating an account for " . setting('name') . ".\n\nNext step: complete the conference registration form (participant data, paper for presenters, and payment).\n" . absUrl('registrasi.php'));
            flash(t('Account created. Please continue by completing the conference registration form.'));
            if ($ajax) { header('Content-Type: application/json'); echo json_encode(['ok' => 1, 'to' => BASE . 'registrasi.php']); exit; }
            go('registrasi.php');
        }
    }
    if ($err && $ajax) { header('Content-Type: application/json'); echo json_encode(['error' => $err]); exit; }
}
head(t('Register')); ?><h2><?= te('Create Participant Account') ?></h2>
<?php if (!regOpen()): ?><p class="msg"><?= e(tx(setting('reg_close_msg'))) ?></p><?php else: ?>
<p><?= te('Step 1 of 2. After creating your account you will be taken straight to the conference registration form.') ?></p>
<?php if ($err): ?><p class="msg err"><?= e($err) ?></p><?php endif; ?>
<form method="post" class="card narrow"><?= csrf() ?>
<label><?= te('Full name') ?><input name="name" required autocomplete="name" value="<?= e($_POST['name'] ?? '') ?>"></label>
<label><?= te('Email') ?><input type="email" name="email" required autocomplete="email" value="<?= e($_POST['email'] ?? '') ?>"></label>
<label><?= te('Password (min. 8 characters)') ?><input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
<input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true"><?= captchaHtml() ?>
<button class="btn"><?= te('Create Account & Continue') ?></button>
<p><?= te('Already have an account?') ?> <a href="login.php"><?= te('Log in') ?></a></p></form><?php endif; foot();
