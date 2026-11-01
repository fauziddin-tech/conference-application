<?php require 'inc.php';
$u = user(); $em = setting('email');
$map = isurl(setting('map_url')) ? setting('map_url') : 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3989.6635694481533!2d101.45034407509675!3d0.5046369637092568!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x31d5aebce81ef37f%3A0x9b837fcba8f106e9!2sHotel%20Pangeran!5e0!3m2!1sen!2sid!4v1716732941113!5m2!1sen!2sid';
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
head(t('Home'), $ld); ?>
<section class="hero"><?php if (setting('organizer')): ?><p class="kicker"><?= e(tx(setting('organizer'))) ?></p><?php endif; ?>
<h1><?= e(setting('name')) ?></h1><p class="theme"><?= e(setting('tagline')) ?></p>
<p><b><?= e(tx(setting('date'))) ?></b> &middot; <?= e(tx(setting('time'))) ?> &middot; <?= e(setting('location')) ?></p>
<?php if ($startIso && strtotime($start) > time()): ?><div class="countdown" data-countdown="<?= e($startIso) ?>" aria-label="<?= te('Event countdown') ?>"><span><b>-</b><?= te('days') ?></span><span><b>-</b><?= te('hours') ?></span><span><b>-</b><?= te('min') ?></span><span><b>-</b><?= te('sec') ?></span></div><?php endif; ?>
<div class="actions"><a class="btn" href="<?= $reg ?>"><?= $u ? te('Open Dashboard') : te('Register Now') ?></a><a class="btn ghost" href="#cfp"><?= te('Call for Papers') ?></a><?php if ($start): ?><a class="btn ghost" href="calendar.php">+ <?= te('Add to Calendar') ?></a><?php endif; ?></div></section>

<?php if ($poster = setting('poster')): $pu = imgSrc($poster); ?>
<section id="poster" class="s fade poster"><a href="<?= e($pu) ?>" class="pimg" data-poster aria-label="<?= te('View full size') ?>"><img src="<?= e($pu) ?>" alt="<?= e(t('Event Poster') . ' — ' . setting('name')) ?>"<?= imgDims($poster) ?> decoding="async" fetchpriority="high"></a>
<p class="center pbtn"><a class="btn ghost2" href="<?= e($pu) ?>" data-poster><?= te('View full size') ?></a> <?php $po = setting('poster_orig') ?: $poster; ?><a class="btn ghost2" href="<?= e(imgSrc($po)) ?>" download="poster-<?= e(strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', brand()))) ?>.<?= e(pathinfo($po, PATHINFO_EXTENSION)) ?>">&#8681; <?= te('Download Poster') ?></a></p></section>
<div id="posm" class="modal posm" hidden><div class="pbox" role="dialog" aria-modal="true" aria-label="<?= te('Event Poster') ?>"><button type="button" class="mx" aria-label="<?= te('Close') ?>">&times;</button><img src="<?= e($pu) ?>" alt=""<?= imgDims($poster) ?>><?php if (regOpen() && !$u): ?><p class="center"><a class="btn" href="register.php"><?= te('Register Now') ?></a></p><?php endif; ?></div></div>
<script>(function(){var m=document.getElementById('posm');function op(e){if(e)e.preventDefault();m.hidden=false;document.body.style.overflow='hidden'}function cl(){m.hidden=true;document.body.style.overflow=''}
document.querySelectorAll('[data-poster]').forEach(function(a){a.addEventListener('click',op)});m.querySelector('.mx').onclick=cl;m.addEventListener('click',function(e){if(e.target===m)cl()});document.addEventListener('keydown',function(e){if(e.key==='Escape'&&!m.hidden)cl()});
m.querySelectorAll('a[href$="register.php"]').forEach(function(a){a.addEventListener('click',cl)});
<?php if (setting('poster_popup') === '1'): ?>try{var k='posterSeen',v=localStorage.getItem(k);if(!v||Date.now()-(+v)>86400000){localStorage.setItem(k,Date.now());setTimeout(op,600)}}catch(x){}<?php endif; ?>})();</script>
<?php endif; ?>
<?php if ($news): ?><section class="s fade news"><h2><?= te('Announcements') ?></h2><div class="grid ngrid"><?php foreach (array_slice($news, 0, 3) as $n): ?>
<article class="card"><small><?= e(fdateL($n['created_at'])) ?></small><h3><?= e(tx($n['title'])) ?></h3><p><?= nl2br(e(tx($n['body']))) ?></p><?php if (isurl($n['url'])): ?><p><a href="<?= e($n['url']) ?>" target="_blank" rel="noopener"><?= te('Read more') ?> &rarr;</a></p><?php endif; ?></article><?php endforeach; ?></div></section><?php endif; ?>

