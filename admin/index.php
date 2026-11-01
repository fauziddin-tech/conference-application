<?php require '../inc.php'; $u = need('admin'); chk();
define('ADMIN', 1);
$p = preg_replace('/[^a-z]/', '', (string)($_GET['p'] ?? 'ringkasan')) ?: 'ringkasan';

/* Jenis konten fleksibel (tabel items) dan label field-nya */
$KINDS = [
    'date' => ['Tanggal Penting', ['title' => 'Kegiatan *', 'extra' => 'Tanggal *', 'body' => 'Keterangan (opsional)']],
    'topic' => ['Topik / Track', ['title' => 'Nama topik *', 'body' => 'Deskripsi singkat (opsional)']],
    'committee' => ['Komite', ['title' => 'Nama *', 'subtitle' => 'Jabatan / peran', 'body' => 'Afiliasi', 'extra' => 'Kelompok (mis. Steering Committee, Scientific Committee)']],
    'sponsor' => ['Sponsor & Mitra', ['title' => 'Nama *', 'url' => 'Website', 'image' => 'Logo', 'extra' => 'Tingkat (mis. Platinum, Gold, Media Partner)']],
    'faq' => ['FAQ', ['title' => 'Pertanyaan *', 'body' => 'Jawaban']],
    'news' => ['Pengumuman', ['title' => 'Judul *', 'body' => 'Isi', 'url' => 'Tautan (opsional)', 'extra' => 'Tampil untuk']],
    'download' => ['Unduhan (template, panduan)', ['title' => 'Nama file *', 'url' => 'File / URL']],
];
$AUD = [
    'all' => 'Semua pendaftar (formulir terisi)', 'verified' => 'Pembayaran terverifikasi', 'unpaid' => 'Belum bayar / bukti ditolak', 'pending' => 'Menunggu verifikasi pembayaran',
    'presenters' => 'Semua presenter', 'accepted' => 'Presenter dengan naskah diterima', 'revision' => 'Presenter yang perlu revisi', 'online' => 'Peserta online terverifikasi',
    'offline' => 'Peserta offline terverifikasi', 'noreg' => 'Akun yang belum mengisi formulir', 'reviewers' => 'Reviewer',
];
function audienceRows($a)
{
    global $pdo;
    $w = ['all' => '1', 'verified' => "r.pay_status='verified'", 'unpaid' => "r.pay_status IN('unpaid','rejected')", 'pending' => "r.pay_status='pending'", 'presenters' => "r.article_title<>''",
        'accepted' => "r.paper_status='accepted' AND r.article_title<>''", 'revision' => "r.paper_status='revision' AND r.article_title<>''", 'online' => "r.pay_status='verified' AND r.attendance='Online'", 'offline' => "r.pay_status='verified' AND r.attendance='Offline'"];
    if ($a === 'noreg') return $pdo->query("SELECT u.email,u.name,'' regno FROM users u WHERE u.role='peserta' AND NOT EXISTS(SELECT 1 FROM registrations r WHERE r.user_id=u.id)")->fetchAll();
    if ($a === 'reviewers') return $pdo->query("SELECT email,name,'' regno FROM users WHERE role='reviewer'")->fetchAll();
    if (!isset($w[$a])) return [];
    return $pdo->query("SELECT u.email,TRIM(CONCAT(IF(r.prefix='Other','',r.prefix),' ',r.full_name)) name,CONCAT('R',LPAD(r.id,4,'0')) regno FROM registrations r JOIN users u ON u.id=r.user_id WHERE " . $w[$a])->fetchAll();
}
function clean($s) { return trim(str_replace(['|', "\r", "\n"], ' ', (string)$s)); }
function jurl($s) { $s = trim((string)$s); if ($s !== '' && !preg_match('#^https?://#i', $s)) $s = 'https://' . $s; return clean($s); }
function saveRows($key, $rs, $n) { $o = []; foreach ($rs as $r) { $r = array_pad($r, $n, ''); $o[] = implode('|', array_slice($r, 0, $n)); } setSetting($key, implode("\n", $o)); }
function jrow() { return [clean(post('name')), jurl(post('url')), clean(post('accr')), clean(post('publisher')), clean(post('issn')), jurl(post('scope')), jurl(post('guide')), jurl(post('cover')), clean(post('note'))]; }

