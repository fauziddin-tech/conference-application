<?php if (!defined('ADMIN')) exit;
$did = (int)($_GET['id'] ?? 0); $q = $pdo->prepare("SELECT r.*,u.email,u.institution FROM registrations r JOIN users u ON u.id=r.user_id WHERE r.id=?"); $q->execute([$did]); $x = $q->fetch();
if (!$x): ?><p>Data tidak ditemukan. <a href="?p=peserta">Kembali</a></p>
<?php return; endif;
$info = ['Title' => e($x['prefix']), 'Nama lengkap' => e($x['full_name']), 'Email' => e($x['email']), 'Institusi' => e($x['institution']), 'Negara' => e($x['country']), 'No. HP / WhatsApp' => e($x['phone']) . ' &middot; <a href="https://wa.me/' . preg_replace('/\D/', '', $x['phone']) . '" target="_blank" rel="noopener">WhatsApp</a>', 'ORCID iD' => $x['orcid'] ? '<a href="https://orcid.org/' . e($x['orcid']) . '" target="_blank" rel="noopener">' . e($x['orcid']) . '</a>' : '-',
    'Kategori' => e($x['category']), 'Kehadiran' => e($x['attendance']), 'Kebutuhan khusus / diet' => e($x['dietary'] ?: '-'),
    'Track' => e($x['track'] ?: '-'), 'Judul artikel' => e($x['article_title'] ?: '-'), 'Co-authors' => $x['coauthors'] ? nl2br(e($x['coauthors'])) : '-', 'Keywords' => e($x['keywords'] ?: '-'), 'Abstract' => $x['abstract'] ? nl2br(e($x['abstract'])) . ' <small>(' . words($x['abstract']) . ' kata)</small>' : '-',
    'File naskah' => $x['article_file'] ? '<a href="../file.php?id=' . $x['id'] . '&k=article">' . e($x['article_name']) . '</a>' : '-',
    'Camera-ready' => $x['camera_file'] ? '<a href="../file.php?id=' . $x['id'] . '&k=camera">' . e($x['camera_name']) . '</a>' : '-',
    'Biaya' => e($x['pay_amount']), 'Tanggal bayar' => e($x['pay_date'] ?: '-'), 'Nama pengirim' => e($x['pay_sender'] ?: '-'), 'Bukti bayar' => $x['pay_file'] ? '<a href="../file.php?id=' . $x['id'] . '&k=pay">' . e($x['pay_name']) . '</a>' : '-',
    'Kode tiket' => e($x['ticket']), 'Check-in' => $x['checked_in_at'] ? e($x['checked_in_at']) : '- <a href="../checkin.php?c=' . e($x['ticket']) . '">check-in</a>',
    'Mendaftar' => e($x['created_at']), 'Terakhir diubah' => e($x['updated_at'])];
if (!$x['article_title']) foreach (['Track', 'Judul artikel', 'Co-authors', 'Keywords', 'Abstract', 'File naskah', 'Camera-ready'] as $k) unset($info[$k]); ?>
<p><a href="?p=peserta">&larr; Kembali ke daftar peserta</a> &middot; <a href="?p=edit&id=<?= $x['user_id'] ?>">Edit akun / reset password</a></p>
<h3><?= no('R', $x['id']) ?> &middot; <?= e($x['prefix'] . ' ' . $x['full_name']) ?></h3>
<p class="docs-inline">Dokumen: <?php foreach (['invoice' => 'Invoice', 'receipt' => 'Kwitansi', 'badge' => 'Badge', 'invitation' => 'Undangan', 'loa' => 'LoA', 'cert' => 'Sertifikat'] as $k => $l): ?><a href="../doc.php?t=<?= $k ?>&id=<?= $x['id'] ?>" target="_blank"><?= $l ?></a> <?php endforeach; ?><small>(admin dapat melihat semua dokumen untuk pratinjau)</small></p>
<div class="scroll"><table><?php foreach ($info as $k => $v): ?><tr><th style="width:220px"><?= e($k) ?></th><td><?= $v ?></td></tr><?php endforeach; ?></table></div>

