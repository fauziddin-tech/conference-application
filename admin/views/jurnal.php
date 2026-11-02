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
<h3>Tambah Jurnal</h3><form method="post" class="card"><?= csrf() ?><input type="hidden" name="a" value="jradd">
<label>Nama jurnal *<input name="name" required></label><label>Link / URL jurnal<input name="url" placeholder="https://..."></label>
<label>Akreditasi / indeks<input name="accr" placeholder="SINTA 2 / Scopus Q3"></label><label>Penerbit<input name="publisher"></label>
<label>ISSN<input name="issn" placeholder="p-ISSN 0000-0000 / e-ISSN 0000-0000"></label><label>Link Focus &amp; Scope<input name="scope" placeholder="https://.../about/editorialPolicies#focusAndScope"></label>
<label>Link panduan penulis<input name="guide" placeholder="https://..."></label><label>Link gambar cover jurnal<input name="cover" placeholder="https://.../cover.jpg"></label>
<label class="full">Catatan (biaya publikasi, jadwal terbit, dll.)<input name="note"></label><button class="btn">Tambah</button></form>
<h3>Daftar Jurnal</h3><?php foreach (rows('journals') as $i => $j): $j = array_pad($j, 9, ''); ?>
<details class="card reg"><summary><b><?= e($j[0]) ?></b><?= $j[2] ? ' &middot; ' . e($j[2]) : '' ?><?= $j[1] ? ' &middot; <small>' . e($j[1]) . '</small>' : '' ?></summary>
<form method="post" class="sub"><?= csrf() ?><input type="hidden" name="a" value="jredit"><input type="hidden" name="i" value="<?= $i ?>">
<label>Nama jurnal *<input name="name" required value="<?= e($j[0]) ?>"></label><label>Link / URL jurnal<input name="url" value="<?= e($j[1]) ?>"></label>
<label>Akreditasi / indeks<input name="accr" value="<?= e($j[2]) ?>"></label><label>Penerbit<input name="publisher" value="<?= e($j[3]) ?>"></label>
<label>ISSN<input name="issn" value="<?= e($j[4]) ?>"></label><label>Link Focus &amp; Scope<input name="scope" value="<?= isurl($j[5]) ? e($j[5]) : '' ?>" placeholder="https://..."></label>
<label>Link panduan penulis<input name="guide" value="<?= e($j[6]) ?>"></label><label>Link gambar cover jurnal<input name="cover" value="<?= isurl($j[7]) ? e($j[7]) : '' ?>" placeholder="https://.../cover.jpg"></label>
<label class="full">Catatan<input name="note" value="<?= e($j[8]) ?>"></label>
<?php if (isurl($j[7])): ?><div class="full"><small>Pratinjau cover:</small><br><img class="thumb" style="width:80px;height:auto;aspect-ratio:3/4" src="<?= e($j[7]) ?>" alt="" referrerpolicy="no-referrer"></div><?php endif; ?>
<div class="full"><button class="btn">Simpan</button></div></form>
<form method="post" data-confirm="Hapus jurnal ini?" style="margin-top:10px"><?= csrf() ?><input type="hidden" name="a" value="jrdel"><input type="hidden" name="i" value="<?= $i ?>"><button class="link">Hapus jurnal</button></form></details><?php endforeach; ?>
