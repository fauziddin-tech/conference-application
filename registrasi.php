<?php require 'inc.php'; $u = need('peserta');
$R = regOf($u['id']);
$fees = fees(); $topics = items('topic');
$locked = $R && $R['pay_status'] === 'verified';
$closed = !$R && !regOpen();
$amin = (int)setting('abs_min', '50'); $amax = (int)setting('abs_max', '250');
$err = [];
function t($k) { return post($k); }
function v($k, $d = '') { global $R; return $_POST[$k] ?? (($R[$k] ?? '') !== '' && $R[$k] !== null ? $R[$k] : $d); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$locked && !$closed) {
    if (!$_POST) $err[] = 'Data tidak terkirim. Ukuran total file mungkin melebihi batas server (post_max_size).';
    else {
        chk();
        $cat = t('category'); $att = t('attendance'); $pres = isPres($cat); $inst = t('institution');
        $f = ['prefix' => t('prefix'), 'full_name' => t('full_name'), 'country' => t('country'), 'orcid' => strtoupper(t('orcid')), 'phone' => t('phone'), 'category' => $cat, 'attendance' => $att,
            'track' => $pres ? t('track') : '', 'article_title' => $pres ? t('article_title') : '', 'coauthors' => $pres ? t('coauthors') : '', 'abstract' => $pres ? t('abstract') : '', 'keywords' => $pres ? t('keywords') : '',
            'pay_sender' => t('pay_sender'), 'pay_date' => t('pay_date') ?: null, 'dietary' => t('dietary'), 'agreed' => 1];
        $feeRow = null; foreach ($fees as $x) if ($x['cat'] === $cat) $feeRow = $x;
        if (!$feeRow) $err[] = 'Pilih kategori pendaftaran.';
        if (!in_array($att, ['Offline', 'Online'], true)) $err[] = 'Pilih jenis kehadiran (Offline/Online).';
        elseif ($feeRow && modeOf($cat) && modeOf($cat) !== $att) $err[] = 'Kategori "' . $cat . '" hanya untuk kehadiran ' . modeOf($cat) . '.';
        if ($f['full_name'] === '' || $inst === '' || $f['country'] === '' || $f['phone'] === '') $err[] = 'Nama lengkap, institusi, negara, dan nomor HP wajib diisi.';
        if ($f['full_name'] !== '' && !validName($f['full_name'])) $err[] = 'Nama lengkap hanya boleh berisi huruf, spasi, titik, koma, dan tanda hubung.';
        if ($f['orcid'] !== '' && !preg_match('/^\d{4}-\d{4}-\d{4}-\d{3}[\dX]$/', $f['orcid'])) $err[] = 'Format ORCID iD harus 0000-0000-0000-0000.';
        if ($pres) {
            $wc = words($f['abstract']); $kw = array_filter(array_map('trim', explode(',', $f['keywords'])));
            if ($f['article_title'] === '') $err[] = 'Article title wajib diisi.';
            if ($topics && $f['track'] === '') $err[] = 'Pilih topik / track naskah.';
            if ($wc < $amin || $wc > $amax) $err[] = "Abstract harus $amin-$amax kata (saat ini $wc kata).";
            if (count($kw) < 3 || count($kw) > 5) $err[] = 'Keywords harus 3-5 kata kunci, dipisah koma.';
        }
        $hasProof = !empty($_FILES['proof']['name']) || ($R && $R['pay_file']);
        if ($hasProof) {
            $d = DateTime::createFromFormat('Y-m-d', (string)$f['pay_date']);
            if (!$d || $d->format('Y-m-d') !== $f['pay_date'] || $d > new DateTime('today')) $err[] = 'Tanggal pembayaran tidak valid.';
            if ($f['pay_sender'] === '') $err[] = 'Nama pengirim pembayaran wajib diisi.';
        }
        $need = ['agree_info', 'agree_privacy']; if ($pres) $need = array_merge($need, ['agree_orig', 'agree_pub']);
        foreach ($need as $k) if (empty($_POST[$k])) { $err[] = 'Semua pernyataan persetujuan wajib dicentang.'; break; }
        $art = null; $pay = null; $e = '';
        if (!$err && $pres) {
            if (!empty($_FILES['article']['name'])) { $art = upFile($_FILES['article'], ['pdf', 'doc', 'docx'], $e, 'Article file'); if (!$art) $err[] = $e; }
            elseif (!($R && $R['article_file'])) $err[] = 'Article file wajib diunggah.';
        }
        if (!$err && !empty($_FILES['proof']['name'])) { $pay = upFile($_FILES['proof'], ['pdf', 'jpg', 'jpeg', 'png'], $e, 'Proof of payment'); if (!$pay) $err[] = $e; }
        if ($err && $art) { rmPriv($art[0]); $art = null; }
        if (!$err) {
            $row = $f; $row['pay_amount'] = feeText($feeRow);
            if ($art) { rmPriv($R['article_file'] ?? ''); $row['article_file'] = $art[0]; $row['article_name'] = $art[1]; }
            if (!$pres && $R && $R['article_file']) { rmPriv($R['article_file']); rmPriv($R['camera_file']); $row['article_file'] = ''; $row['article_name'] = ''; $row['camera_file'] = ''; $row['camera_name'] = ''; }
            if ($pay) { rmPriv($R['pay_file'] ?? ''); $row['pay_file'] = $pay[0]; $row['pay_name'] = $pay[1]; }
            if (!$hasProof) { $row['pay_sender'] = ''; $row['pay_date'] = null; }
            $row['pay_status'] = $pay ? 'pending' : ($hasProof ? ($R['pay_status'] === 'unpaid' || $R['pay_status'] === 'rejected' ? 'pending' : $R['pay_status']) : 'unpaid');
            if ($R) {
                if ($R['paper_status'] === 'revision' && $art) $row['paper_status'] = 'submitted';
                $sets = implode(',', array_map(function ($k) { return "$k=?"; }, array_keys($row)));
                $pdo->prepare("UPDATE registrations SET $sets WHERE id=?")->execute(array_merge(array_values($row), [$R['id']]));
                $rid = $R['id'];
            } else {
                $row['user_id'] = $u['id']; $row['ticket'] = newTicket();
                $cols = implode(',', array_keys($row)); $ph = implode(',', array_fill(0, count($row), '?'));
                $pdo->prepare("INSERT INTO registrations($cols) VALUES($ph)")->execute(array_values($row));
                $rid = $pdo->lastInsertId();
            }
            $pdo->prepare("UPDATE users SET category=?,institution=?,phone=? WHERE id=?")->execute([$cat, $inst, $f['phone'], $u['id']]);
            if (!$R || $pay) {
                $txt = (!$R ? "Your registration has been received.\n\nRegistration number: " . no('R', $rid) . "\nCategory: $cat ($att)\nFee: " . $row['pay_amount'] . ($pres ? "\nPaper: " . $f['article_title'] : '') . "\n\n" : '')
                    . ($row['pay_status'] === 'unpaid' ? "Please complete your payment and upload the proof of payment from your dashboard. Your invoice is available in the dashboard." : "Your proof of payment has been received and will be verified by the committee.");
                notify($u['email'], $f['full_name'], !$R ? 'Registration received ' . no('R', $rid) : 'Proof of payment received', $txt);
            }
            flash($row['pay_status'] === 'unpaid' ? 'Pendaftaran tersimpan. Silakan lakukan pembayaran lalu unggah bukti bayar (invoice tersedia di dashboard).' : 'Pendaftaran tersimpan. Panitia akan memverifikasi pembayaran Anda.');
            go('dashboard.php');
        }
    }
}
$cur = v('category', $u['category']);
head('Pendaftaran'); ?>
<h2>Conference Registration</h2>
<?php if ($err): ?><div class="msg err"><ul><?php foreach ($err as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if ($closed): ?><p class="msg"><?= e(setting('reg_close_msg')) ?></p>
<?php elseif ($locked): ?><p class="msg">Pembayaran Anda sudah terverifikasi, sehingga data pendaftaran dikunci. Revisi / camera-ready naskah dapat diunggah dari dashboard. Hubungi panitia jika perlu perubahan lain. <a href="dashboard.php">Kembali ke dashboard</a></p>
<?php else: ?>
<p>Lengkapi formulir berikut dengan data yang akurat. Tanda <b>*</b> wajib diisi. Nama lengkap akan dipakai pada e-certificate, LoA, dan badge.</p>
<form method="post" enctype="multipart/form-data" class="card"><?= csrf() ?>
<h3>1. Participant Information</h3>
<label>Title *<select name="prefix"><?php foreach (['Prof.', 'Dr.', 'Mr.', 'Mrs.', 'Ms.', 'Other'] as $p): ?><option <?= v('prefix') === $p ? 'selected' : '' ?>><?= e($p) ?></option><?php endforeach; ?></select></label>
<label>Full name (as on certificate) *<input name="full_name" required value="<?= e(v('full_name', $u['name'])) ?>"></label>
<label>Email<input value="<?= e($u['email']) ?>" disabled></label>
<label>Institution / Affiliation *<input name="institution" required value="<?= e($_POST['institution'] ?? $u['institution']) ?>"></label>
<label>Country *<input name="country" required list="countries" value="<?= e(v('country', 'Indonesia')) ?>"></label>
<datalist id="countries"><?php foreach (['Indonesia', 'Malaysia', 'Singapore', 'Thailand', 'Philippines', 'Vietnam', 'Brunei Darussalam', 'Timor-Leste', 'Australia', 'Japan', 'South Korea', 'China', 'India', 'Pakistan', 'Bangladesh', 'Saudi Arabia', 'Turkey', 'Egypt', 'Nigeria', 'South Africa', 'United Kingdom', 'Netherlands', 'Germany', 'United States', 'Canada'] as $c): ?><option><?= $c ?></option><?php endforeach; ?></datalist>
<label>Phone / WhatsApp *<input name="phone" type="tel" required value="<?= e(v('phone', $u['phone'])) ?>" placeholder="+62..."></label>
<label>ORCID iD (optional)<input name="orcid" placeholder="0000-0000-0000-0000" value="<?= e(v('orcid')) ?>"></label>
<label>Dietary / accessibility needs (optional)<input name="dietary" value="<?= e(v('dietary')) ?>" placeholder="e.g. vegetarian, wheelchair access"></label>
<h3>2. Registration Category &amp; Attendance</h3>
<label>Registration category *<select name="category" id="cat" required><option value="">-- select --</option><?php foreach ($fees as $f): ?><option value="<?= e($f['cat']) ?>" data-fee="<?= e(feeText($f)) ?>" <?= $cur === $f['cat'] ? 'selected' : '' ?>><?= e($f['cat'] . ' (' . feeNow($f)[0] . ')') ?></option><?php endforeach; ?></select></label>
<div><b>Attendance participation *</b><label class="chk"><input type="radio" name="attendance" value="Offline" required <?= v('attendance') === 'Offline' ? 'checked' : '' ?>> Offline (on-site)</label><label class="chk"><input type="radio" name="attendance" value="Online" <?= v('attendance') === 'Online' ? 'checked' : '' ?>> Online (virtual)</label></div>
<div id="paper" class="full sub"><h3 class="full">3. Paper Submission (presenters)</h3>
<?php if ($topics): ?><label class="full">Topic / track *<select name="track" data-need="1"><option value="">-- select --</option><?php foreach ($topics as $tp): ?><option <?= v('track') === $tp['title'] ? 'selected' : '' ?>><?= e($tp['title']) ?></option><?php endforeach; ?></select></label><?php endif; ?>
<label class="full">Article title *<input name="article_title" data-need="1" value="<?= e(v('article_title')) ?>"></label>
<label class="full">Co-authors (name &amp; affiliation, one per line; optional)<textarea name="coauthors" rows="3"><?= e(v('coauthors')) ?></textarea></label>
<label class="full">Abstract * <small>(<?= $amin ?>-<?= $amax ?> words, no references) <span id="wc"></span></small><textarea name="abstract" id="abs" rows="8" data-need="1"><?= e(v('abstract')) ?></textarea></label>
<label>Keywords * <small>(3-5, comma separated)</small><input name="keywords" data-need="1" value="<?= e(v('keywords')) ?>"></label>
<label>Article file * <small>(PDF/DOC/DOCX, max 10 MB<?= setting('blind_review', '1') === '1' ? '; remove author names for blind review' : '' ?>)</small><input type="file" name="article" accept=".pdf,.doc,.docx" <?= ($R && $R['article_file']) ? '' : 'data-need="1"' ?>><?php if ($R && $R['article_file']): ?><small>File saat ini: <a href="file.php?id=<?= $R['id'] ?>&k=article"><?= e($R['article_name']) ?></a> (kosongkan jika tidak diganti)</small><?php endif; ?></label>
<label class="chk full"><input type="checkbox" class="pres-only" name="agree_orig" <?= ($_POST ? !empty($_POST['agree_orig']) : ($R && $R['article_title'])) ? 'checked' : '' ?>> I declare that this paper is original, has not been published, and is not under review elsewhere.</label>
<label class="chk full"><input type="checkbox" class="pres-only" name="agree_pub" <?= ($_POST ? !empty($_POST['agree_pub']) : ($R && $R['article_title'])) ? 'checked' : '' ?>> I agree that the accepted paper may be published in the conference proceedings / partner journals.</label></div>
<h3>4. Payment</h3>
<?php if (setting('payment_info')): ?><div class="panel full"><b>Payment instructions</b><br><?= nl2br(e(setting('payment_info'))) ?></div><?php endif; ?>
<p class="full">Registration fee: <b id="fee">-</b><?= earlyOn() ? ' <span class="tag on">Early bird</span>' : '' ?><br><small>Bukti bayar boleh diunggah sekarang atau nanti dari dashboard (misalnya setelah naskah diterima). Invoice tersedia di dashboard.</small></p>
<label>Sender name<input name="pay_sender" value="<?= e(v('pay_sender')) ?>"></label>
<label>Payment date<input type="date" name="pay_date" max="<?= date('Y-m-d') ?>" value="<?= e(v('pay_date')) ?>"></label>
<label>Proof of payment <small>(PDF/JPG/PNG, max 10 MB)</small><input type="file" name="proof" accept=".pdf,.jpg,.jpeg,.png"><?php if ($R && $R['pay_file']): ?><small>File saat ini: <a href="file.php?id=<?= $R['id'] ?>&k=pay"><?= e($R['pay_name']) ?></a> (kosongkan jika tidak diganti)</small><?php endif; ?></label>
<h3>5. Declaration</h3>
<label class="chk full"><input type="checkbox" name="agree_info" required <?= ($_POST ? !empty($_POST['agree_info']) : $R) ? 'checked' : '' ?>> I confirm that all information provided is accurate.</label>
<label class="chk full"><input type="checkbox" name="agree_privacy" required <?= ($_POST ? !empty($_POST['agree_privacy']) : $R) ? 'checked' : '' ?>> I consent to the processing of my personal data for conference administration, certificates, and publication purposes.</label>
<button class="btn"><?= $R ? 'Update Registration' : 'Submit Registration' ?></button></form>
<script>(function(){var c=document.getElementById('cat'),p=document.getElementById('paper'),fee=document.getElementById('fee'),ab=document.getElementById('abs'),wc=document.getElementById('wc'),rd=document.querySelectorAll('input[name=attendance]'),f=document.querySelector('input[name=proof]'),MIN=<?= $amin ?>,MAX=<?= $amax ?>;
function upd(){var o=c.options[c.selectedIndex],n=o?o.value:'',pr=/presenter/i.test(n),m=/online/i.test(n)?'Online':(/offline/i.test(n)?'Offline':'');
p.style.display=pr?'':'none';p.querySelectorAll('[data-need]').forEach(function(e){e.required=pr&&e.dataset.need==='1'});p.querySelectorAll('.pres-only').forEach(function(e){e.required=pr});
rd.forEach(function(r){r.disabled=!!m&&r.value!==m;if(m&&r.value===m)r.checked=true});fee.textContent=(o&&o.dataset.fee)?o.dataset.fee:'-'}
function w(){var n=ab.value.trim().split(/\s+/).filter(Boolean).length;wc.textContent=n+' / '+MAX+' words';wc.style.color=(n>MAX||(n&&n<MIN))?'#b42323':''}
function pay(){var s=!!(f.files&&f.files.length);document.querySelector('input[name=pay_sender]').required=s;document.querySelector('input[name=pay_date]').required=s}
c.addEventListener('change',upd);ab.addEventListener('input',w);f.addEventListener('change',pay);upd();w()})();</script>
<?php endif; foot();
