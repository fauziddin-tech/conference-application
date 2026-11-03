<?php
/*
 * Skema database & migrasi otomatis.
 * Dipakai oleh install/ (instalasi baru) dan inc.php (upgrade otomatis instalasi lama).
 * Setiap perubahan skema: naikkan DB_VERSION dan tambahkan langkahnya di migrate().
 */
const DB_VERSION = 11;

function colExists(PDO $pdo, $t, $c)
{
    $s = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
    $s->execute([$t, $c]);
    return (bool)$s->fetchColumn();
}

function addCol(PDO $pdo, $t, $c, $def)
{
    if (!colExists($pdo, $t, $c)) $pdo->exec("ALTER TABLE `$t` ADD COLUMN `$c` $def");
}

function newTicket()
{
    return strtoupper(bin2hex(random_bytes(6)));
}

function migrate(PDO $pdo)
{
    $E = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings(k VARCHAR(50) PRIMARY KEY,v TEXT)$E");
    $pdo->exec("CREATE TABLE IF NOT EXISTS users(id INT AUTO_INCREMENT PRIMARY KEY,category VARCHAR(100),name VARCHAR(150) NOT NULL,email VARCHAR(150) NOT NULL UNIQUE,institution VARCHAR(150),phone VARCHAR(30),password VARCHAR(255) NOT NULL,role ENUM('admin','peserta','reviewer') DEFAULT 'peserta',status ENUM('pending','confirmed','rejected') DEFAULT 'pending',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)$E");
    $pdo->exec("CREATE TABLE IF NOT EXISTS agenda(id INT AUTO_INCREMENT PRIMARY KEY,day VARCHAR(50),time VARCHAR(50),title VARCHAR(255),speaker VARCHAR(150),room VARCHAR(100))$E");
    $pdo->exec("CREATE TABLE IF NOT EXISTS registrations(id INT AUTO_INCREMENT PRIMARY KEY,user_id INT NOT NULL UNIQUE,prefix VARCHAR(20),full_name VARCHAR(150),country VARCHAR(100),orcid VARCHAR(40),phone VARCHAR(30),category VARCHAR(100),attendance VARCHAR(10),article_title VARCHAR(300),coauthors TEXT,abstract TEXT,keywords VARCHAR(300),article_file VARCHAR(100),article_name VARCHAR(200),pay_amount VARCHAR(60),pay_date DATE NULL,pay_sender VARCHAR(150),pay_file VARCHAR(100),pay_name VARCHAR(200),dietary VARCHAR(150),paper_status ENUM('submitted','under_review','revision','accepted','rejected') DEFAULT 'submitted',pay_status ENUM('unpaid','pending','verified','rejected') DEFAULT 'pending',admin_note TEXT,agreed TINYINT DEFAULT 0,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)$E");

    // --- v2: kolom baru pada tabel lama ---
    $pdo->exec("ALTER TABLE users MODIFY role ENUM('admin','peserta','reviewer','bendahara','jurnal') DEFAULT 'peserta'");
    addCol($pdo, 'users', 'reset_hash', 'VARCHAR(64) NULL');
    addCol($pdo, 'users', 'reset_exp', 'INT NULL');
    addCol($pdo, 'users', 'last_login', 'DATETIME NULL');
    addCol($pdo, 'agenda', 'track', 'VARCHAR(100) NULL');
    addCol($pdo, 'agenda', 'description', 'TEXT NULL');
    $pdo->exec("ALTER TABLE registrations MODIFY pay_status ENUM('unpaid','pending','verified','rejected') DEFAULT 'pending'");
    addCol($pdo, 'registrations', 'track', 'VARCHAR(150) NULL');
    addCol($pdo, 'registrations', 'ticket', 'VARCHAR(24) NULL');
    addCol($pdo, 'registrations', 'checked_in_at', 'DATETIME NULL');
    addCol($pdo, 'registrations', 'camera_file', 'VARCHAR(100) NULL');
    addCol($pdo, 'registrations', 'camera_name', 'VARCHAR(200) NULL');
    $ix = $pdo->query("SHOW INDEX FROM registrations WHERE Key_name='ticket'")->fetch();
    if (!$ix) $pdo->exec("ALTER TABLE registrations ADD UNIQUE KEY ticket(ticket)");
    $up = $pdo->prepare("UPDATE registrations SET ticket=? WHERE id=?");
    foreach ($pdo->query("SELECT id FROM registrations WHERE ticket IS NULL OR ticket=''")->fetchAll(PDO::FETCH_COLUMN) as $id) $up->execute([newTicket(), $id]);

    // --- v2: tabel baru ---
    $pdo->exec("CREATE TABLE IF NOT EXISTS reviews(id INT AUTO_INCREMENT PRIMARY KEY,reg_id INT NOT NULL,reviewer_id INT NOT NULL,score TINYINT NULL,recommendation VARCHAR(20) NULL,to_author TEXT,to_committee TEXT,submitted_at DATETIME NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY rr(reg_id,reviewer_id))$E");
    // Konten fleksibel: tanggal penting, topik, komite, sponsor, FAQ, pengumuman, unduhan
    $pdo->exec("CREATE TABLE IF NOT EXISTS items(id INT AUTO_INCREMENT PRIMARY KEY,kind VARCHAR(20) NOT NULL,sort INT DEFAULT 0,title VARCHAR(300),subtitle VARCHAR(300),body TEXT,url VARCHAR(500),image VARCHAR(500),extra VARCHAR(150),created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,KEY kind(kind,sort))$E");
    $pdo->exec("CREATE TABLE IF NOT EXISTS throttle(id INT AUTO_INCREMENT PRIMARY KEY,k VARCHAR(120) NOT NULL,ts INT NOT NULL,KEY k(k,ts))$E");
    $pdo->exec("CREATE TABLE IF NOT EXISTS mail_log(id INT AUTO_INCREMENT PRIMARY KEY,to_addr VARCHAR(150),subject VARCHAR(255),ok TINYINT,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)$E");
    // --- v4: pengiriman email via Resend ---
    addCol($pdo, 'mail_log', 'error', 'VARCHAR(255) NULL');
    addCol($pdo, 'mail_log', 'provider', 'VARCHAR(10) NULL');

    // --- v5: alur naskah & review berversi (ala MyThesis) ---
    $pdo->exec("CREATE TABLE IF NOT EXISTS paper_files(id INT AUTO_INCREMENT PRIMARY KEY,reg_id INT NOT NULL,round INT NOT NULL DEFAULT 1,version INT NOT NULL DEFAULT 1,kind VARCHAR(12) NOT NULL DEFAULT 'submission',file VARCHAR(100) NOT NULL,name VARCHAR(200),note TEXT,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,KEY reg(reg_id,version))$E");
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications(id INT AUTO_INCREMENT PRIMARY KEY,user_id INT NOT NULL,msg VARCHAR(300) NOT NULL,link VARCHAR(255),is_read TINYINT DEFAULT 0,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,KEY u(user_id,is_read))$E");
    addCol($pdo, 'registrations', 'review_round', 'INT NOT NULL DEFAULT 1');
    addCol($pdo, 'reviews', 'round', 'INT NOT NULL DEFAULT 1');
    addCol($pdo, 'reviews', 'file', 'VARCHAR(100) NULL');
    addCol($pdo, 'reviews', 'file_name', 'VARCHAR(200) NULL');
    addCol($pdo, 'reviews', 'due_date', 'DATE NULL');
    if ($pdo->query("SHOW INDEX FROM reviews WHERE Key_name='rr'")->fetch()) $pdo->exec("ALTER TABLE reviews DROP INDEX rr");
    if (!$pdo->query("SHOW INDEX FROM reviews WHERE Key_name='rrr'")->fetch()) $pdo->exec("ALTER TABLE reviews ADD UNIQUE KEY rrr(reg_id,reviewer_id,round)");
    // naskah yang sudah ada menjadi versi 1 (dan camera-ready bila ada)
    $pf = $pdo->prepare("INSERT INTO paper_files(reg_id,round,version,kind,file,name) VALUES(?,?,?,?,?,?)");
    foreach ($pdo->query("SELECT r.id,r.article_file,r.article_name,r.camera_file,r.camera_name FROM registrations r WHERE r.article_file<>'' AND r.article_file IS NOT NULL AND NOT EXISTS(SELECT 1 FROM paper_files p WHERE p.reg_id=r.id)")->fetchAll() as $r) {
        $pf->execute([$r['id'], 1, 1, 'submission', $r['article_file'], $r['article_name']]);
        if ($r['camera_file']) $pf->execute([$r['id'], 1, 1, 'camera', $r['camera_file'], $r['camera_name']]);
    }

    // --- v6: kode verifikasi sertifikat reviewer ---
    addCol($pdo, 'users', 'cert_code', 'VARCHAR(24) NULL');
    if (!$pdo->query("SHOW INDEX FROM users WHERE Key_name='cert_code'")->fetch()) $pdo->exec("ALTER TABLE users ADD UNIQUE KEY cert_code(cert_code)");

    // --- v7: cache terjemahan konten (EN/ID) ---
    addCol($pdo, 'notifications', 'msg_en', 'VARCHAR(300) NULL');
    $pdo->exec("CREATE TABLE IF NOT EXISTS translations(h CHAR(32) NOT NULL,lang CHAR(2) NOT NULL,src MEDIUMTEXT,txt MEDIUMTEXT,src_lang VARCHAR(5),manual TINYINT DEFAULT 0,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(h,lang))$E");

    // --- v8: versi sesi (keluarkan sesi lain saat password diganti) ---
    addCol($pdo, 'users', 'sess_ver', 'INT NOT NULL DEFAULT 0');

    // --- v10: keuangan (angka pembayaran, buku kas, peran bendahara) ---
    // v11: + peran koordinator jurnal ('jurnal')
    $pdo->exec("ALTER TABLE users MODIFY role ENUM('admin','peserta','reviewer','bendahara','jurnal') DEFAULT 'peserta'");
    addCol($pdo, 'registrations', 'pay_currency', 'CHAR(3) NULL');
    addCol($pdo, 'registrations', 'pay_received', 'DECIMAL(14,2) NULL');
    addCol($pdo, 'registrations', 'pay_verified_at', 'DATETIME NULL');
    addCol($pdo, 'registrations', 'pay_verified_by', 'INT NULL');
    $pdo->exec("CREATE TABLE IF NOT EXISTS cash(id INT AUTO_INCREMENT PRIMARY KEY,entry_date DATE NOT NULL,type ENUM('in','out') NOT NULL,category VARCHAR(100),description VARCHAR(500),amount DECIMAL(14,2) NOT NULL DEFAULT 0,currency CHAR(3) NOT NULL DEFAULT 'IDR',method VARCHAR(30),receipt_file VARCHAR(100),receipt_name VARCHAR(200),created_by INT,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,KEY d(entry_date))$E");
    // pembayaran yang sudah terverifikasi: isi mata uang & jumlah dari teks biaya
    if (function_exists('feeParse')) {
        $up = $pdo->prepare("UPDATE registrations SET pay_currency=?,pay_received=?,pay_verified_at=COALESCE(pay_verified_at,updated_at) WHERE id=?");
        foreach ($pdo->query("SELECT * FROM registrations WHERE pay_status='verified' AND pay_received IS NULL")->fetchAll() as $r) $up->execute([regCurrency($r), regExpected($r), $r['id']]);
    }

    // Pengaturan baru dengan nilai bawaan (tidak menimpa yang sudah ada)
    $def = [
        'venue_info' => 'Hotel Pangeran Pekanbaru adalah hotel keluarga dan bisnis yang menawarkan layanan terbaik untuk kenyamanan setiap tamu.',
        'venue_url' => 'https://pekanbaru.hotelpangeran.com',
        'reg_open' => '1', 'reg_close_msg' => 'Pendaftaran sudah ditutup. Hubungi panitia untuk informasi lebih lanjut.',
        'early_deadline' => '', 'event_start' => '', 'event_end' => '', 'timezone' => 'Asia/Jakarta',
        'mail_from' => '', 'online_link' => '', 'online_info' => '',
        'cfp_info' => '', 'cert_open' => '0', 'cert_checkin' => '1',
        'signers' => '', 'invoice_note' => '', 'organizer' => '', 'logo_url' => '',
        'blind_review' => '1', 'abs_min' => '50', 'abs_max' => '250',
        // v3: anti-spam
        'turnstile_site' => '', 'turnstile_secret' => '',
        // v4: email
        'mail_driver' => 'mail', 'resend_key' => '', 'mail_from_name' => '',
        // v5: naskah
        'paper_word_only' => '0', 'review_days' => '14',
        // v6
        'cert_reviewer' => '1',
        // v7
        'auto_translate' => '1',
        // v8
        'site_url' => '', 'housekeep_at' => '',
        // v9: bersihkan .docx saat diunggah
        'docx_strip' => '1',
        // v10: keuangan
        'usd_rate' => '', 'fin_cat_out' => "Konsumsi\nAkomodasi & Hotel\nHonorarium Pembicara\nSewa Tempat & Peralatan\nSeminar Kit & Cetak\nSertifikat & Plakat\nTransportasi\nPublikasi & Promosi\nBiaya Bank & Transfer\nLain-lain",
        'fin_cat_in' => "Sponsor\nBantuan Institusi\nDonasi\nLain-lain",
        'blocked_domains' => 'fextemp.com mailinator.com guerrillamail.com guerrillamail.info sharklasers.com grr.la 10minutemail.com 10minutemail.net temp-mail.org temp-mail.io tempmail.com tempmail.net tempmailo.com tempail.com yopmail.com yopmail.net trashmail.com trashmail.de getnada.com nada.email dispostable.com maildrop.cc throwawaymail.com emailondeck.com mohmal.com fakeinbox.com mintemail.com mytemp.email tmpmail.org tmpmail.net mail.tm 1secmail.com 1secmail.org 1secmail.net mailnesia.com spamgourmet.com mailcatch.com moakt.com emailfake.com fakemail.net burnermail.io inboxkitten.com mailpoof.com tempr.email discard.email spambox.us',
    ];
    $ins = $pdo->prepare("INSERT IGNORE INTO settings(k,v) VALUES(?,?)");
    foreach ($def as $k => $v) $ins->execute([$k, $v]);
    $pdo->prepare("REPLACE INTO settings(k,v) VALUES('db_version',?)")->execute([(string)DB_VERSION]);
}
