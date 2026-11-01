<?php if (!defined('ADMIN')) exit;
/* Pemeriksaan sistem (hanya membaca) + Backup database. Diadaptasi dari MyThesis. */
$root = realpath(__DIR__ . '/../..');
$bytes = function ($v) { $v = trim((string)$v); if ($v === '' || $v === '-1') return PHP_INT_MAX; $n = (float)$v; $u = strtolower(substr($v, -1)); if ($u === 'g') $n *= 1024 ** 3; elseif ($u === 'm') $n *= 1024 ** 2; elseif ($u === 'k') $n *= 1024; return (int)$n; };
$cnt = function ($sql) use ($pdo) { try { return (int)$pdo->query($sql)->fetchColumn(); } catch (Exception $x) { return null; } };
$G = []; // grup => [[label, ok|warn|fail, keterangan, tautan perbaikan?], ...]

/* ---------- Server ---------- */
$S = [];
$S[] = ['Versi PHP', version_compare(PHP_VERSION, '8.0', '>=') ? 'ok' : (version_compare(PHP_VERSION, '7.4', '>=') ? 'warn' : 'fail'), 'PHP ' . PHP_VERSION . (version_compare(PHP_VERSION, '8.0', '<') ? ' — disarankan PHP 8.1+ (cPanel → MultiPHP Manager).' : '')];
foreach (['pdo_mysql' => ['fail', 'koneksi database'], 'mbstring' => ['fail', 'teks multibahasa'], 'curl' => ['fail', 'email Resend & captcha Turnstile'], 'gd' => ['warn', 'validasi foto & logo'], 'zlib' => ['fail', 'kompresi backup database'], 'openssl' => ['warn', 'koneksi aman ke Resend']] as $ext => $i) {
    $on = extension_loaded($ext);
    $S[] = ['Ekstensi ' . $ext, $on ? 'ok' : $i[0], ($on ? 'Aktif' : 'Belum aktif (cPanel → Select PHP Version → Extensions)') . ' — dipakai untuk ' . $i[1] . '.'];
}
$um = ini_get('upload_max_filesize'); $pm = ini_get('post_max_size');
$S[] = ['Batas unggah file', $bytes($um) >= 10 * 1048576 ? 'ok' : 'fail', "upload_max_filesize = $um (aplikasi menerima file hingga 10 MB)."];
$S[] = ['Batas ukuran formulir', $bytes($pm) >= 21 * 1048576 ? 'ok' : 'warn', "post_max_size = $pm (disarankan ≥ 25M karena formulir bisa memuat naskah + bukti bayar)."];
$dbNow = (string)$pdo->query('SELECT NOW()')->fetchColumn(); $gap = abs(strtotime($dbNow) - time());
$S[] = ['Jam PHP & database', $gap <= 300 ? 'ok' : 'warn', 'Zona waktu ' . date_default_timezone_get() . ' (' . date('H:i') . '), database ' . date('H:i', strtotime($dbNow)) . ($gap > 300 ? ' — selisih ' . round($gap / 60) . ' menit; atur zona waktu di Pengaturan.' : '.')];
$de = filter_var(ini_get('display_errors'), FILTER_VALIDATE_BOOLEAN);
$S[] = ['Tampilan pesan error', $de ? 'warn' : 'ok', $de ? 'display_errors aktif; matikan di situs produksi agar detail teknis tidak terlihat pengunjung.' : 'display_errors nonaktif.'];
$G['Server'] = $S;

/* ---------- Database ---------- */
$D = [];
$ver = (int)setting('db_version', '1');
$D[] = ['Versi skema', $ver >= DB_VERSION ? 'ok' : 'fail', "Versi $ver dari " . DB_VERSION . ($ver >= DB_VERSION ? ' — pembaruan otomatis sudah diterapkan.' : ' — muat ulang halaman untuk menjalankan migrasi.')];
$have = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN); $need = ['settings', 'users', 'registrations', 'agenda', 'items', 'reviews', 'paper_files', 'notifications', 'mail_log', 'throttle'];
$miss = array_diff($need, $have);
$D[] = ['Tabel', $miss ? 'fail' : 'ok', $miss ? 'Tidak ditemukan: ' . implode(', ', $miss) : count($need) . ' tabel inti lengkap.'];
$D[] = ['Server database', 'ok', (string)$pdo->query('SELECT VERSION()')->fetchColumn()];
$G['Database'] = $D;

