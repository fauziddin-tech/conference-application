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
<p><a href="?p=peserta">&larr; Kembali ke daftar peserta</a> &middot; <a href="?p=edit&id=<?= $x['user_id'] ?>">Edit akun / reset password</a> &middot; <a href="#hapus" class="neg">Hapus peserta</a></p>
<h3><?= no('R', $x['id']) ?> &middot; <?= e($x['prefix'] . ' ' . $x['full_name']) ?></h3>
<p class="docs-inline">Dokumen: <?php foreach (['invoice' => 'Invoice', 'receipt' => 'Kwitansi', 'badge' => 'Badge', 'invitation' => 'Undangan', 'loa' => 'LoA', 'cert' => 'Sertifikat'] as $k => $l): ?><a class="js-doc" data-title="<?= $l ?> · <?= no('R', $x['id']) ?>" href="../doc.php?t=<?= $k ?>&id=<?= $x['id'] ?>" target="_blank"><?= $l ?></a> <?php endforeach; ?><small>(admin dapat melihat semua dokumen untuk pratinjau)</small></p>
<div class="scroll"><table><?php foreach ($info as $k => $v): ?><tr><th style="width:220px"><?= e($k) ?></th><td><?= $v ?></td></tr><?php endforeach; ?></table></div>

<?php if ($x['article_title']):
    $cur = (int)$x['review_round'];
    $avail = $pdo->prepare("SELECT id,name,institution FROM users WHERE role='reviewer' AND id NOT IN(SELECT reviewer_id FROM reviews WHERE reg_id=? AND round=?) ORDER BY name"); $avail->execute([$x['id'], $cur]); $avail = $avail->fetchAll();
    $now = reviewsOf($x['id'], $cur); ?>
<h3>Peer Review &middot; Ronde <?= $cur ?></h3>
<?php if (!$now): ?><p>Belum ada reviewer pada ronde ini.</p><?php else: ?><p><?php foreach ($now as $r): ?><span class="chip"><?= e($r['name']) ?> <?= $r['submitted_at'] ? '&#10004;' : '&#9203;' ?>
<form method="post" class="inline" data-confirm="Hapus penugasan reviewer ini pada ronde <?= $cur ?> (beserta reviewnya)?"><?= csrf() ?><input type="hidden" name="a" value="unassign"><input type="hidden" name="id" value="<?= $x['id'] ?>"><input type="hidden" name="reviewer" value="<?= $r['reviewer_id'] ?>"><button class="link" title="Hapus penugasan">&times;</button></form></span> <?php endforeach; ?></p><?php endif; ?>
<?php if ($avail): ?><form method="post" class="filters"><?= csrf() ?><input type="hidden" name="a" value="assign"><input type="hidden" name="id" value="<?= $x['id'] ?>"><select name="reviewer" required><option value="">-- pilih reviewer --</option><?php foreach ($avail as $r): ?><option value="<?= $r['id'] ?>"><?= e($r['name'] . ($r['institution'] ? ' (' . $r['institution'] . ')' : '')) ?></option><?php endforeach; ?></select><label class="nowrap" style="margin:0">Tenggat <input type="date" name="due" value="<?= date('Y-m-d', strtotime('+' . max(1, (int)setting('review_days', '14')) . ' days')) ?>" style="width:auto;display:inline-block"></label><button class="btn">Tugaskan Reviewer</button></form>
<?php else: ?><p><small>Tambahkan akun reviewer di tab <a href="?p=pengguna">Akun</a>.</small></p><?php endif; ?>
<h3>Riwayat Naskah &amp; Review</h3>
<?php paperHistory($x, 'admin'); ?>
<?php endif; ?>

<h3>Verifikasi &amp; Keputusan</h3>
<form method="post" class="card <?= $x['article_title'] ? 'cols4' : 'cols3' ?>"><?= csrf() ?><input type="hidden" name="a" value="regsave"><input type="hidden" name="id" value="<?= $x['id'] ?>">
<label>Status pembayaran<select name="pay_status"><?php foreach ($PAY as $s => $l): ?><option value="<?= $s ?>" <?= $x['pay_status'] === $s ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
<?php $xc = regCurrency($x); ?><label>Mata uang dibayar<select name="pay_currency"><?php foreach (['IDR', 'USD'] as $c): ?><option <?= $xc === $c ? 'selected' : '' ?>><?= $c ?></option><?php endforeach; ?></select></label>
<label>Jumlah diterima <small>Tagihan: <?= e($x['pay_amount']) ?> &middot; isi bila status Verified</small><input name="pay_received" inputmode="decimal" value="<?= $x['pay_status'] === 'verified' && $x['pay_received'] !== null ? e($x['pay_received'] + 0) : e(regExpected($x)) ?>"></label>
<?php if ($x['article_title']): ?><label>Status naskah<select name="paper_status"><?php foreach ($PAPER as $s => $l): ?><option value="<?= $s ?>" <?= $x['paper_status'] === $s ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></label><?php else: ?><input type="hidden" name="paper_status" value="<?= e($x['paper_status']) ?>"><?php endif; ?>
<label class="full">Catatan untuk peserta (tampil di dashboard &amp; email)<textarea name="admin_note" rows="3"><?= e($x['admin_note']) ?></textarea></label>
<label class="chk full"><input type="checkbox" name="notify" value="1" checked> Kirim email notifikasi ke peserta bila status berubah</label>
<button class="btn">Simpan</button></form>
<?php $nf = count(paperFiles($x['id'])); $nrv = count(reviewsOf($x['id'])); ?>
<h3 id="hapus">Hapus Peserta</h3>
<div class="card danger-zone"><p>Gunakan untuk akun <b>uji coba</b>, pendaftaran ganda, atau atas permintaan peserta. Yang ikut terhapus permanen:</p>
<ul><li>Akun login <b><?= e($x['email']) ?></b> dan notifikasinya</li><li>Pendaftaran <?= no('R', $x['id']) ?> &middot; pembayaran <b><?= $x['pay_status'] === 'verified' ? e(money($x['pay_received'], $x['pay_currency'] ?: 'IDR')) . ' (terverifikasi)' : e(lbl($PAY, $x['pay_status'])) ?></b><?= $x['pay_file'] ? ' &middot; file bukti bayar' : '' ?></li>
<?php if ($x['article_title']): ?><li>Naskah: <?= $nf ?> berkas versi<?= $nrv ? ', ' . $nrv . ' review' : '' ?></li><?php endif; ?></ul>
<p><small>Setelah dihapus, peserta tidak lagi dihitung di Ringkasan, Keuangan, maupun ekspor CSV. Kode verifikasi sertifikat/QR miliknya tidak berlaku lagi.</small></p>
<form method="post" data-confirm="Hapus permanen <?= no('R', $x['id']) ?> &ndash; <?= e($x['full_name']) ?> beserta akun, pembayaran, bukti bayar, dan naskahnya? Tindakan ini tidak bisa dibatalkan."><?= csrf() ?><input type="hidden" name="a" value="del"><input type="hidden" name="id" value="<?= $x['user_id'] ?>"><input type="hidden" name="back" value="peserta"><button class="btn danger">Hapus Peserta Ini</button></form></div>

