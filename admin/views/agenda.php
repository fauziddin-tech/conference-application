<?php if (!defined('ADMIN')) exit;
$eid = (int)($_GET['edit'] ?? 0); $E = null;
if ($eid) { $q = $pdo->prepare("SELECT * FROM agenda WHERE id=?"); $q->execute([$eid]); $E = $q->fetch() ?: null; }
$days = $pdo->query("SELECT DISTINCT day FROM agenda ORDER BY day")->fetchAll(PDO::FETCH_COLUMN); ?>
<h3><?= $E ? 'Edit Sesi' : 'Tambah Sesi' ?></h3>
<form method="post" class="card"><?= csrf() ?><input type="hidden" name="a" value="<?= $E ? 'agedit' : 'agenda' ?>"><input type="hidden" name="id" value="<?= $E['id'] ?? 0 ?>">
<label>Hari/Tanggal * <small>dipakai untuk tab per hari; urut alfabetis, mis. "Day 1 - 3 Dec"</small><input name="day" required list="days" value="<?= e($E['day'] ?? '') ?>" placeholder="Day 1 - 3 Dec 2026"></label><datalist id="days"><?php foreach ($days as $d): ?><option><?= e($d) ?></option><?php endforeach; ?></datalist>
<label>Waktu<input name="time" value="<?= e($E['time'] ?? '') ?>" placeholder="09:00-10:00"></label>
<label>Judul Sesi *<input name="title" required value="<?= e($E['title'] ?? '') ?>"></label><label>Pembicara / Moderator<input name="speaker" value="<?= e($E['speaker'] ?? '') ?>"></label>
<label>Ruang<input name="room" value="<?= e($E['room'] ?? '') ?>"></label><label>Track / jenis sesi<input name="track" value="<?= e($E['track'] ?? '') ?>" placeholder="Plenary, Parallel A, Break"></label>
<label class="full">Deskripsi (opsional)<textarea name="description" rows="2"><?= e($E['description'] ?? '') ?></textarea></label>
<button class="btn"><?= $E ? 'Simpan' : 'Tambah' ?></button><?php if ($E): ?> <a href="?p=agenda">Batal</a><?php endif; ?></form>
<div class="scroll"><table><tr><th>Hari</th><th>Waktu</th><th>Sesi</th><th>Pembicara</th><th>Ruang / Track</th><th></th></tr>
<?php foreach (agendaRows() as $x): ?><tr><td><?= e($x['day']) ?></td><td><?= e($x['time']) ?></td><td><?= e($x['title']) ?></td><td><?= e($x['speaker']) ?></td><td><?= e(trim($x['room'] . ' ' . $x['track'])) ?></td>
<td class="nowrap"><a href="?p=agenda&edit=<?= $x['id'] ?>">Edit</a> &nbsp; <form method="post" class="inline" data-confirm="Hapus sesi ini?"><?= csrf() ?><input type="hidden" name="a" value="delag"><input type="hidden" name="id" value="<?= $x['id'] ?>"><button class="link">Hapus</button></form></td></tr><?php endforeach; ?></table></div>