/* ---------- Keamanan & penyimpanan ---------- */
$K = [];
$K[] = ['HTTPS', $HTTPS ? 'ok' : 'fail', $HTTPS ? 'Situs diakses melalui HTTPS.' : 'Situs diakses tanpa HTTPS; password & dokumen dapat disadap. Aktifkan SSL (AutoSSL / Cloudflare).'];
$K[] = ['Folder install/', is_dir($root . '/install') ? 'fail' : 'ok', is_dir($root . '/install') ? 'Masih ada — hapus folder install/ lewat File Manager.' : 'Sudah dihapus.'];
$ht = is_file($root . '/.htaccess') ? (string)file_get_contents($root . '/.htaccess') : '';
$K[] = ['Proteksi config.php', strpos($ht, 'config\.php') !== false ? 'ok' : 'fail', strpos($ht, 'config\.php') !== false ? 'config.php tidak dapat dibuka dari browser.' : 'Aturan pelindung config.php tidak ditemukan di .htaccess (pastikan file .htaccess ikut terunggah).'];
foreach (['uploads/private' => 'Naskah & bukti bayar', 'lib' => 'Kode inti', 'admin/views' => 'Tampilan admin', 'tools' => 'Skrip backup'] as $f => $l) {
    $ok = is_file($root . '/' . $f . '/.htaccess');
    $K[] = ['Proteksi ' . $f . '/', $ok ? 'ok' : 'fail', $l . ($ok ? ' tidak dapat dibuka langsung dari browser.' : ' — file .htaccess pelindung tidak ditemukan.')];
}
foreach (['uploads' => 'Foto, logo & unduhan', 'uploads/private' => 'Naskah & bukti bayar'] as $f => $l) {
    $w = is_dir($root . '/' . $f) && is_writable($root . '/' . $f);
    $K[] = ['Folder ' . $f . '/', $w ? 'ok' : 'fail', $l . ($w ? ' dapat disimpan.' : ' tidak dapat disimpan; periksa permission (755).')];
}
$free = @disk_free_space($root);
if ($free !== false) $K[] = ['Ruang penyimpanan', $free >= 500 * 1048576 ? 'ok' : 'warn', 'Sisa ' . number_format($free / 1073741824, 1, ',', '.') . ' GB.'];
$K[] = ['Captcha Turnstile', captchaOn() ? 'ok' : 'warn', captchaOn() ? 'Aktif pada form daftar & lupa password.' : 'Belum aktif — disarankan untuk menahan akun spam.', '?p=setting'];
$spam = 0; foreach ($pdo->query("SELECT u.name,u.email FROM users u WHERE u.role='peserta' AND NOT EXISTS(SELECT 1 FROM registrations r WHERE r.user_id=u.id)") as $s) if (!validName($s['name']) || blockedEmail($s['email'])) $spam++;
$K[] = ['Akun spam', $spam ? 'warn' : 'ok', $spam ? "$spam akun mencurigakan belum dihapus." : 'Tidak ada akun mencurigakan.', '?p=pengguna'];
$G['Keamanan & Penyimpanan'] = $K;

/* ---------- Backup ---------- */
$B = []; $bs = backupStatus(); $bl = backupList();
if (!$bs) $B[] = ['Backup terakhir', 'fail', 'Belum pernah berjalan. Klik "Buat Backup Sekarang" di bawah, lalu jadwalkan Cron Jobs.'];
elseif (empty($bs['ok'])) $B[] = ['Backup terakhir', 'fail', 'Gagal pada ' . date('d M Y H:i', strtotime($bs['time'])) . ': ' . ($bs['msg'] ?? '-')];
else { $age = time() - strtotime($bs['time']); $B[] = ['Backup terakhir', $age <= 36 * 3600 ? 'ok' : 'warn', date('d M Y H:i', strtotime($bs['time'])) . ' (' . $bs['trigger'] . ') — ' . number_format($bs['size'] / 1024, 1, ',', '.') . ' KB, ' . $bs['tables'] . ' tabel, ' . $bs['rows'] . ' baris' . ($age > 36 * 3600 ? '. Lebih dari 36 jam yang lalu — periksa Cron Jobs.' : '.')]; }
$B[] = ['Lokasi backup', backupInsideWeb() ? 'warn' : 'ok', backupDir() . (backupInsideWeb() ? ' — berada di dalam folder web; sebaiknya atur BACKUP_DIR di config.php ke folder di luar public_html.' : ' (di luar folder web).')];
$B[] = ['Backup otomatis harian', setting('backup_auto', '1') === '1' ? 'ok' : 'warn', setting('backup_auto', '1') === '1' ? 'Aktif: dibuat saat admin membuka panel bila backup terakhir > 24 jam (cadangan bila Cron belum diatur).' : 'Nonaktif — pastikan Cron Jobs sudah dijadwalkan.'];
$G['Backup Database'] = $B;

