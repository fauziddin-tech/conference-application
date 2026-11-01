<?php
/*
 * Backup database (PHP murni, tanpa mysqldump) — diadaptasi dari MyThesis. Dipakai oleh:
 *  - tools/backup-database.php  (Cron Jobs cPanel, disarankan setiap malam)
 *  - panel admin → Sistem        (tombol "Buat Backup Sekarang", unduh, hapus)
 *  - backup otomatis harian saat admin membuka panel (bila diaktifkan; cadangan jika Cron belum diatur)
 * File: <folder backup>/konferensi-YYYYmmdd-HHMMSS.sql.gz. Folder bawaan berada DI LUAR public_html
 * (mis. /home/USER/backup-konferensi); ubah lewat konstanta BACKUP_DIR di config.php bila perlu.
 */
const BACKUP_FILE_PATTERN = '/^konferensi-\d{8}-\d{6}\.sql\.gz$/';

function backupRoot() { return dirname(__DIR__); }
function backupDir()
{
    if (defined('BACKUP_DIR')) return rtrim((string)BACKUP_DIR, '/');
    $root = backupRoot();
    // Simpan di luar public_html agar tidak bisa diunduh dari web.
    $p = strpos($root, '/public_html');
    return ($p !== false ? substr($root, 0, $p) : dirname($root)) . '/backup-konferensi';
}
function backupStatusFile() { return backupRoot() . '/uploads/private/backup-status.json'; }
function backupStatus()
{
    $f = backupStatusFile();
    $s = is_file($f) ? json_decode((string)file_get_contents($f), true) : null;
    return is_array($s) ? $s : null;
}
/** Daftar backup, terbaru lebih dulu: [['name','size','time'], ...]. */
function backupList()
{
    $o = [];
    foreach (glob(backupDir() . '/konferensi-*.sql.gz') ?: [] as $p) {
        $n = basename($p);
        if (preg_match(BACKUP_FILE_PATTERN, $n) && is_file($p)) $o[] = ['name' => $n, 'size' => (int)filesize($p), 'time' => (int)filemtime($p)];
    }
    usort($o, function ($a, $b) { return $b['time'] <=> $a['time'] ?: strcmp($b['name'], $a['name']); });
    return $o;
}
/** Path absolut backup yang sah (nama divalidasi agar tidak keluar dari folder backup), atau null. */
function backupPath($name)
{
    if (!preg_match(BACKUP_FILE_PATTERN, (string)$name)) return null;
    $dir = realpath(backupDir()); $p = $dir ? realpath($dir . '/' . $name) : false;
    return ($p && is_file($p) && dirname($p) === $dir) ? $p : null;
}
/** Folder backup berada di dalam folder situs (bisa diakses web)? */
function backupInsideWeb()
{
    $d = realpath(backupDir()) ?: backupDir(); $r = realpath(backupRoot()) ?: backupRoot();
    return strpos($d . '/', $r . '/') === 0 || strpos($d, '/public_html/') !== false;
}

/**
 * Backup lengkap (struktur + data semua tabel), hapus backup lebih lama dari $keepDays hari,
 * tulis status untuk halaman Sistem. Mengembalikan status; melempar RuntimeException bila gagal.
 */
