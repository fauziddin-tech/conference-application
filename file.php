<?php require 'inc.php'; $u = need();
/*
 * Unduhan file privat dengan pemeriksaan hak akses:
 *  ?id=&k=article|camera|pay  berkas aktif pendaftaran
 *  ?pf=<id>                   versi naskah tertentu (riwayat)
 *  ?rv=<id>                   file berkomentar dari reviewer
 */
$admin = $u['role'] === 'admin';
$isReviewer = function ($rid) use ($pdo, $u) { $q = $pdo->prepare("SELECT 1 FROM reviews WHERE reg_id=? AND reviewer_id=?"); $q->execute([$rid, $u['id']]); return (bool)$q->fetch(); };
$deny = function () { http_response_code(403); die('Akses ditolak'); };
$fn = ''; $dl = '';
if (isset($_GET['pf'])) {
    $q = $pdo->prepare("SELECT p.*,r.user_id FROM paper_files p JOIN registrations r ON r.id=p.reg_id WHERE p.id=?"); $q->execute([(int)$_GET['pf']]); $f = $q->fetch();
    if (!$f) $deny();
    $rev = $u['role'] === 'reviewer' && $isReviewer($f['reg_id']);
    if (!$admin && (int)$f['user_id'] !== (int)$u['id'] && !$rev) $deny();
    $fn = $f['file']; $dl = $rev ? 'naskah-' . no('R', $f['reg_id']) . '-v' . $f['version'] . ($f['kind'] === 'camera' ? '-final' : '') . '.' . pathinfo($fn, PATHINFO_EXTENSION) : $f['name'];
} elseif (isset($_GET['rv'])) {
    $q = $pdo->prepare("SELECT v.*,r.user_id,r.review_round,r.paper_status FROM reviews v JOIN registrations r ON r.id=v.reg_id WHERE v.id=?"); $q->execute([(int)$_GET['rv']]); $v = $q->fetch();
    if (!$v || !$v['file']) $deny();
    // penulis hanya bisa melihat setelah panitia memutuskan ronde tersebut
    $authorOk = (int)$v['user_id'] === (int)$u['id'] && $v['submitted_at'] && ((int)$v['round'] < (int)$v['review_round'] || in_array($v['paper_status'], ['revision', 'accepted', 'rejected'], true));
    if (!$admin && (int)$v['reviewer_id'] !== (int)$u['id'] && !$authorOk) $deny();
    $fn = $v['file']; $dl = 'review-' . no('R', $v['reg_id']) . '-ronde' . $v['round'] . '-' . $v['id'] . '.' . pathinfo($fn, PATHINFO_EXTENSION);
} else {
    $id = (int)($_GET['id'] ?? 0); $k = in_array($_GET['k'] ?? '', ['pay', 'camera'], true) ? $_GET['k'] : 'article';
    $s = $pdo->prepare("SELECT * FROM registrations WHERE id=?"); $s->execute([$id]); $r = $s->fetch();
    $ok = $r && ($admin || (int)$r['user_id'] === (int)$u['id']);
    if ($r && !$ok && $u['role'] === 'reviewer' && $k !== 'pay') $ok = $isReviewer($id);
    if (!$ok) $deny();
    $fn = (string)$r[$k . '_file'];
    $dl = $u['role'] === 'reviewer' ? 'naskah-' . no('R', $r['id']) . '.' . pathinfo($fn, PATHINFO_EXTENSION) : ($r[$k . '_name'] ?: $fn);
}
$fn = basename((string)$fn); $path = __DIR__ . '/uploads/private/' . $fn;
if ($fn === '' || !is_file($path)) { http_response_code(404); die('File tidak ditemukan'); }
$ext = strtolower(pathinfo($fn, PATHINFO_EXTENSION));
$mime = ['pdf' => 'application/pdf', 'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'][$ext] ?? 'application/octet-stream';
header('Content-Type: ' . $mime); header('Content-Length: ' . filesize($path));
header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $dl) . '"');
readfile($path);