/* ---------- Email ---------- */
$E = [];
$E[] = ['Layanan pengirim', resendOn() ? 'ok' : 'warn', resendOn() ? 'Resend API.' : (setting('mail_driver') === 'resend' ? 'Resend dipilih tetapi API key kosong — memakai mail() hosting.' : 'mail() hosting — disarankan Resend agar tidak masuk spam.'), '?p=setting#email-kirim'];
$mf = mailFrom()[0]; $site = preg_replace('/^www\./', '', preg_replace('/:\d+$/', '', host()));
$E[] = ['Alamat pengirim', filter_var(setting('mail_from'), FILTER_VALIDATE_EMAIL) ? 'ok' : 'warn', $mf . (filter_var(setting('mail_from'), FILTER_VALIDATE_EMAIL) ? (resendOn() ? ' — domain harus Verified di Resend.' : '') : ' (otomatis; isi Email pengirim di Pengaturan).'), '?p=setting#email-kirim'];
$m7 = $pdo->query("SELECT COUNT(*) n,SUM(ok=1) s FROM mail_log WHERE created_at > NOW() - INTERVAL 7 DAY")->fetch();
$lastErr = $pdo->query("SELECT error FROM mail_log WHERE ok=0 AND error IS NOT NULL ORDER BY id DESC LIMIT 1")->fetchColumn();
$E[] = ['Pengiriman 7 hari', !$m7['n'] ? 'warn' : ((int)$m7['s'] === (int)$m7['n'] ? 'ok' : ((int)$m7['s'] ? 'warn' : 'fail')), $m7['n'] ? (int)$m7['s'] . ' dari ' . (int)$m7['n'] . ' email terkirim.' . ($lastErr && (int)$m7['s'] < (int)$m7['n'] ? ' Error terakhir: ' . $lastErr : '') : 'Belum ada email dalam 7 hari terakhir — kirim email uji.', '?p=email'];
$G['Email'] = $E;

/* ---------- Data & konfigurasi konferensi ---------- */
$C = [];
foreach (['event_start' => 'Waktu mulai acara (hitung mundur & kalender)', 'payment_info' => 'Instruksi pembayaran', 'signers' => 'Penandatangan LoA & sertifikat', 'email' => 'Email resmi panitia'] as $k => $l)
    $C[] = [$l, setting($k) !== '' ? 'ok' : 'warn', setting($k) !== '' ? 'Terisi.' : 'Belum diisi.', '?p=setting'];
$pend = $cnt("SELECT COUNT(*) FROM registrations WHERE pay_status='pending'");
$C[] = ['Pembayaran menunggu verifikasi', $pend ? 'warn' : 'ok', $pend ? "$pend pendaftar menunggu verifikasi." : 'Tidak ada antrean.', '?p=peserta&pay=pending'];
$norev = $cnt("SELECT COUNT(*) FROM registrations r WHERE r.article_title<>'' AND r.paper_status='submitted' AND NOT EXISTS(SELECT 1 FROM reviews v WHERE v.reg_id=r.id AND v.round=r.review_round)");
$C[] = ['Naskah tanpa reviewer', $norev ? 'warn' : 'ok', $norev ? "$norev naskah belum ditugaskan ke reviewer." : 'Semua naskah sudah ditugaskan.', '?p=naskah&st=submitted'];
$late = $cnt("SELECT COUNT(*) FROM reviews v JOIN registrations r ON r.id=v.reg_id AND v.round=r.review_round WHERE v.submitted_at IS NULL AND v.due_date < CURDATE() AND r.paper_status IN('submitted','under_review')");
$C[] = ['Review terlambat', $late ? 'warn' : 'ok', $late ? "$late review melewati tenggat." : 'Tidak ada review terlambat.', '?p=naskah'];
$G['Konferensi'] = $C;

