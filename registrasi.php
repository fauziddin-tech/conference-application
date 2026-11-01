<?php require 'inc.php'; $u = need('peserta');
$R = regOf($u['id']);
$fees = fees(); $topics = items('topic');
$locked = $R && $R['pay_status'] === 'verified';
$closed = !$R && !regOpen();
// Setelah naskah masuk proses review, data & berkas naskah dikunci di formulir ini (revisi lewat dashboard).
$paperLocked = $R && $R['article_title'] && ($R['paper_status'] !== 'submitted' || reviewsOf($R['id']));
$amin = (int)setting('abs_min', '50'); $amax = (int)setting('abs_max', '250');
$err = [];
function fv($k) { return post($k); }
function v($k, $d = '') { global $R; return $_POST[$k] ?? (($R[$k] ?? '') !== '' && $R[$k] !== null ? $R[$k] : $d); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$locked && !$closed) {
    if (!$_POST) $err[] = t('No data received. The total file size may exceed the server limit (post_max_size).');
    else {
        chk();
        $cat = fv('category'); $att = fv('attendance'); $pres = isPres($cat); $inst = fv('institution');
        $f = ['prefix' => fv('prefix'), 'full_name' => fv('full_name'), 'country' => fv('country'), 'orcid' => strtoupper(fv('orcid')), 'phone' => fv('phone'), 'category' => $cat, 'attendance' => $att,
            'track' => $pres ? fv('track') : '', 'article_title' => $pres ? fv('article_title') : '', 'coauthors' => $pres ? fv('coauthors') : '', 'abstract' => $pres ? fv('abstract') : '', 'keywords' => $pres ? fv('keywords') : '',
            'pay_sender' => fv('pay_sender'), 'pay_date' => fv('pay_date') ?: null, 'dietary' => fv('dietary'), 'agreed' => 1];
        if ($paperLocked) {
            foreach (['track', 'article_title', 'coauthors', 'abstract', 'keywords'] as $k) $f[$k] = $R[$k];
            if (!$pres) $err[] = t('Your paper is under review, so the category cannot be changed to a non-presenter category. Please contact the committee.');
        }
        $feeRow = null; foreach ($fees as $x) if ($x['cat'] === $cat) $feeRow = $x;
        if (!$feeRow) $err[] = t('Please choose a registration category.');
        if (!in_array($att, ['Offline', 'Online'], true)) $err[] = t('Please choose an attendance type (Offline/Online).');
        elseif ($feeRow && modeOf($cat) && modeOf($cat) !== $att) $err[] = t('Category "%s" is only for %s attendance.', tx($cat), modeOf($cat));
        if ($f['full_name'] === '' || $inst === '' || $f['country'] === '' || $f['phone'] === '') $err[] = t('Full name, institution, country and phone number are required.');
        if ($f['full_name'] !== '' && !validName($f['full_name'])) $err[] = t('Full name may only contain letters, spaces, periods, commas and hyphens.');
        if ($f['orcid'] !== '' && !preg_match('/^\d{4}-\d{4}-\d{4}-\d{3}[\dX]$/', $f['orcid'])) $err[] = t('ORCID iD must use the format 0000-0000-0000-0000.');
        if ($pres) {
            $wc = words($f['abstract']); $kw = array_filter(array_map('trim', explode(',', $f['keywords'])));
            if ($f['article_title'] === '') $err[] = t('Article title is required.');
            if ($topics && $f['track'] === '') $err[] = t('Please choose a topic / track.');
            if ($wc < $amin || $wc > $amax) $err[] = t('The abstract must be %d-%d words (currently %d words).', $amin, $amax, $wc);
            if (count($kw) < 3 || count($kw) > 5) $err[] = t('Please provide 3-5 keywords separated by commas.');
        }
        $hasProof = !empty($_FILES['proof']['name']) || ($R && $R['pay_file']);
        if ($hasProof) {
            $d = DateTime::createFromFormat('Y-m-d', (string)$f['pay_date']);
            if (!$d || $d->format('Y-m-d') !== $f['pay_date'] || $d > new DateTime('today')) $err[] = t('Invalid payment date.');
            if ($f['pay_sender'] === '') $err[] = t('Payment sender name is required.');
        }
        $need = ['agree_info', 'agree_privacy']; if ($pres) $need = array_merge($need, ['agree_orig', 'agree_pub']);
        foreach ($need as $k) if (empty($_POST[$k])) { $err[] = t('All declarations must be ticked.'); break; }
        $art = null; $pay = null; $e = '';
        if (!$err && $pres && !$paperLocked) {
            if (!empty($_FILES['article']['name'])) { $art = upFile($_FILES['article'], paperExts(), $e, 'Article file'); if (!$art) $err[] = $e; }
            elseif (!($R && $R['article_file'])) $err[] = t('Please upload the article file.');
        }
        if (!$err && !empty($_FILES['proof']['name'])) { $pay = upFile($_FILES['proof'], ['pdf', 'jpg', 'jpeg', 'png'], $e, 'Proof of payment'); if (!$pay) $err[] = $e; }
        if ($err && $art) { rmPriv($art[0]); $art = null; }
        if (!$err) {
            $row = $f; $row['pay_amount'] = feeText($feeRow);
            if (!$pres && $R && $R['article_file']) { deletePaper($R['id']); $row['article_file'] = ''; $row['article_name'] = ''; $row['camera_file'] = ''; $row['camera_name'] = ''; }
            if ($pay) { rmPriv($R['pay_file'] ?? ''); $row['pay_file'] = $pay[0]; $row['pay_name'] = $pay[1]; }
            if (!$hasProof) { $row['pay_sender'] = ''; $row['pay_date'] = null; }
            $row['pay_status'] = $pay ? 'pending' : ($hasProof ? ($R['pay_status'] === 'unpaid' || $R['pay_status'] === 'rejected' ? 'pending' : $R['pay_status']) : 'unpaid');
            if ($R) {
                $sets = implode(',', array_map(function ($k) { return "$k=?"; }, array_keys($row)));
                $pdo->prepare("UPDATE registrations SET $sets WHERE id=?")->execute(array_merge(array_values($row), [$R['id']]));
                $rid = $R['id'];
            } else {
                $row['user_id'] = $u['id']; $row['ticket'] = newTicket();
                $cols = implode(',', array_keys($row)); $ph = implode(',', array_fill(0, count($row), '?'));
                $pdo->prepare("INSERT INTO registrations($cols) VALUES($ph)")->execute(array_values($row));
                $rid = $pdo->lastInsertId();
            }
            if ($art) { $q = $pdo->prepare("SELECT * FROM registrations WHERE id=?"); $q->execute([$rid]); $ver = addPaperFile($q->fetch(), 'submission', $art);
                notifyAdmins(($R ? 'Naskah diperbarui (v' . $ver . ')' : 'Naskah baru') . ': ' . no('R', $rid) . ' "' . mb_strimwidth($f['article_title'], 0, 80, '…') . '"', 'admin/?p=detail&id=' . $rid); }
            if (!$R) notifyAdmins('Pendaftaran baru ' . no('R', $rid) . ': ' . $f['full_name'] . ' (' . $cat . ')', 'admin/?p=detail&id=' . $rid);
            elseif ($pay) notifyAdmins('Bukti bayar diunggah ' . no('R', $rid) . ': ' . $f['full_name'], 'admin/?p=detail&id=' . $rid);
            $pdo->prepare("UPDATE users SET category=?,institution=?,phone=? WHERE id=?")->execute([$cat, $inst, $f['phone'], $u['id']]);
            if (!$R || $pay) {
                $txt = (!$R ? "Your registration has been received.\n\nRegistration number: " . no('R', $rid) . "\nCategory: $cat ($att)\nFee: " . $row['pay_amount'] . ($pres ? "\nPaper: " . $f['article_title'] : '') . "\n\n" : '')
                    . ($row['pay_status'] === 'unpaid' ? "Please complete your payment and upload the proof of payment from your dashboard. Your invoice is available in the dashboard." : "Your proof of payment has been received and will be verified by the committee.");
                notify($u['email'], $f['full_name'], !$R ? 'Registration received ' . no('R', $rid) : 'Proof of payment received', $txt);
            }
            flash($row['pay_status'] === 'unpaid' ? t('Registration saved. Please make the payment and upload the proof of payment (the invoice is in your dashboard).') : t('Registration saved. The committee will verify your payment.'));
            go('dashboard.php');
        }
    }
}
$cur = v('category', $u['category']);
head(t('Registration')); ?>
<h2><?= te('Conference Registration') ?></h2>
<?php if ($err): ?><div class="msg err"><ul><?php foreach ($err as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if ($closed): ?><p class="msg"><?= e(tx(setting('reg_close_msg'))) ?></p>
<?php elseif ($locked): ?><p class="msg"><?= te('Your payment has been verified, so your registration data is locked. Revised and camera-ready papers can be uploaded from the dashboard. Contact the committee for other changes.') ?> <a href="dashboard.php"><?= te('Back to dashboard') ?></a></p>
<?php else: ?>
<p><?= te('Please complete this form accurately. Fields marked * are required. Your full name will be printed on the e-certificate, LoA and badge.') ?></p>
<form method="post" enctype="multipart/form-data" class="card"><?= csrf() ?>
<h3>1. <?= te('Participant Information') ?></h3>
<label><?= te('Title') ?> *<select name="prefix"><?php foreach (['Prof.', 'Dr.', 'Mr.', 'Mrs.', 'Ms.', 'Other'] as $p): ?><option <?= v('prefix') === $p ? 'selected' : '' ?>><?= e($p) ?></option><?php endforeach; ?></select></label>
<label><?= te('Full name (as on certificate)') ?> *<input name="full_name" required value="<?= e(v('full_name', $u['name'])) ?>"></label>
<label><?= te('Email') ?><input value="<?= e($u['email']) ?>" disabled></label>
<label><?= te('Institution / Affiliation') ?> *<input name="institution" required value="<?= e($_POST['institution'] ?? $u['institution']) ?>"></label>
<label><?= te('Country') ?> *<input name="country" required list="countries" value="<?= e(v('country', 'Indonesia')) ?>"></label>
<datalist id="countries"><?php foreach (['Indonesia', 'Malaysia', 'Singapore', 'Thailand', 'Philippines', 'Vietnam', 'Brunei Darussalam', 'Timor-Leste', 'Australia', 'Japan', 'South Korea', 'China', 'India', 'Pakistan', 'Bangladesh', 'Saudi Arabia', 'Turkey', 'Egypt', 'Nigeria', 'South Africa', 'United Kingdom', 'Netherlands', 'Germany', 'United States', 'Canada'] as $c): ?><option><?= $c ?></option><?php endforeach; ?></datalist>
<label><?= te('Phone / WhatsApp') ?> *<input name="phone" type="tel" required value="<?= e(v('phone', $u['phone'])) ?>" placeholder="+62..."></label>
<label><?= te('ORCID iD (optional)') ?><input name="orcid" placeholder="0000-0000-0000-0000" value="<?= e(v('orcid')) ?>"></label>
<label><?= te('Dietary / accessibility needs (optional)') ?><input name="dietary" value="<?= e(v('dietary')) ?>" placeholder="<?= te('e.g. vegetarian, wheelchair access') ?>"></label>
<h3>2. <?= te('Registration Category & Attendance') ?></h3>
<label><?= te('Registration category') ?> *<select name="category" id="cat" required><option value="">-- <?= te('select') ?> --</option><?php foreach ($fees as $f): ?><option value="<?= e($f['cat']) ?>" data-fee="<?= e(feeText($f)) ?>" <?= $cur === $f['cat'] ? 'selected' : '' ?>><?= e(tx($f['cat']) . ' (' . feeNow($f)[0] . ')') ?></option><?php endforeach; ?></select></label>
<div><b><?= te('Attendance') ?> *</b><label class="chk"><input type="radio" name="attendance" value="Offline" required <?= v('attendance') === 'Offline' ? 'checked' : '' ?>> <?= te('Offline (on-site)') ?></label><label class="chk"><input type="radio" name="attendance" value="Online" <?= v('attendance') === 'Online' ? 'checked' : '' ?>> <?= te('Online (virtual)') ?></label></div>
<div id="paper" class="full sub"><h3 class="full">3. <?= te('Paper Submission (presenters)') ?></h3>
<?php if ($paperLocked): ?><p class="msg full"><?= te('Your paper is in the review process (status: %s), so the paper data is locked here. Upload revisions and the final paper from the dashboard.', lbl($PAPER, $R['paper_status'])) ?> <a href="dashboard.php#paper"><?= te('Open dashboard') ?></a></p><?php endif; ?>
<?php if ($topics): ?><label class="full"><?= te('Topic / track') ?> *<select name="track" data-need="1"<?= $paperLocked ? ' disabled' : '' ?>><option value="">-- <?= te('select') ?> --</option><?php foreach ($topics as $tp): ?><option value="<?= e($tp['title']) ?>" <?= v('track') === $tp['title'] ? 'selected' : '' ?>><?= e(tx($tp['title'])) ?></option><?php endforeach; ?></select></label><?php endif; ?>
<label class="full"><?= te('Article title') ?> *<input name="article_title" data-need="1"<?= $paperLocked ? ' readonly' : '' ?> value="<?= e(v('article_title')) ?>"></label>
<label class="full"><?= te('Co-authors (name & affiliation, one per line; optional)') ?><textarea name="coauthors" rows="3"<?= $paperLocked ? ' readonly' : '' ?>><?= e(v('coauthors')) ?></textarea></label>
<label class="full"><?= te('Abstract') ?> * <small>(<?= te('%d-%d words, no references', $amin, $amax) ?>) <span id="wc"></span></small><textarea name="abstract" id="abs" rows="8" data-need="1"<?= $paperLocked ? ' readonly' : '' ?>><?= e(v('abstract')) ?></textarea></label>
<label><?= te('Keywords') ?> * <small>(<?= te('3-5, comma separated') ?>)</small><input name="keywords" data-need="1"<?= $paperLocked ? ' readonly' : '' ?> value="<?= e(v('keywords')) ?>"></label>
<?php if (!$paperLocked): ?><label><?= te('Article file') ?> * <small>(<?= paperExtLabel() ?>, max 10 MB<?= setting('blind_review', '1') === '1' ? '; ' . te('remove author names for blind review') : '' ?>)</small><input type="file" name="article" accept="<?= '.' . implode(',.', paperExts()) ?>" <?= ($R && $R['article_file']) ? '' : 'data-need="1"' ?>><?php if ($R && $R['article_file']): ?><small><?= te('Current file') ?>: <a href="file.php?id=<?= $R['id'] ?>&k=article"><?= e($R['article_name']) ?></a> (<?= te('upload a new file to create a new version') ?>)</small><?php endif; ?></label><?php endif; ?>
<label class="chk full"><input type="checkbox" class="pres-only" name="agree_orig" <?= ($_POST ? !empty($_POST['agree_orig']) : ($R && $R['article_title'])) ? 'checked' : '' ?>> <?= te('I declare that this paper is original, has not been published, and is not under review elsewhere.') ?></label>
<label class="chk full"><input type="checkbox" class="pres-only" name="agree_pub" <?= ($_POST ? !empty($_POST['agree_pub']) : ($R && $R['article_title'])) ? 'checked' : '' ?>> <?= te('I agree that the accepted paper may be published in the conference proceedings / partner journals.') ?></label></div>
<h3>4. <?= te('Payment') ?></h3>
<?php if (setting('payment_info')): ?><div class="panel full"><b><?= te('Payment instructions') ?></b><br><?= nl2br(e(tx(setting('payment_info')))) ?></div><?php endif; ?>
<p class="full"><?= te('Registration fee') ?>: <b id="fee">-</b><?= earlyOn() ? ' <span class="tag on">Early bird</span>' : '' ?><br><small><?= te('You may upload the proof of payment now or later from the dashboard (e.g. after your paper is accepted). The invoice is available in the dashboard.') ?></small></p>
<label><?= te('Sender name') ?><input name="pay_sender" value="<?= e(v('pay_sender')) ?>"></label>
<label><?= te('Payment date') ?><input type="date" name="pay_date" max="<?= date('Y-m-d') ?>" value="<?= e(v('pay_date')) ?>"></label>
<label><?= te('Proof of payment') ?> <small>(PDF/JPG/PNG, max 10 MB)</small><input type="file" name="proof" accept=".pdf,.jpg,.jpeg,.png"><?php if ($R && $R['pay_file']): ?><small><?= te('Current file') ?>: <a href="file.php?id=<?= $R['id'] ?>&k=pay"><?= e($R['pay_name']) ?></a> (<?= te('leave empty to keep it') ?>)</small><?php endif; ?></label>
<h3>5. <?= te('Declaration') ?></h3>
<label class="chk full"><input type="checkbox" name="agree_info" required <?= ($_POST ? !empty($_POST['agree_info']) : $R) ? 'checked' : '' ?>> <?= te('I confirm that all information provided is accurate.') ?></label>
<label class="chk full"><input type="checkbox" name="agree_privacy" required <?= ($_POST ? !empty($_POST['agree_privacy']) : $R) ? 'checked' : '' ?>> <?= te('I consent to the processing of my personal data for conference administration, certificates, and publication purposes.') ?></label>
<button class="btn"><?= $R ? te('Update Registration') : te('Submit Registration') ?></button></form>
<script>(function(){var c=document.getElementById('cat'),p=document.getElementById('paper'),fee=document.getElementById('fee'),ab=document.getElementById('abs'),wc=document.getElementById('wc'),rd=document.querySelectorAll('input[name=attendance]'),f=document.querySelector('input[name=proof]'),MIN=<?= $amin ?>,MAX=<?= $amax ?>;
function upd(){var o=c.options[c.selectedIndex],n=o?o.value:'',pr=/presenter/i.test(n),m=/online/i.test(n)?'Online':(/offline/i.test(n)?'Offline':'');
p.style.display=pr?'':'none';p.querySelectorAll('[data-need]').forEach(function(e){e.required=pr&&e.dataset.need==='1'});p.querySelectorAll('.pres-only').forEach(function(e){e.required=pr});
rd.forEach(function(r){r.disabled=!!m&&r.value!==m;if(m&&r.value===m)r.checked=true});fee.textContent=(o&&o.dataset.fee)?o.dataset.fee:'-'}
function w(){var n=ab.value.trim().split(/\s+/).filter(Boolean).length;wc.textContent=n+' / '+MAX+' '+<?= json_encode(t('words')) ?>;wc.style.color=(n>MAX||(n&&n<MIN))?'#b42323':''}
function pay(){var s=!!(f.files&&f.files.length);document.querySelector('input[name=pay_sender]').required=s;document.querySelector('input[name=pay_date]').required=s}
c.addEventListener('change',upd);ab.addEventListener('input',w);f.addEventListener('change',pay);upd();w()})();</script>
<?php endif; foot();
