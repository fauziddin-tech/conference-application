<?php if (!defined('ADMIN')) exit; ?>
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
