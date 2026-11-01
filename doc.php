<?php require 'inc.php'; $u = need(['peserta', 'admin']);
/* Dokumen siap cetak / simpan PDF (Ctrl+P): invoice, receipt, loa, invitation, badge, cert */
$t = (string)($_GET['t'] ?? '');
if ($u['role'] === 'admin') { $q = $pdo->prepare("SELECT * FROM registrations WHERE id=?"); $q->execute([(int)($_GET['id'] ?? 0)]); $R = $q->fetch(); }
else $R = regOf($u['id']);
if (!$R) { http_response_code(404); die('Data pendaftaran tidak ditemukan.'); }
$q = $pdo->prepare("SELECT * FROM users WHERE id=?"); $q->execute([$R['user_id']]); $P = $q->fetch();
$pres = (bool)$R['article_title']; $ver = $R['pay_status'] === 'verified';
$allowed = ['invoice' => true, 'receipt' => $ver, 'badge' => $ver, 'invitation' => $ver, 'loa' => $pres && $R['paper_status'] === 'accepted', 'cert' => certReady($R)];
if (!isset($allowed[$t])) { http_response_code(404); die('Dokumen tidak dikenal.'); }
if (!$allowed[$t] && $u['role'] !== 'admin') { http_response_code(403); die('Dokumen belum tersedia untuk status pendaftaran Anda.'); }