<section id="about" class="s fade"><h2><?= te('About the Conference') ?></h2><div class="two"><div><p><?= nl2br(e(tx(setting('about')))) ?></p></div>
<aside class="panel"><h3><?= te('When') ?></h3><p><?= e(tx(setting('date'))) ?><br><?= e(tx(setting('time'))) ?></p><h3><?= te('Where') ?></h3><p><?= e(setting('location')) ?></p><h3><?= te('Format') ?></h3><p><?= te('Hybrid (offline & online)') ?></p>
<?php if ($b = rows('benefits')): ?><h3><?= te('Benefits') ?></h3><ul><?php foreach ($b as $x): ?><li><?= e(tx($x[0])) ?></li><?php endforeach; ?></ul><?php endif; ?>
<?php if ($sp = rows('supporters')): ?><h3><?= te('Supported by') ?></h3><ul><?php foreach ($sp as $x): ?><li><?= e(tx($x[0])) ?></li><?php endforeach; ?></ul><?php endif; ?></aside></div></section>

<?php if ($dates = items('date')): ?><section id="dates" class="s fade"><h2><?= te('Important Dates') ?></h2><ol class="timeline"><?php foreach ($dates as $d): $past = $d['extra'] && $d['extra'] < date('Y-m-d'); ?>
<li class="<?= $past ? 'past' : '' ?>"><time datetime="<?= e($d['extra']) ?>"><?= e(fdateL($d['extra'])) ?></time><b><?= e(tx($d['title'])) ?></b><?php if ($d['body']): ?><small><?= e(tx($d['body'])) ?></small><?php endif; ?><?php if ($past): ?><span class="tag"><?= te('Closed') ?></span><?php endif; ?></li><?php endforeach; ?></ol></section><?php endif; ?>

<section id="speakers" class="s fade"><h2><?= te('Speakers') ?></h2>
<?php $G = []; foreach (rows('speakers') as $s) { $G[strtolower(trim($s[0]))][] = $s; }
$pri = function ($r) { return stripos($r, 'open speech') !== false ? 0 : (stripos($r, 'keynote') !== false ? 1 : 2); };
$keys = array_keys($G); $ord = array_flip($keys);
usort($keys, function ($a, $b) use ($pri, $ord) { $d = $pri($a) <=> $pri($b); return $d ?: ($ord[$a] <=> $ord[$b]); });
foreach ($keys as $k): $pr = $pri($k); $sz = [2, 1.5, 1][$pr]; $cl = ['k2', 'k15', 'k1'][$pr]; ?>
<div class="spgroup <?= $cl ?>" style="--k:<?= $sz ?>"><?php foreach ($G[$k] as $s): ?><div class="card sp"><?php if (!empty($s[3])): ?><img src="uploads/<?= e($s[3]) ?>" alt="<?= e($s[1] ?? '') ?>" loading="lazy" decoding="async"<?= imgDims($s[3]) ?>><?php else: ?><div class="ph" aria-hidden="true"><?= e(initials($s[1] ?? '')) ?></div><?php endif; ?><small><?= e(tx($s[0])) ?></small><b><?= e($s[1] ?? '') ?></b><span><?= e($s[2] ?? '') ?></span></div><?php endforeach; ?></div>
<?php endforeach; ?></section>

<?php $topics = items('topic'); $dl = items('download'); if ($topics || setting('cfp_info') || $dl): ?>
<section id="cfp" class="s fade"><h2><?= te('Call for Papers') ?></h2><?php if (setting('cfp_info')): ?><p><?= nl2br(e(tx(setting('cfp_info')))) ?></p><?php endif; ?>
<?php if ($topics): ?><h3 class="center"><?= te('Conference Topics / Tracks') ?></h3><div class="topics"><?php foreach ($topics as $t): ?><div class="topic"><b><?= e(tx($t['title'])) ?></b><?php if ($t['body']): ?><small><?= e(tx($t['body'])) ?></small><?php endif; ?></div><?php endforeach; ?></div><?php endif; ?>
<?php if ($dl): ?><h3 class="center"><?= te('Downloads & Guidelines') ?></h3><p class="center dl"><?php foreach ($dl as $d): ?><a class="btn ghost2" href="<?= e(isurl($d['url']) ? $d['url'] : imgSrc($d['url'])) ?>" target="_blank" rel="noopener">&#8681; <?= e(tx($d['title'])) ?></a> <?php endforeach; ?></p><?php endif; ?>
<p class="center"><a class="btn big" href="<?= $reg ?>"><?= te('Submit Abstract / Paper') ?></a></p></section><?php endif; ?>