/* ---------- ekspor ---------- */
if ($p === 'exportreg') csvOut('registrasi-' . date('Ymd') . '.csv',
    ['No Reg', 'Title', 'Nama', 'Email', 'Institusi', 'Negara', 'HP', 'ORCID', 'Kategori', 'Kehadiran', 'Track', 'Judul Artikel', 'Co-authors', 'Keywords', 'Status Naskah', 'Camera-ready', 'Biaya', 'Tgl Bayar', 'Pengirim', 'Status Bayar', 'Kode Tiket', 'Check-in', 'Diet/Aksesibilitas', 'Daftar'],
    $pdo->query("SELECT CONCAT('R',LPAD(r.id,4,'0')),r.prefix,r.full_name,u.email,u.institution,r.country,r.phone,r.orcid,r.category,r.attendance,r.track,r.article_title,r.coauthors,r.keywords,IF(r.article_title<>'',r.paper_status,''),IF(r.camera_file<>'','ya',''),r.pay_amount,r.pay_date,r.pay_sender,r.pay_status,r.ticket,r.checked_in_at,r.dietary,r.created_at FROM registrations r JOIN users u ON u.id=r.user_id ORDER BY r.id")->fetchAll());
if ($p === 'export') csvOut('akun-' . date('Ymd') . '.csv', ['ID', 'Peran', 'Kategori', 'Nama', 'Email', 'Institusi', 'HP', 'Status', 'Daftar', 'Login terakhir'],
    $pdo->query("SELECT CONCAT('U',LPAD(id,4,'0')),role,category,name,email,institution,phone,status,created_at,last_login FROM users WHERE role<>'admin' ORDER BY id")->fetchAll());
if ($p === 'exportrev') csvOut('review-' . date('Ymd') . '.csv', ['Paper', 'Judul', 'Reviewer', 'Skor', 'Rekomendasi', 'Komentar penulis', 'Catatan panitia', 'Dikirim'],
    $pdo->query("SELECT CONCAT('R',LPAD(r.id,4,'0')),r.article_title,u.name,v.score,v.recommendation,v.to_author,v.to_committee,v.submitted_at FROM reviews v JOIN registrations r ON r.id=v.reg_id JOIN users u ON u.id=v.reviewer_id ORDER BY r.id")->fetchAll());