$name = trim($R['prefix'] === 'Other' ? $R['full_name'] : $R['prefix'] . ' ' . $R['full_name']);
$signers = rows('signers'); $s1 = $signers[0] ?? [setting('organizer') ?: 'Organizing Committee', 'Chairman of the Committee'];
$verifyUrl = absUrl('verify.php?c=' . $R['ticket']);
$today = fdateEn(date('Y-m-d'));
$ref = no('R', $R['id']);
$titles = ['invoice' => 'Invoice', 'receipt' => 'Payment Receipt', 'loa' => 'Letter of Acceptance', 'invitation' => 'Invitation Letter', 'badge' => 'E-Ticket & Name Badge', 'cert' => 'Certificate'];
function letterhead() { ?><header class="lh"><?php if (setting('logo_url')): ?><img src="<?= e(imgSrc(setting('logo_url'))) ?>" alt=""><?php endif; ?><div><b><?= e(setting('name')) ?></b><?php if (setting('organizer')): ?><br><?= e(setting('organizer')) ?><?php endif; ?><br><small><?= e(setting('secretariat')) ?><?= setting('email') ? ' · ' . e(setting('email')) : '' ?><?= setting('website') ? ' · ' . e(setting('website')) : '' ?></small></div></header><?php }
function sign($s, $date = true) { ?><div class="sign"><?php if ($date): ?><p><?= e(fdateEn(date('Y-m-d'))) ?></p><?php endif; ?><p><?= e($s[1] ?? '') ?>,</p><div class="sp"></div><p><b><u><?= e($s[0] ?? '') ?></u></b></p></div><?php }
?><!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= e($titles[$t] . ' ' . $ref . ' - ' . brand()) ?></title>
<style>
:root{--deep:#1d3a3a;--teal:#27706f;--tan:#d4a373;--sand:#eadfc8}
*{box-sizing:border-box}body{margin:0;background:#e9e6df;font:15px/1.6 Georgia,'Times New Roman',serif;color:#1d1d1d}
.bar{position:sticky;top:0;background:var(--deep);color:#fff;padding:10px 16px;display:flex;gap:10px;align-items:center;justify-content:space-between;font-family:system-ui,sans-serif;flex-wrap:wrap}
.bar a,.bar button{color:#fff;background:var(--teal);border:0;padding:7px 14px;border-radius:6px;text-decoration:none;font:600 14px system-ui,sans-serif;cursor:pointer}
.page{background:#fff;width:210mm;min-height:297mm;margin:20px auto;padding:18mm 20mm;box-shadow:0 2px 14px #0002;position:relative}
.lh{display:flex;gap:14px;align-items:center;border-bottom:3px double var(--deep);padding-bottom:10px;margin-bottom:22px}.lh img{height:64px}.lh b{font-size:1.15rem;color:var(--deep)}
h1{font:700 1.6rem system-ui,sans-serif;color:var(--deep);margin:0 0 4px;letter-spacing:.04em}
table{width:100%;border-collapse:collapse;margin:16px 0}th,td{border:1px solid #ccc;padding:8px;text-align:left;vertical-align:top}th{background:#f3eee3}
.meta td{border:0;padding:2px 8px 2px 0}.right{text-align:right}
.stamp{display:inline-block;border:3px solid;padding:4px 16px;font:800 1.3rem system-ui,sans-serif;letter-spacing:.1em;transform:rotate(-6deg)}.paid{color:#1f7a3a}.unpaid{color:#b42323}
.sign{margin-top:40px;width:280px;margin-left:auto}.sign p{margin:2px 0}.sign .sp{height:70px}
.qr{width:120px;height:120px}.qr img,.qr canvas{width:100%!important;height:100%!important}
.small{font-size:.85rem;color:#555}
/* badge */
.badges{display:flex;gap:10mm;flex-wrap:wrap;justify-content:center}
.badge{width:90mm;height:130mm;border:1px solid #bbb;border-radius:4mm;overflow:hidden;display:flex;flex-direction:column;text-align:center;font-family:system-ui,sans-serif}
.badge .top{background:linear-gradient(135deg,var(--deep),var(--teal));color:#fff;padding:5mm 4mm;font-weight:700;font-size:.95rem}
.badge .nm{font-size:1.45rem;font-weight:800;margin:6mm 4mm 1mm;line-height:1.2}.badge .af{font-size:.9rem;padding:0 4mm;color:#333}
.badge .qr{margin:auto auto 2mm;width:34mm;height:34mm}.badge .cat{background:var(--tan);color:var(--deep);font-weight:800;padding:3mm;text-transform:uppercase;letter-spacing:.06em}
/* sertifikat */
.cert{width:297mm;min-height:210mm;padding:14mm;text-align:center}
.cert .frame{border:3mm double var(--deep);outline:1.2mm solid var(--tan);outline-offset:-6mm;min-height:182mm;padding:14mm 18mm;position:relative;display:flex;flex-direction:column;align-items:center}
.cert h1{font:700 2.6rem Georgia,serif;letter-spacing:.12em;margin:4mm 0 0}.cert h2{font:400 1.1rem system-ui,sans-serif;letter-spacing:.3em;color:var(--teal);margin:0 0 6mm;text-transform:uppercase}
.cert .who{font:italic 700 2.2rem Georgia,serif;color:var(--deep);border-bottom:1px solid var(--tan);padding:0 10mm 2mm;margin:4mm 0}
.cert .signs{display:flex;gap:20mm;justify-content:center;margin-top:auto}.cert .signs div{min-width:60mm}.cert .signs .sp{height:18mm}
.cert .qr{position:absolute;left:12mm;bottom:10mm;width:26mm;height:26mm}.cert .no{position:absolute;right:12mm;bottom:10mm;font:12px system-ui,sans-serif;color:#555}
.cert .lg{height:22mm}
@media print{body{background:#fff}.bar{display:none}.page{margin:0;box-shadow:none;width:auto;min-height:auto}@page{size:A4 <?= $t === 'cert' ? 'landscape' : 'portrait' ?>;margin:0}}
@media (max-width:820px){.page{width:100%;min-height:0;padding:16px;margin:0}.cert .frame{padding:8mm}}
</style></head><body>
<div class="bar"><span><?= e($titles[$t]) ?> &middot; <?= e($ref) ?></span><span><button onclick="window.print()">Cetak / Simpan PDF</button> <a href="<?= $u['role'] === 'admin' ? 'admin/?p=detail&id=' . $R['id'] : 'dashboard.php' ?>">Kembali</a></span></div>

<?php if ($t === 'invoice' || $t === 'receipt'): $paid = $ver; ?>
<div class="page"><?php letterhead(); ?>
<table class="meta"><tr><td><h1><?= $t === 'invoice' ? 'INVOICE' : 'PAYMENT RECEIPT' ?></h1>
<table class="meta"><tr><td>No.</td><td>: <b><?= $t === 'invoice' ? 'INV' : 'RCP' ?>/<?= e(brand()) ?>/<?= e($ref) ?></b></td></tr><tr><td>Date</td><td>: <?= e(fdateEn($t === 'invoice' ? $R['created_at'] : ($R['pay_date'] ?: $R['updated_at']))) ?></td></tr><tr><td>Status</td><td>: <?= e(strtoupper(lbl(['unpaid' => 'Unpaid', 'pending' => 'Awaiting verification', 'verified' => 'Paid', 'rejected' => 'Payment rejected'], $R['pay_status']))) ?></td></tr></table></td>
<td class="right"><span class="stamp <?= $paid ? 'paid' : 'unpaid' ?>"><?= $paid ? 'PAID' : 'UNPAID' ?></span></td></tr></table>
<p><b>Billed to:</b><br><?= e($name) ?><br><?= e($P['institution']) ?><br><?= e($R['country']) ?><br><?= e($P['email']) ?> &middot; <?= e($R['phone']) ?></p>
<table><tr><th>#</th><th>Description</th><th class="right">Amount</th></tr>
<tr><td>1</td><td>Registration fee &ndash; <?= e(setting('name')) ?><br><small>Category: <?= e($R['category']) ?> (<?= e($R['attendance']) ?>)<?= $pres ? '<br>Paper: ' . e($R['article_title']) : '' ?></small></td><td class="right"><?= e($R['pay_amount']) ?></td></tr>
<tr><th colspan="2" class="right">TOTAL</th><th class="right"><?= e($R['pay_amount']) ?></th></tr></table>
<?php if ($t === 'receipt'): ?><p>Received from <b><?= e($R['pay_sender'] ?: $name) ?></b> on <?= e(fdateEn($R['pay_date'])) ?> as full payment of the registration fee above.</p>
<?php elseif (setting('payment_info')): ?><p><b>Payment instructions</b><br><?= nl2br(e(setting('payment_info'))) ?></p><p class="small">Please write <b><?= e($ref) ?></b> in the transfer description and upload the proof of payment in your dashboard.</p><?php endif; ?>
<?php if (setting('invoice_note')): ?><p class="small"><?= nl2br(e(setting('invoice_note'))) ?></p><?php endif; ?>
<?php if ($t === 'receipt') sign([setting('organizer') ?: $s1[0], 'Treasurer, Organizing Committee'], true); ?>
<p class="small">This document is generated electronically. Verify at <?= e($verifyUrl) ?></p></div>

<?php elseif ($t === 'loa'): ?>
<div class="page"><?php letterhead(); ?>
<p class="right"><?= e($today) ?></p>
<p>Ref: LoA/<?= e(brand()) ?>/<?= e($ref) ?></p>
<p>To:<br><b><?= e($name) ?></b><br><?= e($P['institution']) ?>, <?= e($R['country']) ?></p>
<?php if ($R['coauthors']): ?><p class="small">Co-author(s): <?= e(str_replace(["\r\n", "\n"], '; ', $R['coauthors'])) ?></p><?php endif; ?>
<h1 style="text-align:center;margin:24px 0">LETTER OF ACCEPTANCE</h1>
<p>Dear <?= e($name) ?>,</p>
<p>On behalf of the Organizing and Scientific Committee, we are pleased to inform you that your paper entitled:</p>
<p style="text-align:center;font-size:1.1rem"><b>&ldquo;<?= e($R['article_title']) ?>&rdquo;</b></p>
<p>(Paper ID: <?= e($ref) ?><?= $R['track'] ? ', Track: ' . e($R['track']) : '' ?>) has been <b>ACCEPTED</b> for presentation at the <b><?= e(setting('name')) ?></b>, to be held on <?= e(setting('date')) ?> at <?= e(setting('location')) ?><?= $R['attendance'] === 'Online' ? ' (online presentation)' : '' ?>.</p>
<p>Please make sure that the registration fee has been paid and that the final (camera-ready) version of your manuscript, prepared according to the author guidelines and reviewers' comments, is uploaded through your participant dashboard.</p>
<p>Congratulations, and we look forward to your presentation.</p>
<?php sign($s1, false); ?>
<p class="small">Verify this letter at <?= e($verifyUrl) ?></p></div>

<?php elseif ($t === 'invitation'): ?>
<div class="page"><?php letterhead(); ?>
<p class="right"><?= e($today) ?></p>
<p>Ref: INV-L/<?= e(brand()) ?>/<?= e($ref) ?></p>
<h1 style="text-align:center;margin:24px 0">LETTER OF INVITATION</h1>
<p>To whom it may concern,</p>
<p>The Organizing Committee of the <b><?= e(setting('name')) ?></b> is pleased to invite:</p>
<table class="meta" style="margin-left:24px"><tr><td>Name</td><td>: <b><?= e($name) ?></b></td></tr><tr><td>Institution</td><td>: <?= e($P['institution']) ?></td></tr><tr><td>Country</td><td>: <?= e($R['country']) ?></td></tr><tr><td>Participation</td><td>: <?= e($R['category']) ?> (<?= e($R['attendance']) ?>)</td></tr><tr><td>Registration No.</td><td>: <?= e($ref) ?></td></tr></table>
<p>to attend the conference, which will take place on <b><?= e(setting('date')) ?></b> at <b><?= e(setting('location')) ?></b>.<?= $pres ? ' The participant will present the paper entitled &ldquo;' . e($R['article_title']) . '&rdquo;.' : '' ?></p>
<p>The participant has completed registration and payment of the conference fee. This letter is issued for visa application, travel, or institutional permission purposes. Travel, accommodation, and living expenses are the responsibility of the participant unless otherwise stated.</p>
<p>Should you require further information, please contact the committee at <?= e(setting('email')) ?>.</p>
<?php sign($s1, false); ?>
<p class="small">Verify this letter at <?= e($verifyUrl) ?></p></div>

<?php elseif ($t === 'badge'): ?>
<div class="page"><h1>E-Ticket</h1><p>Tunjukkan QR code ini di meja registrasi untuk check-in. Cetak badge di bawah (potong mengikuti garis) atau simpan di ponsel.</p>
<table class="meta"><tr><td>Registration No.</td><td>: <b><?= e($ref) ?></b></td></tr><tr><td>Ticket code</td><td>: <b><?= e($R['ticket']) ?></b></td></tr><tr><td>Event</td><td>: <?= e(setting('date')) ?> &middot; <?= e(setting('time')) ?> &middot; <?= e(setting('location')) ?></td></tr><tr><td>Attendance</td><td>: <?= e($R['attendance']) ?></td></tr></table>
<div class="badges"><div class="badge"><div class="top"><?= e(brand()) ?><br><small><?= e(setting('date')) ?></small></div>
<div class="nm"><?= e($name) ?></div><div class="af"><?= e($P['institution']) ?><br><?= e($R['country']) ?></div>
<div class="qr" data-qr="<?= e(absUrl('checkin.php?c=' . $R['ticket'])) ?>"></div><div class="small"><?= e($ref) ?> &middot; <?= e($R['ticket']) ?></div>
<div class="cat"><?= e($pres ? 'Presenter' : (stripos($R['category'], 'participant') !== false ? 'Participant' : $R['category'])) ?></div></div></div></div>

<?php elseif ($t === 'cert'): $role = $pres && $R['paper_status'] === 'accepted' ? 'Presenter' : 'Participant'; ?>
<div class="page cert"><div class="frame"><?php if (setting('logo_url')): ?><img class="lg" src="<?= e(imgSrc(setting('logo_url'))) ?>" alt=""><?php endif; ?>
<h1>CERTIFICATE</h1><h2>of <?= $role === 'Presenter' ? 'Presentation' : 'Participation' ?></h2>
<p>This is to certify that</p><div class="who"><?= e($name) ?></div><p><?= e($P['institution']) ?></p>
<p style="max-width:200mm">has <?= $role === 'Presenter' ? 'presented the paper entitled <b>&ldquo;' . e($R['article_title']) . '&rdquo;</b>' : 'participated' ?> as a <b><?= $role ?></b><?= $R['attendance'] === 'Online' ? ' (online)' : '' ?> in the<br><b><?= e(setting('name')) ?></b><br><?= e(setting('tagline')) ?><br>held on <?= e(setting('date')) ?> at <?= e(setting('location')) ?>.</p>
<div class="signs"><?php foreach (array_slice($signers ?: [$s1], 0, 3) as $s): ?><div><p><?= e($s[1] ?? '') ?></p><div class="sp"></div><p><b><u><?= e($s[0]) ?></u></b></p></div><?php endforeach; ?></div>
<div class="qr" data-qr="<?= e($verifyUrl) ?>"></div><div class="no">No. CERT/<?= e(brand()) ?>/<?= e($ref) ?><br>Verify: <?= e($verifyUrl) ?></div></div></div>
<?php endif; ?>

<script src="<?= BASE ?>assets/qrcode.min.js"></script>
<script>document.querySelectorAll('[data-qr]').forEach(function(el){if(window.QRCode){new QRCode(el,{text:el.dataset.qr,width:256,height:256,correctLevel:QRCode.CorrectLevel.M})}else{el.textContent=el.dataset.qr;el.style.fontSize='9px';el.style.wordBreak='break-all'}});</script>
</body></html>
