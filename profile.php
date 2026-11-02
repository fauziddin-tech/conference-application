<?php require 'inc.php'; $u = need(); chk();
/*
 * Profil akun untuk semua peran (admin, peserta, reviewer, bendahara):
 * data diri, ganti email, ganti password, keluar dari perangkat lain.
 */
$ROLES = ['admin' => t('Administrator'), 'peserta' => t('Participant'), 'reviewer' => t('Reviewer'), 'bendahara' => t('Treasurer')];
$checkPw = function () use ($u) { // verifikasi password saat ini + batasi percobaan salah
    $k = 'pwchk:' . $u['id'];
    if (throttled($k, 8, 900)) return t('Too many attempts. Try again in 15 minutes.');
    if (!password_verify((string)($_POST['current'] ?? ''), $u['password'])) { hit($k); return t('Current password is incorrect.'); }
    return '';
};
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = post('a');
    if ($a === 'info') {
        $n = post('name');
        if (!validName($n)) flash(t('Names may only contain letters, spaces, periods, commas and hyphens (no numbers, links or symbols).'));
        else { $pdo->prepare("UPDATE users SET name=?,institution=?,phone=? WHERE id=?")->execute([$n, mb_substr(post('institution'), 0, 150), mb_substr(post('phone'), 0, 30), $u['id']]); flash(t('Profile updated.')); }
        go('profile.php');
    }
    if ($a === 'email') {
        $m = strtolower(post('email'));
        if (!filter_var($m, FILTER_VALIDATE_EMAIL) || strlen($m) > 150) flash(t('Enter a valid email address.'));
        elseif ($m === strtolower($u['email'])) flash(t('This is already your email address.'));
        elseif (blockedEmail($m)) flash(t('Please use an institutional or permanent personal email address (temporary emails are not accepted).'));
        elseif ($er = $checkPw()) flash($er);
        else {
            $q = $pdo->prepare("SELECT 1 FROM users WHERE email=? AND id<>?"); $q->execute([$m, $u['id']]);
            if ($q->fetch()) flash(t('This email is already used by another account.'));
            else {
                $pdo->prepare("UPDATE users SET email=? WHERE id=?")->execute([$m, $u['id']]);
                $msg = 'The login email of your ' . setting('name') . ' account has been changed from ' . $u['email'] . ' to ' . $m . '. If you did not make this change, contact the committee immediately.';
                notify($u['email'], $u['name'], 'Login email changed', $msg, 'profile.php');
                notify($m, $u['name'], 'Login email changed', $msg, 'profile.php');
                flash(t('Email changed. Use the new email the next time you log in.'));
            }
        }
        go('profile.php#email');
    }
    if ($a === 'pw') {
        $pw = (string)($_POST['password'] ?? '');
        if ($er = $checkPw()) flash($er);
        elseif (strlen($pw) < 8) flash(t('Password must be at least 8 characters.'));
        elseif ($pw !== (string)($_POST['password2'] ?? '')) flash(t('The new passwords do not match.'));
        else {
            setPassword($u['id'], $pw, true); // sesi di perangkat lain ikut keluar
            notify($u['email'], $u['name'], 'Password changed', 'The password of your ' . setting('name') . ' account was just changed. If you did not make this change, use "Forgot password" on the login page and contact the committee.', 'profile.php');
            flash(t('Password changed. Other devices have been logged out.'));
        }
        go('profile.php#password');
    }
    if ($a === 'sessions') {
        $pdo->prepare("UPDATE users SET sess_ver=sess_ver+1 WHERE id=?")->execute([$u['id']]);
        $_SESSION['sv'] = (int)$u['sess_ver'] + 1;
        flash(t('All other devices have been logged out.')); go('profile.php#security');
    }
    go('profile.php');
}
$R = $u['role'] === 'peserta' ? regOf($u['id']) : null;
head(t('My Profile')); ?>
<h2><?= te('My Profile') ?></h2>
<div class="panel profile-head"><div class="avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr(trim(preg_replace('/^(prof|dr|drs|ir|hj?)\.?\s+/i', '', $u['name'])), 0, 1))) ?></div>
<div><b><?= e($u['name']) ?></b><br><small><?= e($u['email']) ?> &middot; <span class="tag"><?= e($ROLES[$u['role']] ?? $u['role']) ?></span> &middot; <?= te('Member since %s', fdateL($u['created_at'])) ?></small></div>
<a class="btn ghost2" href="<?= e(homeFor($u)) ?>"><?= te('Back') ?></a></div>

<h3 id="info"><?= te('Personal Details') ?></h3>
<form method="post" class="card"><?= csrf() ?><input type="hidden" name="a" value="info">
<label><?= te('Name') ?><input name="name" required maxlength="120" autocomplete="name" value="<?= e($u['name']) ?>"></label>
<label><?= te('Institution') ?><input name="institution" maxlength="150" autocomplete="organization" value="<?= e($u['institution']) ?>"></label>
<label><?= te('Phone / WhatsApp') ?><input name="phone" type="tel" maxlength="30" autocomplete="tel" value="<?= e($u['phone']) ?>"></label>
<?php if ($R): ?><p class="hint-box"><?= te('The name, title and institution printed on your certificate, LoA and invoice are taken from your registration form.') ?> <a href="registrasi.php"><?= te('Edit registration data') ?></a></p><?php endif; ?>
<button class="btn"><?= te('Save') ?></button></form>

<h3 id="email"><?= te('Login Email') ?></h3>
<form method="post" class="card"><?= csrf() ?><input type="hidden" name="a" value="email">
<label><?= te('New email') ?> <small><?= te('current: %s', $u['email']) ?></small><input type="email" name="email" required maxlength="150" autocomplete="email"></label>
<label><?= te('Current password') ?><input type="password" name="current" required autocomplete="current-password"></label>
<p class="hint-box"><?= te('A notice is sent to both the old and the new address.') ?></p>
<button class="btn"><?= te('Change Email') ?></button></form>

<h3 id="password"><?= te('Change Password') ?></h3>
<form method="post" class="card"><?= csrf() ?><input type="hidden" name="a" value="pw">
<label><?= te('Current password') ?><input type="password" name="current" required autocomplete="current-password"></label>
<label><?= te('New password') ?> <small><?= te('min. 8 characters') ?></small><input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
<label><?= te('Repeat new password') ?><input type="password" name="password2" required minlength="8" autocomplete="new-password"></label>
<button class="btn"><?= te('Change Password') ?></button></form>

<h3 id="security"><?= te('Security') ?></h3>
<div class="card"><p><?= te('Logged in on a shared or lost device? Log out everywhere except this browser.') ?></p>
<form method="post" data-confirm="<?= te('Log out all other devices?') ?>"><?= csrf() ?><input type="hidden" name="a" value="sessions"><button class="btn ghost2"><?= te('Log out other devices') ?></button></form></div>
<?php foot();
