<?php
/**
 * Backup database untuk Cron Jobs cPanel (disarankan setiap malam):
 *   php /home/USER/public_html/tools/backup-database.php
 * Hasil: <folder backup>/konferensi-YYYYmmdd-HHMMSS.sql.gz (struktur + data semua tabel).
 * Folder bawaan di luar public_html (~/backup-konferensi); ubah lewat konstanta BACKUP_DIR di config.php.
 * Jumlah hari penyimpanan diatur di Admin → Sistem (bawaan 14 hari). Hanya bisa dijalankan dari command line.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Hanya dapat dijalankan dari command line.'); }
$root = dirname(__DIR__);
if (!is_file($root . '/config.php')) { fwrite(STDERR, "config.php tidak ditemukan; jalankan instalasi terlebih dahulu.\n"); exit(1); }
require $root . '/config.php';
require $root . '/lib/backup.php';
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $set = $pdo->query("SELECT k,v FROM settings WHERE k IN('backup_keep','timezone')")->fetchAll(PDO::FETCH_KEY_PAIR);
    @date_default_timezone_set($set['timezone'] ?? 'Asia/Jakarta');
    $s = backupRun($pdo, 'cron', (int)($set['backup_keep'] ?? 14));
    echo 'Backup berhasil: ' . $s['dir'] . '/' . $s['file'] . ' (' . round($s['size'] / 1024, 1) . ' KB, ' . $s['tables'] . ' tabel, ' . $s['rows'] . ' baris). Tersimpan ' . $s['count'] . " backup.\n";
    exit(0);
} catch (Throwable $x) {
    fwrite(STDERR, 'Backup gagal: ' . $x->getMessage() . "\n");
    exit(1);
}
