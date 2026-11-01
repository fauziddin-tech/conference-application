<?php if (!defined('ADMIN')) exit;
$k = isset($KINDS[$_GET['k'] ?? '']) ? $_GET['k'] : 'date'; [$label, $F] = $KINDS[$k];
$list = items($k); $eid = (int)($_GET['edit'] ?? 0); $E = null; foreach ($list as $it) if ((int)$it['id'] === $eid) $E = $it;
$field = function ($name, $lab, $v) use ($k) {
    if ($name === 'body') return '<label class="full">' . e($lab) . '<textarea name="body" rows="' . ($k === 'news' || $k === 'faq' ? 5 : 2) . '">' . e($v) . '</textarea></label>';
    if ($name === 'extra' && $k === 'date') return '<label>' . e($lab) . '<input type="date" name="extra" required value="' . e($v) . '"></label>';
    if ($name === 'extra' && $k === 'news') return '<label>' . e($lab) . '<select name="extra"><option value="public">Publik (beranda &amp; dashboard)</option><option value="participants"' . ($v === 'participants' ? ' selected' : '') . '>Hanya peserta (dashboard)</option></select></label>';
    if ($name === 'image') return '<label>' . e($lab) . ' <small>(upload JPG/PNG/WEBP maks. 2 MB, atau URL)</small><input type="file" name="image" accept="image/jpeg,image/png,image/webp"><input name="image_url" placeholder="https://.../logo.png" value="' . (isurl($v) ? e($v) : '') . '"></label>';
    if ($name === 'url' && $k === 'download') return '<label>' . e($lab) . ' <small>(upload PDF/DOC/DOCX/PPTX/XLSX/ZIP maks. 10 MB, atau URL)</small><input type="file" name="file"><input name="url" placeholder="https://..." value="' . e($v) . '"></label>';
    return '<label>' . e($lab) . '<input name="' . $name . '" value="' . e($v) . '"' . ($name === 'title' ? ' required' : '') . '></label>';
}; ?>
<p class="tabs small"><?php foreach ($KINDS as $kk => $kv): ?><a href="?p=konten&k=<?= $kk ?>" class="<?= $k === $kk ? 'on' : '' ?>"><?= e($kv[0]) ?></a><?php endforeach; ?></p>
<h3><?= $E ? 'Edit' : 'Tambah' ?> <?= e($label) ?></h3>
<form method="post" enctype="multipart/form-data" class="card"><?= csrf() ?><input type="hidden" name="a" value="itemsave"><input type="hidden" name="kind" value="<?= $k ?>"><input type="hidden" name="id" value="<?= $E ? $E['id'] : 0 ?>">
<?php foreach ($F as $name => $lab) echo $field($name, $lab, $E[$name] ?? ''); ?>
<?php if ($k !== 'date' && $k !== 'news'): ?><label>Urutan <small>(angka kecil tampil lebih dulu)</small><input type="number" name="sort" value="<?= (int)($E['sort'] ?? count($list)) ?>"></label><?php endif; ?>
<?php if ($k === 'news' && !$E): ?><label class="chk full"><input type="checkbox" name="mail" value="1"> Kirim juga sebagai email ke semua pendaftar</label><?php endif; ?>
<button class="btn">Simpan</button><?php if ($E): ?> <a href="?p=konten&k=<?= $k ?>">Batal</a><?php endif; ?></form>
<h3>Daftar <?= e($label) ?> (<?= count($list) ?>)</h3>
<?php if (!$list): ?><p>Belum ada data. Bagian ini disembunyikan dari beranda sampai diisi.</p><?php else: ?>
<div class="scroll"><table><tr><?php foreach ($F as $name => $lab): ?><th><?= e(rtrim($lab, ' *')) ?></th><?php endforeach; ?><th></th></tr>
<?php foreach ($list as $it): ?><tr><?php foreach ($F as $name => $lab): $v = (string)$it[$name]; ?><td><?php if ($name === 'image' && $v): ?><img class="thumb" src="<?= e(imgSrc($v)) ?>" alt=""><?php elseif ($name === 'extra' && $k === 'date'): ?><?= e(fdate($v)) ?><?php else: ?><?= e(mb_strimwidth($v, 0, 120, '…')) ?><?php endif; ?></td><?php endforeach; ?>
<td class="nowrap"><a href="?p=konten&k=<?= $k ?>&edit=<?= $it['id'] ?>">Edit</a> &nbsp; <form method="post" class="inline" onsubmit="return confirm('Hapus item ini?')"><?= csrf() ?><input type="hidden" name="a" value="itemdel"><input type="hidden" name="kind" value="<?= $k ?>"><input type="hidden" name="id" value="<?= $it['id'] ?>"><button class="link">Hapus</button></form></td></tr><?php endforeach; ?></table></div><?php endif; ?>
