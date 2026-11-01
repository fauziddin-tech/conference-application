<?php if (!defined('ADMIN')) exit;
$eid = (int)($_GET['id'] ?? 0); $q = $pdo->prepare("SELECT * FROM users WHERE id=? AND role IN('peserta','reviewer')"); $q->execute([$eid]); $x = $q->fetch();
if (!$x): ?><p>Akun tidak ditemukan. <a href="?p=pengguna">Kembali</a></p>
<?php return; endif; $cats = array_column(fees(), 'cat'); if ($x['category'] && !in_array($x['category'], $cats)) $cats[] = $x['category']; ?>
<p><a href="?p=pengguna&role=<?= e($x['role']) ?>">&larr; Kembali ke daftar akun</a></p><h3>Edit Akun: <?= e($x['name']) ?></h3>
<form method="post" class="card"><?= csrf() ?><input type="hidden" name="a" value="uedit"><input type="hidden" name="id" value="<?= $x['id'] ?>">
<label>Nama<input name="name" required value="<?= e($x['name']) ?>"></label>
<label>Email (dipakai untuk login)<input type="email" name="email" required value="<?= e($x['email']) ?>"></label>
<label>Institusi<input name="institution" value="<?= e($x['institution']) ?>"></label>
<label>No. HP<input name="phone" value="<?= e($x['phone']) ?>"></label>
<label>Kategori<select name="category"><option value="">-</option><?php foreach ($cats as $c): ?><option <?= $x['category'] === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select></label>
<label>Status<select name="status"><?php foreach ($ULBL as $s => $l): ?><option value="<?= $s ?>" <?= $x['status'] === $s ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
<label>Peran<select name="role"><option value="peserta" <?= $x['role'] === 'peserta' ? 'selected' : '' ?>>Peserta</option><option value="reviewer" <?= $x['role'] === 'reviewer' ? 'selected' : '' ?>>Reviewer</option></select></label>
<button class="btn">Simpan Perubahan</button></form>
<h3>Reset Password</h3>
<form method="post" class="card" data-confirm="Reset password akun ini?"><?= csrf() ?><input type="hidden" name="a" value="pwreset"><input type="hidden" name="id" value="<?= $x['id'] ?>">
<label class="full">Password baru (kosongkan untuk dibuatkan password acak)<input name="password" type="text" autocomplete="off" minlength="8"></label>
<button class="btn">Reset Password</button></form>