function backupRun(PDO $pdo, $trigger = 'cron', $keepDays = 14)
{
    $dir = backupDir(); $gz = null; $partial = '';
    @set_time_limit(300);
    try {
        if (!function_exists('gzopen')) throw new RuntimeException('Ekstensi PHP zlib tidak aktif.');
        if (!is_dir($dir) && !@mkdir($dir, 0700, true)) throw new RuntimeException('Folder backup tidak dapat dibuat: ' . $dir . '. Buat foldernya lewat File Manager atau atur BACKUP_DIR di config.php.');
        // pertahanan tambahan bila folder ternyata di dalam folder web
        if (!is_file($dir . '/.htaccess')) @file_put_contents($dir . '/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
        if (!is_file($dir . '/index.html')) @file_put_contents($dir . '/index.html', '');
        $name = 'konferensi-' . date('Ymd-His') . '.sql.gz'; $target = $dir . '/' . $name;
        if (is_file($target)) throw new RuntimeException('Backup dengan waktu yang sama sudah ada. Coba lagi sebentar.');
        $partial = $target . '.part';
        $gz = @gzopen($partial, 'wb6');
        if (!$gz) throw new RuntimeException('File backup tidak dapat ditulis di ' . $dir . ' (periksa permission folder).');
        $w = function ($t) use ($gz) { if (gzwrite($gz, $t) === false) throw new RuntimeException('Gagal menulis file backup.'); };
        $db = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
        $w("-- Backup aplikasi konferensi " . date('c') . "\n-- Database: $db\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
        $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
        $rows = 0;
        foreach ($tables as $t) {
            $q = '`' . str_replace('`', '``', $t) . '`';
            $create = $pdo->query("SHOW CREATE TABLE $q")->fetch(PDO::FETCH_NUM)[1];
            $w("DROP TABLE IF EXISTS $q;\n$create;\n\n");
            $st = $pdo->query("SELECT * FROM $q", PDO::FETCH_NUM); $batch = [];
            while ($r = $st->fetch(PDO::FETCH_NUM)) {
                $batch[] = '(' . implode(',', array_map(function ($v) use ($pdo) { return $v === null ? 'NULL' : $pdo->quote((string)$v); }, $r)) . ')';
                $rows++;
                if (count($batch) >= 200) { $w("INSERT INTO $q VALUES\n" . implode(",\n", $batch) . ";\n"); $batch = []; }
            }
            $st->closeCursor();
            if ($batch) $w("INSERT INTO $q VALUES\n" . implode(",\n", $batch) . ";\n");
            $w("\n");
        }
        $w("SET FOREIGN_KEY_CHECKS=1;\n-- Selesai\n");
        gzclose($gz); $gz = null;
        if (!rename($partial, $target)) throw new RuntimeException('File backup tidak dapat diselesaikan.');
        @chmod($target, 0600);
        $removed = 0; $keep = max(1, (int)$keepDays);
        foreach (backupList() as $b) if ($b['name'] !== $name && $b['time'] < time() - $keep * 86400 && @unlink($dir . '/' . $b['name'])) $removed++;
        $s = ['ok' => true, 'time' => date('c'), 'file' => $name, 'dir' => $dir, 'size' => filesize($target), 'tables' => count($tables), 'rows' => $rows, 'count' => count(backupList()), 'removed' => $removed, 'trigger' => $trigger];
        @file_put_contents(backupStatusFile(), json_encode($s, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        return $s;
    } catch (Throwable $x) {
        if ($gz) gzclose($gz);
        if ($partial !== '' && is_file($partial)) @unlink($partial);
        @file_put_contents(backupStatusFile(), json_encode(['ok' => false, 'time' => date('c'), 'msg' => $x->getMessage(), 'dir' => $dir, 'trigger' => $trigger], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        error_log('Backup konferensi gagal: ' . $x->getMessage());
        throw $x instanceof RuntimeException ? $x : new RuntimeException($x->getMessage());
    }
}
/** Backup otomatis harian tanpa Cron: dijalankan saat admin membuka panel bila backup sukses terakhir > 24 jam. */
function backupAutoIfDue(PDO $pdo)
{
    if (setting('backup_auto', '1') !== '1') return null;
    $s = backupStatus(); $last = 0;
    if ($s) $last = (int)strtotime((string)$s['time']);
    if ($s && empty($s['ok']) && $last > time() - 3600) return null; // gagal < 1 jam lalu: jangan coba terus-menerus
    if ($s && !empty($s['ok']) && $last > time() - 86400) return null;
    try { return backupRun($pdo, 'otomatis', (int)setting('backup_keep', '14')); } catch (Throwable $x) { return ['ok' => false, 'msg' => $x->getMessage()]; }
}
