<?php
/*
 * Skema database & migrasi otomatis.
 * Dipakai oleh install/ (instalasi baru) dan inc.php (upgrade otomatis instalasi lama).
 * Setiap perubahan skema: naikkan DB_VERSION dan tambahkan langkahnya di migrate().
 */
const DB_VERSION = 3;

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
    $pdo->exec("ALTER TABLE users MODIFY role ENUM('admin','peserta','reviewer') DEFAULT 'peserta'");
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
        'blocked_domains' => 'fextemp.com mailinator.com guerrillamail.com guerrillamail.info sharklasers.com grr.la 10minutemail.com 10minutemail.net temp-mail.org temp-mail.io tempmail.com tempmail.net tempmailo.com tempail.com yopmail.com yopmail.net trashmail.com trashmail.de getnada.com nada.email dispostable.com maildrop.cc throwawaymail.com emailondeck.com mohmal.com fakeinbox.com mintemail.com mytemp.email tmpmail.org tmpmail.net mail.tm 1secmail.com 1secmail.org 1secmail.net mailnesia.com spamgourmet.com mailcatch.com moakt.com emailfake.com fakemail.net burnermail.io inboxkitten.com mailpoof.com tempr.email discard.email spambox.us',
    ];
    $ins = $pdo->prepare("INSERT IGNORE INTO settings(k,v) VALUES(?,?)");
    foreach ($def as $k => $v) $ins->execute([$k, $v]);
    $pdo->prepare("REPLACE INTO settings(k,v) VALUES('db_version',?)")->execute([(string)DB_VERSION]);
}