<?php if ($x['article_title']):
    $rv = $pdo->prepare("SELECT v.*,u.name FROM reviews v JOIN users u ON u.id=v.reviewer_id WHERE v.reg_id=? ORDER BY v.id"); $rv->execute([$x['id']]); $rv = $rv->fetchAll();
    $avail = $pdo->prepare("SELECT id,name,institution FROM users WHERE role='reviewer' AND id NOT IN(SELECT reviewer_id FROM reviews WHERE reg_id=?) ORDER BY name"); $avail->execute([$x['id']]); $avail = $avail->fetchAll(); ?>
<h3>Peer Review</h3>
<?php if (!$rv): ?><p>Belum ada reviewer yang ditugaskan.</p><?php endif; ?>
<?php foreach ($rv as $r): ?><div class="review"><b><?= e($r['name']) ?></b> &middot; <?= $r['submitted_at'] ? 'Skor <b>' . (int)$r['score'] . '/5</b> &middot; <span class="badge">' . e(lbl($REC, $r['recommendation'])) . '</span> &middot; <small>' . e($r['submitted_at']) . '</small>' : '<span class="badge pending">Belum mereview</span>' ?>
<?php if ($r['submitted_at']): ?><p><small>Untuk penulis:</small><br><?= nl2br(e($r['to_author'])) ?></p><?php if ($r['to_committee']): ?><p><small>Rahasia untuk panitia:</small><br><?= nl2br(e($r['to_committee'])) ?></p><?php endif; endif; ?>
<form method="post" class="inline" onsubmit="return confirm('Hapus penugasan reviewer ini (beserta reviewnya)?')"><?= csrf() ?><input type="hidden" name="a" value="unassign"><input type="hidden" name="id" value="<?= $x['id'] ?>"><input type="hidden" name="reviewer" value="<?= $r['reviewer_id'] ?>"><button class="link">Hapus penugasan</button></form></div><?php endforeach; ?>
<?php if ($avail): ?><form method="post" class="filters"><?= csrf() ?><input type="hidden" name="a" value="assign"><input type="hidden" name="id" value="<?= $x['id'] ?>"><select name="reviewer" required><option value="">-- pilih reviewer --</option><?php foreach ($avail as $r): ?><option value="<?= $r['id'] ?>"><?= e($r['name'] . ($r['institution'] ? ' (' . $r['institution'] . ')' : '')) ?></option><?php endforeach; ?></select><button class="btn">Tugaskan Reviewer</button></form>
<?php else: ?><p><small>Tambahkan akun reviewer di tab <a href="?p=pengguna">Akun</a>.</small></p><?php endif; ?>
<?php endif; ?>

<h3>Verifikasi &amp; Keputusan</h3>
<form method="post" class="card"><?= csrf() ?><input type="hidden" name="a" value="regsave"><input type="hidden" name="id" value="<?= $x['id'] ?>">
<label>Status pembayaran<select name="pay_status"><?php foreach ($PAY as $s => $l): ?><option value="<?= $s ?>" <?= $x['pay_status'] === $s ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
<?php if ($x['article_title']): ?><label>Status naskah<select name="paper_status"><?php foreach ($PAPER as $s => $l): ?><option value="<?= $s ?>" <?= $x['paper_status'] === $s ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></label><?php else: ?><input type="hidden" name="paper_status" value="<?= e($x['paper_status']) ?>"><?php endif; ?>
<label class="full">Catatan untuk peserta (tampil di dashboard &amp; email)<textarea name="admin_note" rows="3"><?= e($x['admin_note']) ?></textarea></label>
<label class="chk full"><input type="checkbox" name="notify" value="1" checked> Kirim email notifikasi ke peserta bila status berubah</label>
<button class="btn">Simpan</button></form>
