<?php if (!defined('ADMIN')) exit;
$in = function ($k, $l, $type = 'text', $ph = '') { return '<label>' . $l . '<input type="' . $type . '" name="' . $k . '" value="' . e(setting($k)) . '" placeholder="' . e($ph) . '"></label>'; };
$ta = function ($k, $l, $r = 4) { return '<label class="full">' . $l . '<textarea name="' . $k . '" rows="' . $r . '">' . e(setting($k)) . '</textarea></label>'; };
$cb = function ($k, $l) { return '<label class="chk full"><input type="checkbox" name="' . $k . '" value="1"' . (setting($k) === '1' ? ' checked' : '') . '> ' . $l . '</label>'; }; ?>
<form method="post" enctype="multipart/form-data" class="card settings"><?= csrf() ?><input type="hidden" name="a" value="set">
<h3>Status</h3>
<?= $cb('reg_open', '<b>Pendaftaran dibuka</b> (akun &amp; formulir baru)') ?>
<?= $ta('reg_close_msg', 'Pesan saat pendaftaran ditutup', 2) ?>
<?= $cb('cert_open', '<b>Buka unduhan e-certificate</b> (aktifkan setelah acara)') ?>
<?= $cb('cert_checkin', 'Peserta offline wajib check-in untuk mendapat sertifikat') ?>
<?= $cb('blind_review', 'Blind review (identitas penulis disembunyikan dari reviewer)') ?>
<?= $cb('paper_word_only', 'Naskah & revisi hanya format Word (DOC/DOCX) agar reviewer bisa memberi komentar langsung di file') ?>
<?= $in('review_days', 'Tenggat review bawaan (hari)', 'number') ?>
<h3>Identitas Konferensi</h3>
<?= $in('name', 'Nama Konferensi') ?><?= $in('tagline', 'Tagline / Tema') ?><?= $in('organizer', 'Penyelenggara') ?>
<label>Poster acara <small>JPG/PNG/WEBP maks. 8 MB; tampil di beranda &amp; sebagai gambar pratinjau saat tautan dibagikan di WhatsApp/media sosial</small><input type="file" name="poster" accept="image/jpeg,image/png,image/webp"><?php if (setting('poster')): ?><img src="<?= e(imgSrc(setting('poster'))) ?>" alt="" style="max-width:260px;border-radius:8px;margin-top:6px;display:block"><?php endif; ?></label>
<?php if (setting('poster')): ?><label class="chk full"><input type="checkbox" name="poster_rm" value="1"> Hapus poster</label><?php endif; ?>
<?= $cb('poster_popup', 'Tampilkan poster sebagai <b>popup</b> saat pengunjung membuka beranda (maks. sekali per hari per pengunjung)') ?>
<label>Logo <small>JPG/PNG/WEBP; dipakai di navigasi, dokumen &amp; sertifikat</small><input type="file" name="logo" accept="image/jpeg,image/png,image/webp"><?php if (setting('logo_url')): ?><img class="thumb" src="<?= e(imgSrc(setting('logo_url'))) ?>" alt=""><?php endif; ?></label>
<?= $in('logo_url', 'atau URL logo', 'text', 'https://...') ?>
<?= $in('site_url', 'Alamat resmi situs <small>dipakai di email, QR &amp; sertifikat; terisi otomatis</small>', 'url', 'https://picoe.org/') ?><?= $in('website', 'Website (teks tampilan)') ?><?= $in('email', 'Email resmi panitia', 'email') ?>
<?= $ta('about', 'Tentang', 6) ?>
<h3>Waktu &amp; Tempat</h3>
<?= $in('date', 'Tanggal (teks tampilan)') ?><?= $in('time', 'Waktu (teks tampilan)') ?>
<?= $in('event_start', 'Mulai acara <small>hitung mundur, kalender, SEO</small>', 'datetime-local') ?><?= $in('event_end', 'Selesai acara', 'datetime-local') ?>
<?= $in('timezone', 'Zona waktu', 'text', 'Asia/Jakarta') ?><?= $in('location', 'Lokasi') ?>
<?= $in('venue_url', 'Link info lokasi/hotel', 'url') ?><?= $in('map_url', 'URL embed Google Maps') ?>
<?= $ta('venue_info', 'Deskripsi lokasi', 3) ?>
<h3>Call for Papers &amp; Naskah</h3>
<?= $ta('cfp_info', 'Informasi Call for Papers (topik dikelola di Konten &rarr; Topik)', 4) ?>
<?= $in('submission_url', 'Link Make Submission eksternal (OJS/EasyChair; kosongkan jika tidak dipakai)', 'url') ?>
<?= $cb('docx_strip', 'Saat .docx diunggah (naskah, revisi, camera-ready, file komentar reviewer): <b>buang font tertanam</b> &amp; perkecil gambar PNG yang terlalu besar (500 dpi), agar file jauh lebih kecil. Isi, tabel, komentar &amp; track changes tidak berubah.') ?>
<?= $in('abs_min', 'Abstrak minimal (kata)', 'number') ?><?= $in('abs_max', 'Abstrak maksimal (kata)', 'number') ?>
<h3>Biaya &amp; Pembayaran</h3>
<?= $ta('fees', 'Biaya pendaftaran <small>Satu kategori per baris, format <code>Kategori|IDR|USD|Early IDR|Early USD</code> (kolom early opsional). Kategori berisi kata "Presenter" mewajibkan naskah, "Online"/"Offline" mengunci jenis kehadiran.</small>', 5) ?>
<?= $in('early_deadline', 'Batas early bird', 'date') ?>
<?= $ta('payment_info', 'Instruksi pembayaran (rekening bank, atas nama, SWIFT, PayPal, dll.)', 4) ?>
<?= $ta('invoice_note', 'Catatan kaki invoice/kwitansi (opsional)', 2) ?>
<h3>Peserta Online</h3>
<?= $in('online_link', 'Link Zoom/Meet/YouTube <small>hanya tampil untuk peserta online terverifikasi</small>', 'url') ?>
<?= $ta('online_info', 'Info akses online (Meeting ID, passcode, aturan)', 3) ?>
<h3 id="sertifikat">Dokumen &amp; Sertifikat</h3>
<?= $ta('signers', 'Penandatangan <small>Format <code>Nama|Jabatan</code>, satu per baris, maks. 3; baris pertama menandatangani LoA &amp; surat</small>', 3) ?>
<?php $noTitle = array_filter(rows('signers'), function ($r) { return count($r) < 2 || $r[1] === ''; }); if ($noTitle): ?><p class="msg err full">Baris penandatangan tanpa jabatan: <b><?= e(implode('; ', array_column($noTitle, 0))) ?></b>. Tambahkan jabatan dengan tanda <code>|</code>, contoh: <code>Dr. Rusdial Marta, M.Pd.|Chairman of the Committee</code></p><?php endif; ?>
<?= $cb('cert_reviewer', '<b>Buka sertifikat reviewer</b> (tersedia bagi reviewer yang sudah mengirim minimal satu review)') ?>
<div class="full previews"><b>Pratinjau sertifikat:</b> <button type="button" class="btn ghost2" data-prev="participant">Peserta</button><button type="button" class="btn ghost2" data-prev="presenter">Presenter</button><button type="button" class="btn ghost2" data-prev="reviewer">Reviewer</button><button type="button" class="btn ghost2" data-prev="speaker">Pembicara</button><button type="button" class="btn ghost2" data-prev="committee">Panitia</button><button type="button" class="btn ghost2" data-prev="moderator">Moderator</button><small>memakai isian penandatangan di atas (belum perlu disimpan) dan data contoh</small></div>
<script>document.querySelectorAll('[data-prev]').forEach(function(b){b.addEventListener('click',function(){var s=document.querySelector('textarea[name=signers]').value;openDoc('<?= BASE ?>doc.php?t=cert&preview='+b.dataset.prev+'&signers='+encodeURIComponent(s),'Pratinjau sertifikat '+b.textContent)})});</script>
<h3 id="email-kirim">Pengiriman Email</h3>
<label>Layanan pengirim<select name="mail_driver"><option value="mail">PHP mail() bawaan hosting</option><option value="resend"<?= setting('mail_driver') === 'resend' ? ' selected' : '' ?>>Resend API (disarankan)</option></select></label>
<label>Resend API key <small><?= setting('resend_key') !== '' ? 'tersimpan: re_••••' . e(substr(setting('resend_key'), -4)) . ' &mdash; kosongkan agar tidak diubah' : 'belum diisi' ?></small><input type="password" name="resend_key" autocomplete="new-password" placeholder="re_xxxxxxxxxxxx"></label>
<?php if (setting('resend_key') !== ''): ?><label class="chk full"><input type="checkbox" name="resend_key_clear" value="1"> Hapus API key yang tersimpan</label><?php endif; ?>
<?= $in('mail_from', 'Email pengirim <small>domain harus sudah <b>Verified</b> di Resend, mis. noreply@picoe.org</small>', 'email') ?>
<?= $in('mail_from_name', 'Nama pengirim <small>kosong = singkatan konferensi</small>') ?>
<p class="full"><small>Balasan peserta diarahkan ke <b>Email resmi panitia</b> di atas. Buat API key di resend.com &rarr; API Keys &rarr; Create API key (izin <i>Sending access</i> cukup). Lalu uji di bagian &ldquo;Uji Pengiriman Email&rdquo; di bawah.</small></p>
<h3>Anti-spam</h3>
<p class="full"><small>Pendaftaran sudah otomatis menolak nama berisi tautan/simbol, email sementara, dan pengiriman form oleh bot. Untuk perlindungan terkuat, aktifkan <b>Cloudflare Turnstile</b> (gratis): Cloudflare Dashboard &rarr; Turnstile &rarr; Add widget &rarr; domain Anda &rarr; salin Site Key &amp; Secret Key ke sini. Kosongkan keduanya untuk menonaktifkan.</small></p>
<?= $in('turnstile_site', 'Turnstile Site Key') ?><?= $in('turnstile_secret', 'Turnstile Secret Key', 'password') ?>
<?= $ta('blocked_domains', 'Domain email yang ditolak (pisahkan dengan spasi atau baris baru)', 4) ?>
<h3>Konten Beranda</h3>
<?= $ta('benefits', 'Benefit (satu per baris)', 4) ?><?= $ta('supporters', 'Dukungan institusi (satu nama per baris)', 4) ?>
<?= $ta('contacts', 'Kontak WhatsApp <small>Format <code>Nama|Nomor</code>, satu per baris</small>', 4) ?><?= $ta('secretariat', 'Sekretariat', 3) ?>
<button class="btn">Simpan Pengaturan</button></form>
<p><small>Pembicara dikelola di tab <a href="?p=pembicara">Pembicara</a>; tanggal penting, topik, komite, sponsor, FAQ, pengumuman dan unduhan di tab <a href="?p=konten">Konten</a>.</small></p>
<h3 id="uji-email">Uji Pengiriman Email</h3>
<p>Layanan aktif: <b><?= resendOn() ? 'Resend API' : 'PHP mail() hosting' ?></b> &middot; Pengirim: <b><?= e(mailFrom()[0]) ?></b><?= setting('mail_driver') === 'resend' && !resendOn() ? ' &middot; <span class="tag spam">API key Resend belum diisi</span>' : '' ?></p>
<form method="post" class="filters"><?= csrf() ?><input type="hidden" name="a" value="mailtest"><input type="email" name="to" value="<?= e($u['email']) ?>" required placeholder="email tujuan"><button class="btn">Kirim Email Uji</button></form>
<p><small>Hasil setiap pengiriman tercatat di tab <a href="?p=email">Email</a> &rarr; Log Email.</small></p>
