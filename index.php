<?php require 'inc.php';
$u = user(); $em = setting('email');
$map = setting('map_url') ?: 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3989.6635694481533!2d101.45034407509675!3d0.5046369637092568!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x31d5aebce81ef37f%3A0x9b837fcba8f106e9!2sHotel%20Pangeran!5e0!3m2!1sen!2sid!4v1716732941113!5m2!1sen!2sid';
$reg = $u ? homeFor($u) : 'register.php';
if ($u && $u['role'] === 'peserta') $reg = regOf($u['id']) ? 'dashboard.php' : 'registrasi.php';
$start = setting('event_start');
$startIso = $start ? date('c', strtotime($start)) : '';
$news = array_values(array_filter(items('news'), function ($n) { return $n['extra'] !== 'participants'; }));

// Data terstruktur (schema.org) agar acara mudah ditemukan mesin pencari
$ld = '';
if ($startIso) {
    $ev = ['@context' => 'https://schema.org', '@type' => 'Event', 'name' => setting('name'), 'description' => setting('tagline'), 'startDate' => $startIso,
        'eventAttendanceMode' => 'https://schema.org/MixedEventAttendanceMode', 'eventStatus' => 'https://schema.org/EventScheduled',
        'location' => [['@type' => 'Place', 'name' => setting('location'), 'address' => setting('location')], ['@type' => 'VirtualLocation', 'url' => absUrl()]],
        'organizer' => ['@type' => 'Organization', 'name' => setting('organizer') ?: setting('name'), 'url' => absUrl()], 'url' => absUrl()];
    if (setting('event_end')) $ev['endDate'] = date('c', strtotime(setting('event_end')));
    $ld = '<script type="application/ld+json">' . json_encode($ev, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . '</script>';
}
head('Beranda', $ld); ?>
<section class="hero"><?php if (setting('organizer')): ?><p class="kicker"><?= e(setting('organizer')) ?></p><?php endif; ?>
<h1><?= e(setting('name')) ?></h1><p class="theme"><?= e(setting('tagline')) ?></p>
<p><b><?= e(setting('date')) ?></b> &middot; <?= e(setting('time')) ?> &middot; <?= e(setting('location')) ?></p>
<?php if ($startIso && strtotime($start) > time()): ?><div class="countdown" data-countdown="<?= e($startIso) ?>" aria-label="Hitung mundur acara"><span><b>-</b>days</span><span><b>-</b>hours</span><span><b>-</b>min</span><span><b>-</b>sec</span></div><?php endif; ?>
<div class="actions"><a class="btn" href="<?= $reg ?>"><?= $u ? 'Buka Dashboard' : 'Register Now' ?></a><a class="btn ghost" href="#cfp">Call for Papers</a><?php if ($start): ?><a class="btn ghost" href="calendar.php">+ Add to Calendar</a><?php endif; ?></div></section>

<?php if ($news): ?><section class="s fade news"><h2>Announcements</h2><div class="grid ngrid"><?php foreach (array_slice($news, 0, 3) as $n): ?>
<article class="card"><small><?= e(fdate($n['created_at'])) ?></small><h3><?= e($n['title']) ?></h3><p><?= nl2br(e($n['body'])) ?></p><?php if (isurl($n['url'])): ?><p><a href="<?= e($n['url']) ?>" target="_blank" rel="noopener">Selengkapnya &rarr;</a></p><?php endif; ?></article><?php endforeach; ?></div></section><?php endif; ?>

<section id="about" class="s fade"><h2>Tentang Konferensi</h2><div class="two"><div><p><?= nl2br(e(setting('about'))) ?></p></div>
<aside class="panel"><h3>When</h3><p><?= e(setting('date')) ?><br><?= e(setting('time')) ?></p><h3>Where</h3><p><?= e(setting('location')) ?></p><h3>Format</h3><p>Hybrid (offline &amp; online)</p>
<?php if ($b = rows('benefits')): ?><h3>Benefit</h3><ul><?php foreach ($b as $x): ?><li><?= e($x[0]) ?></li><?php endforeach; ?></ul><?php endif; ?>
<?php if ($sp = rows('supporters')): ?><h3>Dukungan Institusi</h3><ul><?php foreach ($sp as $x): ?><li><?= e($x[0]) ?></li><?php endforeach; ?></ul><?php endif; ?></aside></div></section>

<?php if ($dates = items('date')): ?><section id="dates" class="s fade"><h2>Important Dates</h2><ol class="timeline"><?php foreach ($dates as $d): $past = $d['extra'] && $d['extra'] < date('Y-m-d'); ?>
<li class="<?= $past ? 'past' : '' ?>"><time datetime="<?= e($d['extra']) ?>"><?= e(fdateEn($d['extra'])) ?></time><b><?= e($d['title']) ?></b><?php if ($d['body']): ?><small><?= e($d['body']) ?></small><?php endif; ?><?php if ($past): ?><span class="tag">Closed</span><?php endif; ?></li><?php endforeach; ?></ol></section><?php endif; ?>

<section id="speakers" class="s fade"><h2>Pembicara</h2>
<?php $G = []; foreach (rows('speakers') as $s) { $G[strtolower(trim($s[0]))][] = $s; }
$pri = function ($r) { return stripos($r, 'open speech') !== false ? 0 : (stripos($r, 'keynote') !== false ? 1 : 2); };
$keys = array_keys($G); $ord = array_flip($keys);
usort($keys, function ($a, $b) use ($pri, $ord) { $d = $pri($a) <=> $pri($b); return $d ?: ($ord[$a] <=> $ord[$b]); });
foreach ($keys as $k): $pr = $pri($k); $sz = [2, 1.5, 1][$pr]; $cl = ['k2', 'k15', 'k1'][$pr]; ?>
<div class="spgroup <?= $cl ?>" style="--k:<?= $sz ?>"><?php foreach ($G[$k] as $s): ?><div class="card sp"><?php if (!empty($s[3])): ?><img src="uploads/<?= e($s[3]) ?>" alt="<?= e($s[1] ?? '') ?>" loading="lazy"><?php else: ?><div class="ph" aria-hidden="true"><?= e(initials($s[1] ?? '')) ?></div><?php endif; ?><small><?= e($s[0]) ?></small><b><?= e($s[1] ?? '') ?></b><span><?= e($s[2] ?? '') ?></span></div><?php endforeach; ?></div>
<?php endforeach; ?></section>

<?php $topics = items('topic'); $dl = items('download'); if ($topics || setting('cfp_info') || $dl): ?>
<section id="cfp" class="s fade"><h2>Call for Papers</h2><?php if (setting('cfp_info')): ?><p><?= nl2br(e(setting('cfp_info'))) ?></p><?php endif; ?>
<?php if ($topics): ?><h3 class="center">Conference Topics / Tracks</h3><div class="topics"><?php foreach ($topics as $t): ?><div class="topic"><b><?= e($t['title']) ?></b><?php if ($t['body']): ?><small><?= e($t['body']) ?></small><?php endif; ?></div><?php endforeach; ?></div><?php endif; ?>
<?php if ($dl): ?><h3 class="center">Downloads &amp; Guidelines</h3><p class="center dl"><?php foreach ($dl as $d): ?><a class="btn ghost2" href="<?= e(isurl($d['url']) ? $d['url'] : imgSrc($d['url'])) ?>" target="_blank" rel="noopener">&#8681; <?= e($d['title']) ?></a> <?php endforeach; ?></p><?php endif; ?>
<p class="center"><a class="btn big" href="<?= $reg ?>">Submit Abstract / Paper</a></p></section><?php endif; ?>

<section id="program" class="s fade"><h2>Program</h2><?php agenda(); ?></section>

<section id="register" class="s fade"><h2>Registrasi &amp; Biaya</h2>
<?php $early = hasEarly(); $eon = earlyOn(); ?>
<?php if ($early && setting('early_deadline')): ?><p class="center"><?= $eon ? '<span class="tag on">Early bird berlaku hingga ' . e(fdate(setting('early_deadline'))) . '</span>' : '<span class="tag">Periode early bird telah berakhir (' . e(fdate(setting('early_deadline'))) . ')</span>' ?></p><?php endif; ?>
<div class="scroll"><table><tr><th>Kategori</th><?php if ($early): ?><th>Early Bird (IDR)</th><th>Early Bird (USD)</th><th>Regular (IDR)</th><th>Regular (USD)</th><?php else: ?><th>IDR</th><th>USD</th><?php endif; ?></tr>
<?php foreach (fees() as $f): ?><tr><td><?= e($f['cat']) ?></td><?php if ($early): ?><td class="<?= $eon ? 'hl' : 'muted' ?>"><?= e($f['eidr'] ?: '-') ?></td><td class="<?= $eon ? 'hl' : 'muted' ?>"><?= e($f['eusd'] ?: '-') ?></td><?php endif; ?><td><?= e($f['idr']) ?></td><td><?= e($f['usd']) ?></td></tr><?php endforeach; ?></table></div>
<?php if (regOpen()): ?><ol class="steps"><li><b>Buat akun</b><small>Nama, email, password</small></li><li><b>Isi formulir</b><small>Data peserta &amp; naskah (presenter)</small></li><li><b>Bayar &amp; unggah bukti</b><small>Invoice tersedia di dashboard</small></li><li><b>Verifikasi panitia</b><small>LoA, badge &amp; sertifikat di dashboard</small></li></ol>
<p class="center"><a class="btn big" href="<?= $reg ?>"><?= $u ? 'Buka Dashboard' : 'Daftar Sekarang' ?></a><?php if (setting('submission_url')): ?> <a class="btn ghost2 big" href="<?= e(setting('submission_url')) ?>" target="_blank" rel="noopener">Make Submission</a><?php endif; ?></p>
<?php else: ?><p class="msg center"><?= e(setting('reg_close_msg')) ?></p><?php endif; ?></section>

<?php if ($cm = items('committee')): $CG = []; foreach ($cm as $c) $CG[$c['extra'] ?: 'Committee'][] = $c; ?>
<section id="committee" class="s fade"><h2>Committee</h2><div class="cgrid"><?php foreach ($CG as $g => $list): ?><div class="card"><h3><?= e($g) ?></h3><ul class="plain"><?php foreach ($list as $c): ?><li><b><?= e($c['title']) ?></b><?php if ($c['subtitle']): ?> &ndash; <?= e($c['subtitle']) ?><?php endif; ?><?php if ($c['body']): ?><br><small><?= e($c['body']) ?></small><?php endif; ?></li><?php endforeach; ?></ul></div><?php endforeach; ?></div></section><?php endif; ?>

<section id="venue" class="s fade"><h2>Lokasi Acara</h2><div class="venue"><div class="map"><iframe src="<?= e($map) ?>" loading="lazy" allowfullscreen referrerpolicy="no-referrer-when-downgrade" title="Peta lokasi"></iframe></div>
<div class="panel"><h3><?= e(setting('location')) ?></h3><?php if (setting('venue_info')): ?><p><?= nl2br(e(setting('venue_info'))) ?></p><?php endif; ?><?php if (isurl(setting('venue_url'))): ?><p><a href="<?= e(setting('venue_url')) ?>" target="_blank" rel="noopener">Info lokasi &rarr;</a></p><?php endif; ?></div></div></section>

<?php if ($j = rows('journals')): ?><section id="publication" class="s fade"><h2>Peluang Publikasi</h2><p>Jurnal mitra untuk publikasi artikel konferensi. Baca Focus &amp; Scope dan panduan penulis masing-masing jurnal sebelum mengirim naskah.</p><div class="grid jgrid"><?php foreach ($j as $x): $x = array_pad($x, 9, ''); ?>
<div class="card jr"><div class="jrow"><?php if (isurl($x[7])): ?><img class="jcover" src="<?= e($x[7]) ?>" alt="Cover <?= e($x[0]) ?>" loading="lazy" referrerpolicy="no-referrer" onerror="this.style.display='none'"><?php endif; ?>
<div class="jinfo"><h3><?= isurl($x[1]) ? '<a href="' . e($x[1]) . '" target="_blank" rel="noopener">' . e($x[0]) . '</a>' : e($x[0]) ?></h3>
<?php if ($x[2]): ?><span class="badge"><?= e($x[2]) ?></span><?php endif; ?>
<?php if ($x[3]): ?><p><small>Penerbit:</small> <?= e($x[3]) ?></p><?php endif; ?>
<?php if ($x[4]): ?><p><small>ISSN:</small> <?= e($x[4]) ?></p><?php endif; ?>
<?php if ($x[5] && !isurl($x[5])): ?><p class="jscope"><?= nl2br(e($x[5])) ?></p><?php endif; ?>
<?php if ($x[8]): ?><p><small>Catatan:</small> <?= e($x[8]) ?></p><?php endif; ?>
<p class="jbtn"><?php if (isurl($x[1])): ?><a class="btn" href="<?= e($x[1]) ?>" target="_blank" rel="noopener">Kunjungi Jurnal</a><?php endif; ?><?php if (isurl($x[5])): ?><a class="btn ghost2" href="<?= e($x[5]) ?>" target="_blank" rel="noopener">Focus &amp; Scope</a><?php endif; ?><?php if (isurl($x[6])): ?><a class="btn ghost2" href="<?= e($x[6]) ?>" target="_blank" rel="noopener">Panduan Penulis</a><?php endif; ?></p></div></div></div><?php endforeach; ?></div></section><?php endif; ?>

<?php if ($spons = items('sponsor')): $SG = []; foreach ($spons as $s) $SG[$s['extra'] ?: 'Partners'][] = $s; ?>
<section id="sponsors" class="s fade"><h2>Sponsors &amp; Partners</h2><?php foreach ($SG as $g => $list): ?><h3 class="center"><?= e($g) ?></h3><div class="logos"><?php foreach ($list as $s): $img = $s['image'] ? '<img src="' . e(imgSrc($s['image'])) . '" alt="' . e($s['title']) . '" loading="lazy">' : '<span>' . e($s['title']) . '</span>'; ?>
<?= isurl($s['url']) ? '<a href="' . e($s['url']) . '" target="_blank" rel="noopener" title="' . e($s['title']) . '">' . $img . '</a>' : '<div title="' . e($s['title']) . '">' . $img . '</div>' ?><?php endforeach; ?></div><?php endforeach; ?></section><?php endif; ?>

<?php if ($faq = items('faq')): ?><section id="faq" class="s fade"><h2>FAQ</h2><div class="faq"><?php foreach ($faq as $q): ?><details><summary><?= e($q['title']) ?></summary><p><?= nl2br(e($q['body'])) ?></p></details><?php endforeach; ?></div></section><?php endif; ?>

<section id="contact" class="s fade"><h2>Kontak</h2><div class="cg">
<div class="card ci"><h3>Alamat</h3><p><?= e(setting('secretariat')) ?></p></div>
<?php if ($cs = rows('contacts')): ?><div class="card ci"><h3>WhatsApp</h3><?php foreach ($cs as $c): ?><p><?= e($c[0]) ?><br><a href="https://wa.me/<?= preg_replace('/\D/', '', $c[1] ?? '') ?>" target="_blank" rel="noopener"><?= e($c[1] ?? '') ?></a></p><?php endforeach; ?></div><?php endif; ?>
<div class="card ci"><h3>Email &amp; Web</h3><?php if ($em): ?><p><a href="mailto:<?= e($em) ?>"><?= e($em) ?></a></p><?php endif; ?><?php if (setting('website')): ?><p><?= e(setting('website')) ?></p><?php endif; ?></div></div></section>
<?php foot();
