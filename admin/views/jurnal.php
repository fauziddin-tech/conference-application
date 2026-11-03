<?php if (!defined('ADMIN')) exit; $bf = bookFile(); ?>
<h3 id="buku">Book of Abstracts (Flipbook)</h3>
<p><small>Unggah PDF Book of Abstracts / buku program; pengunjung membacanya seperti buku yang dibalik per halaman di <a href="../book.php" target="_blank">book.php</a>. Halaman PDF dirender langsung di browser, jadi tidak perlu mengubah PDF menjadi gambar.</small></p>
<form method="post" enctype="multipart/form-data" class="card"><?= csrf() ?><input type="hidden" name="a" value="bookset">
<label class="chk full"><input type="checkbox" name="book_on" value="1" <?= setting('book_on') === '1' ? 'checked' : '' ?>> <span><b>Tampilkan Book of Abstracts</b> (tombol muncul di beranda, bagian Program, dan dashboard peserta)</span></label>
<label>File PDF <small><?= $bf ? 'Saat ini: ' . e(setting('book_name') ?: basename($bf)) . ' (' . (($sz = filesize(__DIR__ . '/../../' . $bf)) >= 1048576 ? round($sz / 1048576, 1) . ' MB' : ceil($sz / 1024) . ' KB') . '). Unggah lagi untuk mengganti.' : 'maks. 80 MB, tetapi juga dibatasi server: ' . e(ini_get('upload_max_filesize')) ?></small><input type="file" name="book" accept="application/pdf,.pdf"></label>
<label>atau nama file di server <small>untuk PDF besar: unggah via File Manager cPanel ke folder <code>uploads</code>, lalu tulis namanya, mis. <code>book-of-abstracts.pdf</code></small><input name="book_path" value="<?= $bf && strpos($bf, 'uploads/dl_') !== 0 ? e(substr($bf, 8)) : '' ?>" placeholder="book-of-abstracts.pdf"></label>
<label>Judul publikasi<input name="book_title" value="<?= e(setting('book_title')) ?>" placeholder="Book of Abstracts &amp; Conference Programme"></label>
<label class="chk"><input type="checkbox" name="book_zoom" value="1" <?= setting('book_zoom', '1') === '1' ? 'checked' : '' ?>> <span>Tampilkan link Zoom di halaman buku <small>(hanya untuk peserta online terverifikasi &amp; admin yang sedang login)</small></span></label>
<label>Link tambahan: label <small>mis. Konfirmasi Kehadiran</small><input name="book_link_label" value="<?= e(setting('book_link_label')) ?>" placeholder="Konfirmasi Kehadiran"></label>
<label>Link tambahan: URL<input name="book_link_url" value="<?= e(setting('book_link_url')) ?>" placeholder="https://forms.gle/..."></label>
<label>Link tambahan: keterangan<input name="book_link_note" value="<?= e(setting('book_link_note')) ?>" placeholder="Semua pemakalah dan peserta wajib memilih hadir luring atau daring."></label>
<label class="chk"><input type="checkbox" name="book_link_req" value="1" <?= setting('book_link_req') === '1' ? 'checked' : '' ?>> <span>Tandai link tambahan sebagai <b>WAJIB</b> (disorot &amp; muncul juga di toolbar buku)</span></label>
<?php if ($bf): ?><label class="chk full"><input type="checkbox" name="book_rm" value="1"> <span>Hapus file PDF</span></label><?php endif; ?>
<div class="full vbtn"><button class="btn">Simpan</button><?php if ($bf): ?><a class="btn ghost2" href="../book.php" target="_blank">Lihat Buku</a><?php endif; ?></div></form>
<?php $bc = []; foreach ($pdo->query("SELECT paper_status,pay_status,COUNT(*) n FROM registrations WHERE article_title<>'' AND abstract<>'' GROUP BY paper_status,pay_status") as $r) { $bc[$r['paper_status']][0] = ($bc[$r['paper_status']][0] ?? 0) + $r['n']; if ($r['pay_status'] === 'verified') $bc[$r['paper_status']][1] = ($bc[$r['paper_status']][1] ?? 0) + $r['n']; }
$bn = function ($s) use ($bc) { $a = $bc[$s][0] ?? 0; $b = $bc[$s][1] ?? 0; return '<small>' . $a . ' naskah, ' . $b . ' sudah bayar</small>'; }; ?>
<h3 id="susun">Susun Draf Book of Abstracts (Word)</h3>
<p><small>Membuat file <b>.docx</b> berisi abstrak yang sudah masuk, dikelompokkan per track, lengkap dengan sampul, daftar isi, jadwal, pembicara, panitia, dan indeks penulis. Buka di Microsoft Word, pilih <b>Yes</b> saat ditanya memperbarui field (daftar isi &amp; indeks terisi otomatis), lengkapi bagian yang <span style="background:#ff0">bertanda kuning</span>, lalu simpan sebagai PDF dan unggah di bagian Flipbook di atas.</small></p>
<form method="post" class="card boa"><?= csrf() ?><input type="hidden" name="a" value="boa">
<fieldset class="full"><legend>Naskah yang dimasukkan</legend>
<label class="chk"><input type="checkbox" name="st[]" value="accepted" checked> <span>Accepted <?= $bn('accepted') ?></span></label>
<label class="chk"><input type="checkbox" name="st[]" value="revision"> <span>Revision <?= $bn('revision') ?></span></label>
<label class="chk"><input type="checkbox" name="st[]" value="under_review"> <span>Under review <?= $bn('under_review') ?></span></label>
<label class="chk"><input type="checkbox" name="st[]" value="submitted"> <span>Submitted <?= $bn('submitted') ?></span></label>
<label class="chk"><input type="checkbox" name="paid" value="1"> <span>Hanya yang <b>pembayarannya sudah terverifikasi</b></span></label></fieldset>
<label>Urutan dalam tiap track<select name="sort"><option value="title">Judul (A–Z)</option><option value="author">Nama penulis (A–Z)</option><option value="id">Nomor pendaftaran</option></select></label>
<fieldset class="full"><legend>Bagian buku</legend><div class="chkgrid">
<?php foreach (['colophon' => 'Halaman judul & hak cipta', 'welcome' => 'Kata sambutan (placeholder per penandatangan)', 'committee' => 'Susunan panitia', 'toc' => 'Daftar isi', 'program' => 'Jadwal acara', 'speakers' => 'Pembicara utama', 'index' => 'Indeks penulis', 'journals' => 'Jurnal publikasi', 'sponsors' => 'Sponsor &amp; mitra'] as $k => $l): ?><label class="chk"><input type="checkbox" name="sec[]" value="<?= $k ?>" checked> <span><?= $l ?></span></label><?php endforeach; ?>
<label class="chk"><input type="checkbox" name="onepage" value="1" checked> <span>Satu abstrak per halaman</span></label>
<label class="chk"><input type="checkbox" name="email" value="1" checked> <span>Tampilkan email penulis korespondensi</span></label></div></fieldset>
<div class="full"><button class="btn">Unduh Draf (.docx)</button></div></form>
<?php $JB = '../'; include __DIR__ . '/../../views/journals.php'; ?>
