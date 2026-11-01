<?php require 'inc.php'; $u = need();
/* Unduhan file privat: pemilik, admin, atau reviewer yang ditugaskan (hanya naskah). */
$id = (int)($_GET['id'] ?? 0); $k = in_array($_GET['k'] ?? '', ['pay', 'camera'], true) ? $_GET['k'] : 'article';
$s = $pdo->prepare("SELECT * FROM registrations WHERE id=?"); $s->execute([$id]); $r = $s->fetch();
$ok = $r && ($u['role'] === 'admin' || (int)$r['user_id'] === (int)$u['id']);
if ($r && !$ok && $u['role'] === 'reviewer' && $k !== 'pay') { $q = $pdo->prepare("SELECT 1 FROM reviews WHERE reg_id=? AND reviewer_id=?"); $q->execute([$id, $u['id']]); $ok = (bool)$q->fetch(); }
if (!$ok) { http_response_code(403); die('Akses ditolak'); }
$fn = basename((string)$r[$k . '_file']); $path = __DIR__ . '/uploads/private/' . $fn;
if ($fn === '' || !is_file($path)) { http_response_code(404); die('File tidak ditemukan'); }
$ext = strtolower(pathinfo($fn, PATHINFO_EXTENSION));
$mime = ['pdf' => 'application/pdf', 'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'][$ext] ?? 'application/octet-stream';
$dl = $u['role'] === 'reviewer' ? 'paper-' . no('R', $r['id']) . '.' . $ext : preg_replace('/[^A-Za-z0-9._-]/', '_', $r[$k . '_name'] ?: $fn);
header('Content-Type: ' . $mime); header('Content-Length: ' . filesize($path));
header('Content-Disposition: attachment; filename="' . $dl . '"');
readfile($path);