<section id="program" class="s fade"><h2><?= te('Program') ?></h2><?php agenda(); ?></section>

<section id="register" class="s fade"><h2><?= te('Registration & Fees') ?></h2>
<?php $early = hasEarly(); $eon = earlyOn(); ?>
<?php if ($early && setting('early_deadline')): ?><p class="center"><?= $eon ? '<span class="tag on">' . te('Early bird until %s', fdateL(setting('early_deadline'))) . '</span>' : '<span class="tag">' . te('Early bird period ended (%s)', fdateL(setting('early_deadline'))) . '</span>' ?></p><?php endif; ?>
<div class="scroll"><table><tr><th><?= te('Category') ?></th><?php if ($early): ?><th>Early Bird (IDR)</th><th>Early Bird (USD)</th><th>Regular (IDR)</th><th>Regular (USD)</th><?php else: ?><th>IDR</th><th>USD</th><?php endif; ?></tr>
<?php foreach (fees() as $f): ?><tr><td><?= e(tx($f['cat'])) ?></td><?php if ($early): ?><td class="<?= $eon ? 'hl' : 'muted' ?>"><?= e($f['eidr'] ?: '-') ?></td><td class="<?= $eon ? 'hl' : 'muted' ?>"><?= e($f['eusd'] ?: '-') ?></td><?php endif; ?><td><?= e($f['idr']) ?></td><td><?= e($f['usd']) ?></td></tr><?php endforeach; ?></table></div>
<?php if (regOpen()): ?><ol class="steps"><li><b><?= te('Create an account') ?></b><small><?= te('Name, email, password') ?></small></li><li><b><?= te('Fill in the form') ?></b><small><?= te('Participant data & paper (presenters)') ?></small></li><li><b><?= te('Pay & upload proof') ?></b><small><?= te('Invoice available in the dashboard') ?></small></li><li><b><?= te('Committee verification') ?></b><small><?= te('LoA, badge & certificate in the dashboard') ?></small></li></ol>
<p class="center"><a class="btn big" href="<?= $reg ?>"><?= $u ? te('Open Dashboard') : te('Register Now') ?></a><?php if (isurl(setting('submission_url'))): ?> <a class="btn ghost2 big" href="<?= e(setting('submission_url')) ?>" target="_blank" rel="noopener"><?= te('Make Submission') ?></a><?php endif; ?></p>
<?php else: ?><p class="msg center"><?= e(tx(setting('reg_close_msg'))) ?></p><?php endif; ?></section>

<?php if ($cm = items('committee')): $CG = []; foreach ($cm as $c) $CG[$c['extra'] ? tx($c['extra']) : t('Committee')][] = $c; ?>
<section id="committee" class="s fade"><h2><?= te('Committee') ?></h2><div class="cgrid"><?php foreach ($CG as $g => $list): ?><div class="card"><h3><?= e($g) ?></h3><ul class="plain"><?php foreach ($list as $c): ?><li><b><?= e($c['title']) ?></b><?php if ($c['subtitle']): ?> &ndash; <?= e(tx($c['subtitle'])) ?><?php endif; ?><?php if ($c['body']): ?><br><small><?= e($c['body']) ?></small><?php endif; ?></li><?php endforeach; ?></ul></div><?php endforeach; ?></div></section><?php endif; ?>

<section id="venue" class="s fade"><h2><?= te('Venue') ?></h2><div class="venue"><div class="map mapf" data-src="<?= e($map) ?>"><button type="button" class="btn"><?= te('Show map') ?></button><small><?= e(setting('location')) ?></small></div>
<script>document.querySelectorAll('.mapf').forEach(function(m){m.querySelector('button').addEventListener('click',function(){m.innerHTML='<iframe src="'+m.dataset.src+'" allowfullscreen referrerpolicy="no-referrer-when-downgrade" title="'+<?= json_encode(t('Venue map')) ?>+'"></iframe>';m.classList.remove('mapf')})})</script>
<div class="panel"><h3><?= e(setting('location')) ?></h3><?php if (setting('venue_info')): ?><p><?= nl2br(e(tx(setting('venue_info')))) ?></p><?php endif; ?><?php if (isurl(setting('venue_url'))): ?><p><a href="<?= e(setting('venue_url')) ?>" target="_blank" rel="noopener"><?= te('Venue information') ?> &rarr;</a></p><?php endif; ?></div></div></section>