/* ---------- aksi (POST) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = post('a'); $id = (int)($_POST['id'] ?? 0); $msg = null; $back = null;
    switch ($a) {
    case 'status':
        $st = in_array($_POST['status'] ?? '', ['pending', 'confirmed', 'rejected'], true) ? $_POST['status'] : 'pending';
        $pdo->prepare("UPDATE users SET status=? WHERE id=? AND role='peserta'")->execute([$st, $id]); break;
    case 'del':
        $q = $pdo->prepare("SELECT id,article_file,pay_file,camera_file FROM registrations WHERE user_id=?"); $q->execute([$id]);
        if ($r0 = $q->fetch()) { rmPriv($r0['article_file']); rmPriv($r0['pay_file']); rmPriv($r0['camera_file']); $pdo->prepare("DELETE FROM reviews WHERE reg_id=?")->execute([$r0['id']]); }
        $pdo->prepare("DELETE FROM reviews WHERE reviewer_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM registrations WHERE user_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM users WHERE id=? AND role IN('peserta','reviewer')")->execute([$id]);
        $msg = 'Akun dihapus.'; break;
    case 'spamclean': // hapus akun peserta mencurigakan yang belum mengisi formulir
        $n = 0;
        foreach ($pdo->query("SELECT u.id,u.name,u.email FROM users u WHERE u.role='peserta' AND NOT EXISTS(SELECT 1 FROM registrations r WHERE r.user_id=u.id)")->fetchAll() as $s)
            if (!validName($s['name']) || blockedEmail($s['email'])) { $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$s['id']]); $n++; }
        $msg = "$n akun mencurigakan dihapus."; $back = '?p=pengguna'; break;
    case 'uadd': // akun reviewer / admin baru
        $n = post('name'); $m = strtolower(post('email')); $role = in_array(post('role'), ['reviewer', 'admin'], true) ? post('role') : 'reviewer';
        $q = $pdo->prepare("SELECT id FROM users WHERE email=?"); $q->execute([$m]);
        if ($n === '' || !filter_var($m, FILTER_VALIDATE_EMAIL)) $msg = 'Nama dan email valid wajib diisi.';
        elseif ($q->fetch()) $msg = 'Email sudah terdaftar.';
        else {
            $pw = substr(strtr(base64_encode(random_bytes(9)), '+/=', 'xyz'), 0, 10);
            $pdo->prepare("INSERT INTO users(name,email,institution,password,role,status) VALUES(?,?,?,?,?,'confirmed')")->execute([$n, $m, post('institution'), password_hash($pw, PASSWORD_DEFAULT), $role]);
            $sent = !empty($_POST['send']) && notify($m, $n, 'Your ' . $role . ' account', "An account has been created for you as $role of " . setting('name') . ".\n\nLogin: " . absUrl('login.php') . "\nEmail: $m\nPassword: $pw\n\nPlease change your password after logging in.");
            $msg = 'Akun ' . $role . ' dibuat. Password awal: ' . $pw . ($sent ? ' (juga dikirim via email).' : ' (catat dan sampaikan; tidak ditampilkan lagi).');
        }
        $back = '?p=pengguna'; break;
    case 'uedit':
        $n = post('name'); $m = strtolower(post('email')); $back = '?p=edit&id=' . $id;
        $q = $pdo->prepare("SELECT id FROM users WHERE email=? AND id<>?"); $q->execute([$m, $id]);
        if ($n === '' || !filter_var($m, FILTER_VALIDATE_EMAIL)) $msg = 'Nama dan email valid wajib diisi.';
        elseif ($q->fetch()) $msg = 'Email sudah dipakai akun lain.';
        else {
            $sv = in_array($_POST['status'] ?? '', ['pending', 'confirmed', 'rejected'], true) ? $_POST['status'] : 'pending';
            $role = in_array(post('role'), ['peserta', 'reviewer'], true) ? post('role') : 'peserta';
            $pdo->prepare("UPDATE users SET name=?,email=?,institution=?,phone=?,category=?,status=?,role=? WHERE id=? AND role IN('peserta','reviewer')")->execute([$n, $m, post('institution'), post('phone'), post('category'), $sv, $role, $id]);
            $msg = 'Akun diperbarui.'; $back = '?p=pengguna';
        }
        break;
    case 'pwreset':
        $back = '?p=edit&id=' . $id; $pw = (string)($_POST['password'] ?? '');
        if ($pw === '') $pw = substr(strtr(base64_encode(random_bytes(9)), '+/=', 'xyz'), 0, 10);
        if (strlen($pw) < 8) $msg = 'Password minimal 8 karakter.';
        else { $pdo->prepare("UPDATE users SET password=? WHERE id=? AND role IN('peserta','reviewer')")->execute([password_hash($pw, PASSWORD_DEFAULT), $id]); $msg = 'Password direset. Password baru: ' . $pw . ' (catat dan sampaikan ke pengguna; tidak ditampilkan lagi).'; }
        break;
    case 'regsave':
        $back = '?p=detail&id=' . $id;
        $q = $pdo->prepare("SELECT r.*,u.email FROM registrations r JOIN users u ON u.id=r.user_id WHERE r.id=?"); $q->execute([$id]); $old = $q->fetch();
        if (!$old) break;
        $ps = isset($PAY[$_POST['pay_status'] ?? '']) ? $_POST['pay_status'] : $old['pay_status'];
        $pp = isset($PAPER[$_POST['paper_status'] ?? '']) ? $_POST['paper_status'] : $old['paper_status'];
        $note = post('admin_note');
        $pdo->prepare("UPDATE registrations SET pay_status=?,paper_status=?,admin_note=? WHERE id=?")->execute([$ps, $pp, $note, $id]);
        $pdo->prepare("UPDATE users SET status=? WHERE id=?")->execute([['unpaid' => 'pending', 'pending' => 'pending', 'verified' => 'confirmed', 'rejected' => 'rejected'][$ps], $old['user_id']]);
        $nm = trim(($old['prefix'] === 'Other' ? '' : $old['prefix']) . ' ' . $old['full_name']); $sent = [];
        if (!empty($_POST['notify'])) {
            $nt = $note !== '' ? "\n\nNote from the committee:\n$note" : '';
            if ($ps !== $old['pay_status'] && $ps === 'verified') $sent[] = notify($old['email'], $nm, 'Payment verified', "Your payment for registration " . no('R', $id) . " has been verified. Your receipt, e-ticket (QR code for check-in), and invitation letter are now available in your dashboard." . ($old['attendance'] === 'Online' && setting('online_link') ? "\n\nThe online meeting link is available in your dashboard." : '') . $nt);
            if ($ps !== $old['pay_status'] && $ps === 'rejected') $sent[] = notify($old['email'], $nm, 'Payment could not be verified', "We could not verify the proof of payment for registration " . no('R', $id) . ". Please check the note below and upload a valid proof of payment from your dashboard." . $nt);
            if ($old['article_title'] && $pp !== $old['paper_status']) {
                $txt = ['accepted' => "Congratulations! Your paper \"" . $old['article_title'] . "\" has been ACCEPTED. Your Letter of Acceptance is available in the dashboard. Please upload the final (camera-ready) version of your paper through the dashboard.",
                    'revision' => "Your paper \"" . $old['article_title'] . "\" requires REVISION. Please read the reviewers' comments in your dashboard and upload the revised manuscript.",
                    'rejected' => "We regret to inform you that your paper \"" . $old['article_title'] . "\" could not be accepted. Reviewers' comments are available in your dashboard. Thank you for your submission."][$pp] ?? '';
                if ($txt) $sent[] = notify($old['email'], $nm, 'Paper decision ' . no('R', $id), $txt . $nt);
            }
        }
        $msg = 'Perubahan disimpan.' . ($sent ? ' Email notifikasi: ' . count(array_filter($sent)) . '/' . count($sent) . ' terkirim.' : ''); break;
    case 'assign':
        $back = '?p=detail&id=' . $id; $rid = (int)($_POST['reviewer'] ?? 0);
        $q = $pdo->prepare("SELECT name,email FROM users WHERE id=? AND role='reviewer'"); $q->execute([$rid]); $rv = $q->fetch();
        if (!$rv) { $msg = 'Pilih reviewer.'; break; }
        $pdo->prepare("INSERT IGNORE INTO reviews(reg_id,reviewer_id) VALUES(?,?)")->execute([$id, $rid]);
        $pdo->prepare("UPDATE registrations SET paper_status='under_review' WHERE id=? AND paper_status='submitted'")->execute([$id]);
        $q = $pdo->prepare("SELECT article_title FROM registrations WHERE id=?"); $q->execute([$id]);
        $ok = notify($rv['email'], $rv['name'], 'Review request ' . no('R', $id), "You have been assigned to review the paper \"" . $q->fetchColumn() . "\" (" . no('R', $id) . ").\n\nPlease log in to submit your review:\n" . absUrl('reviewer.php?id=' . $id));
        $msg = 'Reviewer ditugaskan' . ($ok ? ' dan diberi tahu via email.' : ' (email notifikasi gagal terkirim).'); break;
    case 'unassign':
        $back = '?p=detail&id=' . $id; $pdo->prepare("DELETE FROM reviews WHERE reg_id=? AND reviewer_id=?")->execute([$id, (int)($_POST['reviewer'] ?? 0)]); $msg = 'Penugasan reviewer dihapus.'; break;
    case 'agenda': case 'agedit':
        $v = [post('day'), post('time'), post('title'), post('speaker'), post('room'), post('track'), post('description')];
        if ($v[0] === '' || $v[2] === '') { $msg = 'Hari dan judul sesi wajib diisi.'; break; }
        if ($a === 'agenda') $pdo->prepare("INSERT INTO agenda(day,time,title,speaker,room,track,description) VALUES(?,?,?,?,?,?,?)")->execute($v);
        else $pdo->prepare("UPDATE agenda SET day=?,time=?,title=?,speaker=?,room=?,track=?,description=? WHERE id=?")->execute(array_merge($v, [$id]));
        $msg = 'Agenda disimpan.'; break;
    case 'delag':
        $pdo->prepare("DELETE FROM agenda WHERE id=?")->execute([$id]); $msg = 'Sesi dihapus.'; break;
    case 'jradd':
        $r = jrow(); if ($r[0] === '') $msg = 'Nama jurnal wajib diisi.'; else { $rs = rows('journals'); $rs[] = $r; saveRows('journals', $rs, 9); $msg = 'Jurnal ditambahkan.'; } break;
    case 'jredit': case 'jrdel':
        $rs = rows('journals'); $i = (int)($_POST['i'] ?? -1);
        if (!isset($rs[$i])) break;
        if ($a === 'jredit') { $r = jrow(); if ($r[0] === '') $msg = 'Nama jurnal wajib diisi.'; else { $rs[$i] = $r; saveRows('journals', $rs, 9); $msg = 'Data jurnal diperbarui.'; } }
        else { array_splice($rs, $i, 1); saveRows('journals', $rs, 9); $msg = 'Jurnal dihapus.'; }
        break;
    case 'spadd':
        $n = clean(post('name'));
        if ($n === '') { $msg = 'Nama pembicara wajib diisi.'; break; }
        $rs = rows('speakers'); $ph = ''; $er = ''; if (!empty($_FILES['photo']['name'])) $ph = upImage($_FILES['photo'], $er, 'sp');
        $rs[] = [clean(post('role')), $n, clean(post('affil')), $ph]; saveRows('speakers', $rs, 4); $msg = 'Pembicara ditambahkan.' . ($er ? ' ' . $er : ''); break;
    case 'spedit': case 'spdel': case 'photo': case 'rmphoto': case 'spup': case 'spdown':
        $rs = rows('speakers'); $i = (int)($_POST['i'] ?? -1);
        if (!isset($rs[$i])) break;
        $rs[$i] = array_pad($rs[$i], 4, '');
        if ($a === 'spedit') { $n = clean(post('name')); if ($n === '') $msg = 'Nama wajib diisi.'; else { $rs[$i][0] = clean(post('role')); $rs[$i][1] = $n; $rs[$i][2] = clean(post('affil')); $msg = 'Data pembicara diperbarui.'; } }
        if ($a === 'spdel') { rmImage($rs[$i][3]); array_splice($rs, $i, 1); $msg = 'Pembicara dihapus.'; }
        if ($a === 'rmphoto') { rmImage($rs[$i][3]); $rs[$i][3] = ''; $msg = 'Foto dihapus.'; }
        if ($a === 'photo') { $er = ''; $fn = upImage($_FILES['photo'] ?? null, $er, 'sp'); if ($fn === '') $msg = $er; else { rmImage($rs[$i][3]); $rs[$i][3] = $fn; $msg = 'Foto diunggah.'; } }
        if ($a === 'spup' && $i > 0) [$rs[$i - 1], $rs[$i]] = [$rs[$i], $rs[$i - 1]];
        if ($a === 'spdown' && $i < count($rs) - 1) [$rs[$i + 1], $rs[$i]] = [$rs[$i], $rs[$i + 1]];
        saveRows('speakers', $rs, 4); break;
    case 'itemsave': case 'itemdel':
        $kind = post('kind'); if (!isset($KINDS[$kind])) break; $back = '?p=konten&k=' . $kind;
        $q = $pdo->prepare("SELECT * FROM items WHERE id=? AND kind=?"); $q->execute([$id, $kind]); $old = $q->fetch();
        if ($a === 'itemdel') { if ($old) { rmImage($old['image']); if (strpos((string)$old['url'], 'dl_') === 0) rmImage($old['url']); $pdo->prepare("DELETE FROM items WHERE id=?")->execute([$id]); } $msg = 'Item dihapus.'; break; }
        $v = ['sort' => (int)($_POST['sort'] ?? 0), 'title' => post('title'), 'subtitle' => post('subtitle'), 'body' => post('body'), 'url' => post('url'), 'image' => $old['image'] ?? '', 'extra' => post('extra')];
        if ($v['title'] === '') { $msg = 'Judul / nama wajib diisi.'; break; }
        if ($kind === 'date' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v['extra'])) { $msg = 'Tanggal wajib diisi.'; break; }
        if ($v['url'] !== '' && !isurl($v['url']) && strpos($v['url'], 'dl_') !== 0) $v['url'] = 'https://' . $v['url'];
        $er = '';
        if ($kind === 'sponsor' && !empty($_FILES['image']['name'])) { $fn = upImage($_FILES['image'], $er, 'logo'); if ($fn) { rmImage($v['image']); $v['image'] = $fn; } }
        if ($kind === 'sponsor' && post('image_url') !== '') { if (isurl(post('image_url'))) { rmImage($v['image']); $v['image'] = post('image_url'); } }
        if ($kind === 'download' && !empty($_FILES['file']['name'])) { $f = upFile($_FILES['file'], ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xlsx', 'zip'], $er, 'File', true); if ($f) { if ($old && strpos((string)$old['url'], 'dl_') === 0) rmImage($old['url']); $v['url'] = $f[0]; } }
        if ($kind === 'download' && $v['url'] === '') { $msg = 'Unggah file atau isi URL.'; break; }
        if ($old) $pdo->prepare("UPDATE items SET sort=?,title=?,subtitle=?,body=?,url=?,image=?,extra=? WHERE id=?")->execute(array_merge(array_values($v), [$id]));
        else $pdo->prepare("INSERT INTO items(sort,title,subtitle,body,url,image,extra,kind) VALUES(?,?,?,?,?,?,?,?)")->execute(array_merge(array_values($v), [$kind]));
        $msg = 'Tersimpan.' . ($er ? ' ' . $er : '');
        if ($kind === 'news' && !$old && !empty($_POST['mail'])) {
            [$n, $tot] = sendBatch(array_map(function ($r) use ($v) { return [$r['email'], '[' . brand() . '] ' . $v['title'], "Dear " . $r['name'] . ",\n\n" . $v['body'] . ($v['url'] ? "\n\n" . $v['url'] : '') . "\n\n--\n" . setting('name') . "\n" . absUrl()]; }, audienceRows('all')));
            $msg .= " Email terkirim ke $n dari $tot peserta.";
        }
        break;
    case 'mailtest':
        $back = '?p=setting#uji-email'; $to = strtolower(post('to')) ?: $u['email'];
        $ok = sendMail($to, '[' . brand() . '] Test email', "This is a test email from " . setting('name') . ".\n\nSent: " . date('Y-m-d H:i:s') . " (" . setting('timezone', 'Asia/Jakarta') . ")\nProvider: " . (resendOn() ? 'Resend API' : 'PHP mail()') . "\nFrom: " . mailFrom()[0] . "\n\nIf you received this, email notifications are working.\n" . absUrl(), $err);
        $msg = $ok ? "Email uji berhasil dikirim ke $to lewat " . (resendOn() ? 'Resend' : 'mail() hosting') . '. Periksa kotak masuk (dan folder spam).' : "Email uji GAGAL ke $to: $err";
        break;
    case 'bulk':
        $back = '?p=email'; $aud = post('aud'); $sub = post('subject'); $body = post('body');
        if (!isset($AUD[$aud]) || $sub === '' || $body === '') { $msg = 'Pilih penerima, isi subjek dan pesan.'; break; }
        [$n, $tot, $le] = sendBatch(array_map(function ($r) use ($sub, $body) { return [$r['email'], '[' . brand() . '] ' . $sub, strtr($body, ['{name}' => $r['name'], '{regno}' => $r['regno'], '{email}' => $r['email']]) . "\n\n--\n" . setting('name') . "\n" . absUrl()]; }, audienceRows($aud)));
        $msg = "Email terkirim: $n dari $tot penerima." . ($le ? " Error terakhir: $le" : ''); break;
    case 'set':
        foreach (['name', 'tagline', 'organizer', 'date', 'time', 'event_start', 'event_end', 'timezone', 'location', 'venue_info', 'venue_url', 'map_url', 'website', 'email', 'mail_from', 'logo_url', 'submission_url', 'about', 'cfp_info', 'fees', 'early_deadline', 'payment_info', 'invoice_note', 'supporters', 'benefits', 'contacts', 'secretariat', 'online_link', 'online_info', 'signers', 'abs_min', 'abs_max', 'reg_close_msg', 'turnstile_site', 'turnstile_secret', 'blocked_domains', 'mail_from_name'] as $k)
            if (isset($_POST[$k])) setSetting($k, trim((string)$_POST[$k]));
        foreach (['reg_open', 'cert_open', 'cert_checkin', 'blind_review'] as $k) setSetting($k, empty($_POST[$k]) ? '0' : '1');
        if (isset($_POST['mail_driver'])) setSetting('mail_driver', post('mail_driver') === 'resend' ? 'resend' : 'mail');
        if (!empty($_POST['resend_key_clear'])) setSetting('resend_key', '');
        elseif (post('resend_key') !== '') {
            if (!preg_match('/^re_[A-Za-z0-9_]{10,}$/', post('resend_key'))) $msg = 'API key Resend tidak valid (harus diawali "re_"). Key tidak disimpan; pengaturan lain tersimpan.';
            else setSetting('resend_key', post('resend_key'));
        }
        if (setting('mail_driver') === 'resend' && setting('resend_key') === '') $msg = ($msg ?? 'Pengaturan disimpan.') . ' Peringatan: Resend dipilih tetapi API key belum diisi, email tetap dikirim lewat mail() hosting.';
        if (!in_array(setting('timezone'), timezone_identifiers_list(), true)) setSetting('timezone', 'Asia/Jakarta');
        if (!empty($_FILES['logo']['name'])) { $er = ''; $fn = upImage($_FILES['logo'], $er, 'logo'); if ($fn) { rmImage(setting('logo_url')); setSetting('logo_url', $fn); } else $msg = $er; }
        $msg = $msg ?? 'Pengaturan disimpan.'; break;
    }
    flash($msg ?? 'Perubahan disimpan.'); go($back ?? '?p=' . $p . (isset($_GET['k']) ? '&k=' . urlencode($_GET['k']) : ''));
}

$TABS = ['ringkasan' => 'Ringkasan', 'peserta' => 'Peserta', 'naskah' => 'Naskah & Review', 'pengguna' => 'Akun', 'pembicara' => 'Pembicara', 'konten' => 'Konten', 'agenda' => 'Agenda', 'jurnal' => 'Jurnal', 'email' => 'Email', 'setting' => 'Pengaturan'];
head('Admin'); ?>
<h2>Panel Admin</h2><div class="tabs"><?php foreach ($TABS as $k => $l): ?><a href="?p=<?= $k ?>" class="<?= $p === $k || ($k === 'peserta' && $p === 'detail') || ($k === 'pengguna' && $p === 'edit') ? 'on' : '' ?>"><?= e($l) ?></a><?php endforeach; ?><a href="../checkin.php">Check-in</a></div>
<?php
$view = __DIR__ . '/views/' . $p . '.php';
if (is_file($view)) include $view; else echo '<p>Halaman tidak ditemukan.</p>';
foot();
