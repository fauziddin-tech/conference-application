<?php if (!defined('ADMIN')) exit; ?>
<h3>Kirim Email Massal</h3>
<p>Gunakan untuk pengingat pembayaran, info teknis acara, link zoom, dsb. Placeholder: <code>{name}</code>, <code>{regno}</code>, <code>{email}</code>. Catatan: hosting bersama biasanya membatasi jumlah email per jam (mis. 100-500); kirim bertahap bila penerima banyak.</p>
<form method="post" class="card" data-confirm="Kirim email ke semua penerima terpilih?"><?= csrf() ?><input type="hidden" name="a" value="bulk">
<label>Penerima<select name="aud" required><?php foreach ($AUD as $k => $l): ?><option value="<?= $k ?>"><?= e($l) ?> (<?= count(audienceRows($k)) ?>)</option><?php endforeach; ?></select></label>
<label>Subjek<input name="subject" required></label>
<label class="full">Pesan<textarea name="body" rows="10" required>Dear {name},

</textarea></label><button class="btn">Kirim</button></form>
<p><small>Layanan pengirim: <b><?= resendOn() ? 'Resend API (batch, maks. 100 per permintaan)' : 'PHP mail() hosting' ?></b> &middot; <a href="?p=setting#email-kirim">ubah</a> &middot; <a href="?p=setting#uji-email">kirim email uji</a></small></p>
<h3>Log Email Terakhir</h3><p><small>Jika gagal, baca kolom keterangan. Dengan Resend, pastikan domain email pengirim berstatus <b>Verified</b> di dashboard Resend.</small></p>
<div class="scroll"><table><tr><th>Waktu</th><th>Kepada</th><th>Subjek</th><th>Via</th><th>Status</th><th>Keterangan</th></tr>
<?php foreach ($pdo->query("SELECT * FROM mail_log ORDER BY id DESC LIMIT 50") as $m): ?><tr><td><small><?= e($m['created_at']) ?></small></td><td><?= e($m['to_addr']) ?></td><td><?= e($m['subject']) ?></td><td><small><?= e($m['provider'] ?? '') ?></small></td><td><?= $m['ok'] ? '<span class="badge verified">terkirim</span>' : '<span class="badge rejected">gagal</span>' ?></td><td><small><?= e($m['error'] ?? '') ?></small></td></tr><?php endforeach; ?></table></div>
