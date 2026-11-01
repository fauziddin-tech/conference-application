<?php if (!defined('ADMIN')) exit;
/* Terjemahan konten admin (EN/ID): status, jalankan terjemahan otomatis, koreksi manual. */
$all = contentStrings(); $tot = count($all);
$rows = []; foreach ($pdo->query("SELECT * FROM translations") as $r) $rows[$r['h']][$r['lang']] = $r;
$done = 0; foreach ($all as $h => $s) if (isset($rows[$h]['en'], $rows[$h]['id'])) $done++;
$qs = trim((string)($_GET['q'] ?? '')); $only = ($_GET['f'] ?? '') === 'missing';
$list = array_filter($all, function ($s, $h) use ($qs, $only, $rows) {
    if ($only && isset($rows[$h]['en'], $rows[$h]['id'])) return false;
    if ($qs === '') return true;
    $hay = $s . ' ' . ($rows[$h]['en']['txt'] ?? '') . ' ' . ($rows[$h]['id']['txt'] ?? '');
    return mb_stripos($hay, $qs) !== false;
}, ARRAY_FILTER_USE_BOTH);
$page = max(1, (int)($_GET['pg'] ?? 1)); $per = 30; $pages = max(1, (int)ceil(count($list) / $per)); $list = array_slice($list, ($page - 1) * $per, $per, true); ?>
<h3>Terjemahan Konten (English &harr; Indonesia)</h3>
<p>Situs tampil dalam <b>bahasa Inggris</b> secara bawaan; pengunjung dapat beralih ke <b>Bahasa Indonesia</b> lewat tombol EN | ID di menu. Teks yang Anda tulis di Pengaturan, Konten, Pembicara, dan Agenda diterjemahkan otomatis (Google Translate) saat disimpan. Nama orang, nama konferensi, tema, dan lokasi tidak diterjemahkan. Hasil terjemahan bisa dikoreksi di bawah; koreksi manual tidak akan ditimpa.</p>
<p class="sysum"><span class="<?= $done === $tot ? 'ok' : 'warn' ?>"><?= $done ?> / <?= $tot ?> teks sudah diterjemahkan</span> <?= setting('auto_translate', '1') === '1' ? '<span class="ok">Terjemahan otomatis aktif</span>' : '<span class="fail">Terjemahan otomatis nonaktif</span>' ?></p>
<div class="filters">
<form method="post" class="inline"><?= csrf() ?><input type="hidden" name="a" value="trrun"><button class="btn">Terjemahkan yang Belum</button></form>
<form method="post" class="inline" data-confirm="Terjemahkan ulang semua teks? Koreksi manual tetap dipertahankan."><?= csrf() ?><input type="hidden" name="a" value="trforce"><button class="btn ghost2">Terjemahkan Ulang Semua</button></form>
<form method="post" class="inline"><?= csrf() ?><input type="hidden" name="a" value="trauto"><button class="link plain"><?= setting('auto_translate', '1') === '1' ? 'Matikan terjemahan otomatis' : 'Aktifkan terjemahan otomatis' ?></button></form></div>
<form method="get" class="filters"><input type="hidden" name="p" value="terjemahan"><input name="q" value="<?= e($qs) ?>" placeholder="Cari teks..."><label class="chk" style="margin:0"><input type="checkbox" name="f" value="missing" <?= $only ? 'checked' : '' ?>> hanya yang belum</label><button class="btn">Filter</button></form>
<?php if (!$list): ?><p>Tidak ada teks.</p><?php endif; ?>
<?php foreach ($list as $h => $s): $en = $rows[$h]['en'] ?? null; $id = $rows[$h]['id'] ?? null; ?>
<form method="post" class="card trrow"><?= csrf() ?><input type="hidden" name="a" value="trsave"><input type="hidden" name="h" value="<?= $h ?>"><input type="hidden" name="src" value="<?= e($s) ?>">
<div class="full"><small>Teks asli<?= $en && $en['src_lang'] ? ' (' . e(strtoupper($en['src_lang'])) . ')' : '' ?><?= ($en['manual'] ?? 0) || ($id['manual'] ?? 0) ? ' <span class="tag on">dikoreksi manual</span>' : '' ?><?= !$en || !$id ? ' <span class="tag spam">belum lengkap</span>' : '' ?></small><div class="src"><?= nl2br(e(mb_strimwidth($s, 0, 600, '…'))) ?></div></div>
<label>English<textarea name="en" rows="<?= min(8, max(2, substr_count($s, "\n") + 2)) ?>"><?= e($en['txt'] ?? '') ?></textarea></label>
<label>Indonesia<textarea name="id" rows="<?= min(8, max(2, substr_count($s, "\n") + 2)) ?>"><?= e($id['txt'] ?? '') ?></textarea></label>
<button class="btn">Simpan Koreksi</button></form>
<?php endforeach; ?>
<?php if ($pages > 1): ?><p class="tabs small"><?php for ($i = 1; $i <= $pages; $i++): ?><a class="<?= $i === $page ? 'on' : '' ?>" href="?p=terjemahan&pg=<?= $i ?>&q=<?= e(urlencode($qs)) ?><?= $only ? '&f=missing' : '' ?>"><?= $i ?></a><?php endfor; ?></p><?php endif; ?>
