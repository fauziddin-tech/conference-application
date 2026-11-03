<?php require 'inc.php'; $u = need(['jurnal', 'admin']); chk();
/*
 * Halaman Koordinator Jurnal: menambah, mengubah, mengurutkan, dan menghapus jurnal mitra
 * yang tampil di bagian "Publication Opportunities" beranda. Admin juga bisa membuka halaman ini.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = post('a');
    if (in_array($a, ['jradd', 'jredit', 'jrdel', 'jrup', 'jrdown'], true) && ($m = jrnHandle($a, $u)) !== '') flash($m);
    go('jurnal.php');
}
$n = count(rows('journals'));
head('Jurnal Mitra'); ?>
<h2>Jurnal Mitra</h2>
<p>Halo, <b><?= e($u['name']) ?></b>. Jurnal di halaman ini tampil di bagian <b>Publication Opportunities</b> di beranda, sesuai urutan daftar di bawah. Saat ini ada <b><?= $n ?></b> jurnal.
<a href="<?= BASE ?>#publication" target="_blank">Lihat di beranda &rarr;</a></p>
<div class="panel"><small>Tips: isi <b>link gambar cover</b> dengan alamat gambar sampul dari situs jurnal (klik kanan gambar sampul → <i>Copy image address</i>). Isi <b>Akreditasi / indeks</b> singkat saja, misalnya <i>SINTA 2</i> atau <i>Scopus Q3</i>. Catatan cocok untuk biaya publikasi atau jadwal terbit.</small></div>
<?php include __DIR__ . '/views/journals.php'; ?>
<?php foot();