$tot = ['ok' => 0, 'warn' => 0, 'fail' => 0]; foreach ($G as $rows) foreach ($rows as $r) $tot[$r[1]]++;
$icon = ['ok' => '&#10004;', 'warn' => '!', 'fail' => '&#10006;']; ?>
<h3>Pemeriksaan Sistem</h3>
<p class="sysum"><span class="ok"><?= $tot['ok'] ?> baik</span> <span class="warn"><?= $tot['warn'] ?> perlu perhatian</span> <span class="fail"><?= $tot['fail'] ?> bermasalah</span> <small>&middot; halaman ini hanya membaca, tidak mengubah data</small></p>
<div class="sysgrid"><?php foreach ($G as $g => $rows): ?><div class="card"><h4><?= e($g) ?></h4><ul class="checks"><?php foreach ($rows as $r): ?><li class="<?= $r[1] ?>"><i><?= $icon[$r[1]] ?></i><div><b><?= e($r[0]) ?></b><br><small><?= e($r[2]) ?></small><?php if (!empty($r[3]) && $r[1] !== 'ok'): ?> <a href="<?= e($r[3]) ?>">perbaiki &rarr;</a><?php endif; ?></div></li><?php endforeach; ?></ul></div><?php endforeach; ?></div>

<h3 id="backup">Backup Database</h3>
<div class="dgrid"><div class="card">
<form method="post"><?= csrf() ?><input type="hidden" name="a" value="backuprun"><button class="btn">Buat Backup Sekarang</button></form>
<form method="post" class="card" style="margin-top:14px"><?= csrf() ?><input type="hidden" name="a" value="backupset">
<label class="chk full"><input type="checkbox" name="backup_auto" value="1"<?= setting('backup_auto', '1') === '1' ? ' checked' : '' ?>> Backup otomatis harian saat admin membuka panel (cadangan bila Cron belum diatur)</label>
<label>Simpan backup selama (hari)<input type="number" name="backup_keep" min="1" max="365" value="<?= (int)setting('backup_keep', '14') ?>"></label>
<button class="btn">Simpan</button></form></div>
<div class="card"><h4>Jadwalkan di cPanel &rarr; Cron Jobs</h4><p><small>Pilih <b>Once Per Day</b> (mis. pukul 02:00), lalu isi Command:</small></p>
<pre class="cmd">php <?= e($root) ?>/tools/backup-database.php</pre>
<p><small>Bila cPanel memakai beberapa versi PHP, gunakan path lengkap, mis. <code>/usr/local/bin/php</code> atau <code>/opt/cpanel/ea-php82/root/usr/bin/php</code>. Backup disimpan di <code><?= e(backupDir()) ?></code>. Untuk memulihkan: unduh file <code>.sql.gz</code>, lalu impor lewat phpMyAdmin &rarr; Import.</small></p>
<p><small>File unggahan (naskah, bukti bayar, foto) berada di folder <code>uploads/</code> — sertakan dalam fitur <b>Backup</b> cPanel (Home Directory) secara berkala.</small></p></div></div>
<?php if ($bl): ?><div class="scroll"><table><tr><th>File</th><th>Ukuran</th><th>Waktu</th><th></th></tr>
<?php foreach ($bl as $b): ?><tr><td><?= e($b['name']) ?></td><td><?= number_format($b['size'] / 1024, 1, ',', '.') ?> KB</td><td><?= e(date('d M Y H:i', $b['time'])) ?></td>
<td class="nowrap"><a href="?p=backupdl&f=<?= e(urlencode($b['name'])) ?>">Unduh</a> &nbsp; <form method="post" class="inline" data-confirm="Hapus file backup ini?"><?= csrf() ?><input type="hidden" name="a" value="backupdel"><input type="hidden" name="f" value="<?= e($b['name']) ?>"><button class="link">Hapus</button></form></td></tr><?php endforeach; ?></table></div>
<?php else: ?><p>Belum ada file backup.</p><?php endif; ?>
