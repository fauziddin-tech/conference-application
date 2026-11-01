<?php
/* Instalasi awal: membuat tabel, data contoh, akun admin, dan config.php. */
$root = dirname(__DIR__); $err = ''; $done = false;
if (file_exists("$root/config.php") || file_exists(__DIR__ . '/install.lock')) die('Sudah terinstal. Hapus folder /install demi keamanan.');
require $root . '/lib/schema.php';

$SP = "Open Speech|Prof. Brian Yuliarto, Ph.D|Diktisaintek
Open Speech|Prof. Dr. Amir Luthfi|Rektor Universitas Pahlawan Tuanku Tambusai
Keynote Speaker|Prof. Syamsul Bahri, M.Sc. Ph.D|North-West University, Potchefstroom
Keynote Speaker|Prof. Hansde Ridder|North-West University, Potchefstroom
Keynote Speaker|Dr. Nur Ehsan Mohd Said|Universiti Kebangsaan Malaysia
Keynote Speaker|Dr. Oksana Afitska|Manchester University
Keynote Speaker|Dr. Musnar Indra Daulay, M.Pd|Universitas Pahlawan Tuanku Tambusai
Chairman of the Committee|Dr. Rusdial Marta, M.Pd|Universitas Pahlawan Tuanku Tambusai";
$FE = "Offline Participant|IDR 800.000|US$ 45
Online Participant|IDR 100.000|US$ 6
Offline Presenter|IDR 1.500.000|US$ 85
Offline Student Presenter|IDR 1.300.000|US$ 74";
$BE = "E-Certificate
Meals, Seminar Kits
National Journal Indonesia Accredited SINTA (S2, S3, S4, S5)";
$CO = "Rusdial Marta|+6281266432727
Kasman Ediputra|+62822 8800 9315
Yenny Fitra Surya|+62821 6902 9303";
$SE = "Sekretariat: Fakultas Keguruan dan Ilmu Pendidikan, Universitas Pahlawan Tuanku Tambusai, Jalan Tuanku Tambusai No. 23, Bangkinang, Riau, Indonesia";
$AB = "Konferensi internasional terbuka untuk umum yang diselenggarakan oleh Fakultas Keguruan dan Ilmu Pendidikan (FKIP), Universitas Pahlawan Tuanku Tambusai, dengan topik yang tidak terbatas pada bidang pendidikan.\n\nTujuan kegiatan:\na) Menjadi wadah internasional bagi peneliti dan akademisi untuk berbagi informasi dan gagasan baru tentang praktik pendidikan yang baik di tengah perkembangan teknologi.\nb) Membuka kolaborasi dengan akademisi, peneliti, dan anggota asosiasi pendidikan secara internasional.\nc) Membuka peluang baru kolaborasi riset pendidikan yang kompetitif secara internasional.";

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $f = function ($k) { return trim($_POST[$k] ?? ''); };
    try {
        if (!filter_var($f('a_email'), FILTER_VALIDATE_EMAIL)) throw new Exception('Email admin tidak valid.');
        if (strlen($_POST['a_pass'] ?? '') < 8) throw new Exception('Password admin minimal 8 karakter.');
        $start = $f('s_start');
        if ($start !== '' && !strtotime($start)) throw new Exception('Tanggal mulai acara tidak valid.');
        $pdo = new PDO("mysql:host=" . $f('db_host') . ";dbname=" . $f('db_name') . ";charset=utf8mb4", $f('db_user'), $_POST['db_pass'] ?? '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        migrate($pdo);
        $st = $pdo->prepare("REPLACE INTO settings(k,v) VALUES(?,?)");
        foreach ([
            'name' => $f('s_name'), 'tagline' => $f('s_tagline'), 'date' => $f('s_date'), 'location' => $f('s_loc'),
            'email' => $f('s_email'), 'about' => $AB, 'time' => '08.00 - 18.00 WIB', 'website' => 'www.picoe.org',
            'organizer' => 'Fakultas Keguruan dan Ilmu Pendidikan, Universitas Pahlawan Tuanku Tambusai',
            'event_start' => $start ? date('Y-m-d\TH:i', strtotime($start)) : '', 'mail_from' => '',
            'speakers' => $SP, 'fees' => $FE, 'benefits' => $BE, 'contacts' => $CO, 'secretariat' => $SE,
            'cfp_info' => "Authors are invited to submit original, unpublished research papers. Presenters submit the abstract (50-250 words) and full paper through the registration form. All papers are peer reviewed by the scientific committee.",
            'signers' => "Dr. Rusdial Marta, M.Pd|Chairman of the Committee",
        ] as $k => $v) $st->execute([$k, $v]);
        // Data contoh yang bisa diubah/hapus dari panel admin
        $it = $pdo->prepare("INSERT INTO items(kind,sort,title,subtitle,body,extra) VALUES(?,?,?,?,?,?)");
        $base = $start ? strtotime($start) : strtotime('+60 days');
        foreach ([['Abstract & full paper submission deadline', -45], ['Notification of acceptance', -30], ['Early bird registration deadline', -25], ['Final registration & payment deadline', -14], ['Conference day', 0]] as $i => $d)
            $it->execute(['date', $i, $d[0], '', '', date('Y-m-d', $base + $d[1] * 86400)]);
        foreach (['Curriculum, Instruction & Assessment', 'Artificial Intelligence in Education', 'Digital Learning & Educational Technology', 'Language & Literature Education', 'Teacher Education & Professional Development', 'Inclusive & Special Needs Education'] as $i => $t)
            $it->execute(['topic', $i, $t, '', '', '']);
        foreach ([['Can I attend online?', 'Yes. Choose an online category on the registration form. The meeting link is shown in your dashboard after your payment is verified.'], ['When will I receive my certificate?', 'E-certificates can be downloaded from your dashboard after the event once the committee opens the certificate download.'], ['Can I request an invitation letter for visa purposes?', 'Yes. International participants can download an official invitation letter from the dashboard after payment verification.']] as $i => $q)
            $it->execute(['faq', $i, $q[0], '', $q[1], '']);
        $pdo->prepare("INSERT INTO users(name,email,password,role,status) VALUES(?,?,?,'admin','confirmed')")->execute([$f('a_name'), strtolower($f('a_email')), password_hash($_POST['a_pass'], PASSWORD_DEFAULT)]);
        $base = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/') . '/';
        $cfg = "<?php\ndefine('DB_HOST'," . var_export($f('db_host'), true) . ");\ndefine('DB_NAME'," . var_export($f('db_name'), true) . ");\ndefine('DB_USER'," . var_export($f('db_user'), true) . ");\ndefine('DB_PASS'," . var_export($_POST['db_pass'] ?? '', true) . ");\ndefine('BASE'," . var_export($base, true) . ");\n";
        if (file_put_contents("$root/config.php", $cfg) === false) throw new Exception('Gagal menulis config.php (cek izin folder).');
        @file_put_contents(__DIR__ . '/install.lock', '1'); $done = true;
    } catch (Exception $x) { $err = $x->getMessage(); }
}
?><!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Instalasi</title><link rel="stylesheet" href="../style.css"></head><body><main>
<h2>Instalasi Website Konferensi</h2>
<?php if ($done): ?><p class="msg">Instalasi berhasil! <b>Hapus folder /install</b> dari server, lalu <a href="../login.php">masuk sebagai admin</a> untuk melengkapi pengaturan.</p>
<?php else: ?><?php if ($err): ?><p class="msg err"><?= e($err) ?></p><?php endif; ?>
<form method="post" class="card"><h3>Database MySQL (dari cPanel)</h3>
<label>Host<input name="db_host" value="<?= e($_POST['db_host'] ?? 'localhost') ?>" required></label>
<label>Nama Database<input name="db_name" value="<?= e($_POST['db_name'] ?? '') ?>" required></label>
<label>User Database<input name="db_user" value="<?= e($_POST['db_user'] ?? '') ?>" required></label>
<label>Password Database<input type="password" name="db_pass"></label>
<h3>Konferensi</h3>
<label>Nama Konferensi<input name="s_name" value="<?= e($_POST['s_name'] ?? '3rd Pahlawan International Conference on Education (PICOE)') ?>" required></label>
<label>Tagline / Tema<input name="s_tagline" value="<?= e($_POST['s_tagline'] ?? 'The New Face of Education: Reimagining Learning in the Era of Artificial Intelligence and Digital Transformation') ?>"></label>
<label>Tanggal (teks tampilan)<input name="s_date" value="<?= e($_POST['s_date'] ?? 'Kamis, 3 Desember 2026') ?>"></label>
<label>Mulai acara (untuk hitung mundur &amp; kalender)<input type="datetime-local" name="s_start" value="<?= e($_POST['s_start'] ?? '2026-12-03T08:00') ?>"></label>
<label>Lokasi<input name="s_loc" value="<?= e($_POST['s_loc'] ?? 'Hotel Pangeran Pekanbaru') ?>"></label>
<label>Email resmi panitia<input type="email" name="s_email" value="<?= e($_POST['s_email'] ?? 'picoe@universitaspahlawan.ac.id') ?>"></label>
<h3>Akun Admin</h3>
<label>Nama<input name="a_name" value="<?= e($_POST['a_name'] ?? '') ?>" required></label>
<label>Email<input type="email" name="a_email" value="<?= e($_POST['a_email'] ?? '') ?>" required></label>
<label>Password (min. 8 karakter)<input type="password" name="a_pass" required minlength="8"></label>
<button class="btn">Install</button></form><?php endif; ?></main></body></html>