<?php if ($j = rows('journals')): ?><section id="publication" class="s fade"><h2><?= te('Publication Opportunities') ?></h2><p><?= te('Partner journals for publishing conference papers. Read each journal\'s Focus & Scope and author guidelines before submitting.') ?></p><div class="grid jgrid"><?php foreach ($j as $x): $x = array_pad($x, 9, ''); ?>
<div class="card jr"><div class="jrow"><?php if (isurl($x[7])): ?><img class="jcover" src="<?= e($x[7]) ?>" alt="Cover <?= e($x[0]) ?>" loading="lazy" referrerpolicy="no-referrer" onerror="this.style.display='none'"><?php endif; ?>
<div class="jinfo"><h3><?= isurl($x[1]) ? '<a href="' . e($x[1]) . '" target="_blank" rel="noopener">' . e($x[0]) . '</a>' : e($x[0]) ?></h3>
<?php if ($x[2]): ?><span class="badge"><?= e($x[2]) ?></span><?php endif; ?>
<?php if ($x[3]): ?><p><small><?= te('Publisher') ?>:</small> <?= e($x[3]) ?></p><?php endif; ?>
<?php if ($x[4]): ?><p><small>ISSN:</small> <?= e($x[4]) ?></p><?php endif; ?>
<?php if ($x[5] && !isurl($x[5])): ?><p class="jscope"><?= nl2br(e($x[5])) ?></p><?php endif; ?>
<?php if ($x[8]): ?><p><small><?= te('Note') ?>:</small> <?= e($x[8]) ?></p><?php endif; ?>
<p class="jbtn"><?php if (isurl($x[1])): ?><a class="btn" href="<?= e($x[1]) ?>" target="_blank" rel="noopener"><?= te('Visit Journal') ?></a><?php endif; ?><?php if (isurl($x[5])): ?><a class="btn ghost2" href="<?= e($x[5]) ?>" target="_blank" rel="noopener">Focus &amp; Scope</a><?php endif; ?><?php if (isurl($x[6])): ?><a class="btn ghost2" href="<?= e($x[6]) ?>" target="_blank" rel="noopener"><?= te('Author Guidelines') ?></a><?php endif; ?></p></div></div></div><?php endforeach; ?></div></section><?php endif; ?>

<?php if ($spons = items('sponsor')): $SG = []; foreach ($spons as $s) $SG[$s['extra'] ? tx($s['extra']) : t('Partners')][] = $s; ?>
<section id="sponsors" class="s fade"><h2><?= te('Sponsors & Partners') ?></h2><?php foreach ($SG as $g => $list): ?><h3 class="center"><?= e($g) ?></h3><div class="logos"><?php foreach ($list as $s): $img = $s['image'] ? '<img src="' . e(imgSrc($s['image'])) . '" alt="' . e($s['title']) . '" loading="lazy">' : '<span>' . e($s['title']) . '</span>'; ?>
<?= isurl($s['url']) ? '<a href="' . e($s['url']) . '" target="_blank" rel="noopener" title="' . e($s['title']) . '">' . $img . '</a>' : '<div title="' . e($s['title']) . '">' . $img . '</div>' ?><?php endforeach; ?></div><?php endforeach; ?></section><?php endif; ?>

<?php if ($faq = items('faq')): ?><section id="faq" class="s fade"><h2>FAQ</h2><div class="faq"><?php foreach ($faq as $q): ?><details><summary><?= e(tx($q['title'])) ?></summary><p><?= nl2br(e(tx($q['body']))) ?></p></details><?php endforeach; ?></div></section><?php endif; ?>

<section id="contact" class="s fade"><h2><?= te('Contact') ?></h2><div class="cg">
<div class="card ci"><h3><?= te('Address') ?></h3><p><?= e(tx(setting('secretariat'))) ?></p></div>
<?php if ($cs = rows('contacts')): ?><div class="card ci"><h3>WhatsApp</h3><?php foreach ($cs as $c): ?><p><?= e($c[0]) ?><br><a href="https://wa.me/<?= preg_replace('/\D/', '', $c[1] ?? '') ?>" target="_blank" rel="noopener"><?= e($c[1] ?? '') ?></a></p><?php endforeach; ?></div><?php endif; ?>
<div class="card ci"><h3>Email &amp; Web</h3><?php if ($em): ?><p><a href="mailto:<?= e($em) ?>"><?= e($em) ?></a></p><?php endif; ?><?php if (setting('website')): ?><p><?= e(setting('website')) ?></p><?php endif; ?></div></div></section>
<?php foot();
