<?php if (!defined('ADMIN')) exit; ?>
<h3>Kirim Email Massal</h3>
<p>Gunakan untuk pengingat pembayaran, info teknis acara, link zoom, dsb. Placeholder: <code>{name}</code>, <code>{regno}</code>, <code>{email}</code>. Catatan: hosting bersama biasanya membatasi jumlah email per jam (mis. 100-500); kirim bertahap bila penerima banyak.</p>
<form method="post" class="card" onsubmit="return confirm('Kirim email ke semua penerima terpilih?')"><?= csrf() ?><input type="hidden" name="a" value="bulk">
<label>Penerima<select name="aud" required><?php foreach ($AUD as $k => $l): ?><option value="<?= $k ?>"><?= e($l) ?> (<?= count(audienceRows($k)) ?>)</option><?php endforeach; ?></select></label>
<label>Subjek<input name="subject" required></label>
<label class="full">Pesan<textarea name="body" rows="10" required>Dear {name},

</textarea></label><button class="btn">Kirim</button></form>
<h3>Log Email Terakhir</h3><p><small>Jika banyak yang gagal, periksa pengaturan "Email pengirim" (gunakan email dengan domain website Anda) atau fitur email di cPanel.</small></p>
<div class="scroll"><table><tr><th>Waktu</th><th>Kepada</th><th>Subjek</th><th>Status</th></tr>
<?php foreach ($pdo->query("SELECT * FROM mail_log ORDER BY id DESC LIMIT 50") as $m): ?><tr><td><small><?= e($m['created_at']) ?></small></td><td><?= e($m['to_addr']) ?></td><td><?= e($m['subject']) ?></td><td><?= $m['ok'] ? '<span class="badge verified">terkirim</span>' : '<span class="badge rejected">gagal</span>' ?></td></tr><?php endforeach; ?></table></div>
