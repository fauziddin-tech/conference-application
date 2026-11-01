<?php require 'inc.php'; $u = need(); chk();
/* Notifikasi di aplikasi: daftar aktivitas terbaru; dibuka = ditandai sudah dibaca. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('a') === 'clear') { $pdo->prepare("DELETE FROM notifications WHERE user_id=? AND is_read=1")->execute([$u['id']]); flash('Notifikasi yang sudah dibaca dihapus.'); go('notifikasi.php'); }
if (isset($_GET['go'])) { // buka tautan notifikasi & tandai dibaca
    $q = $pdo->prepare("SELECT link FROM notifications WHERE id=? AND user_id=?"); $q->execute([(int)$_GET['go'], $u['id']]); $l = (string)$q->fetchColumn();
    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?")->execute([(int)$_GET['go'], $u['id']]);
    go(BASE . ($l !== '' && strpos($l, '//') === false && strpos($l, ':') === false ? ltrim($l, '/') : homeFor($u)));
}
$q = $pdo->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 100"); $q->execute([$u['id']]); $L = $q->fetchAll();
head('Notifikasi');
$pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id=? AND is_read=0")->execute([$u['id']]); ?>
<h2>Notifikasi</h2>
<?php if (!$L): ?><p class="panel">Belum ada notifikasi.</p><?php else: ?>
<ul class="notif"><?php foreach ($L as $n): ?><li class="<?= $n['is_read'] ? '' : 'unread' ?>"><a href="?go=<?= $n['id'] ?>"><?= e($n['msg']) ?></a><small><?= e(date('d M Y H:i', strtotime($n['created_at']))) ?></small></li><?php endforeach; ?></ul>
<form method="post"><?= csrf() ?><input type="hidden" name="a" value="clear"><button class="link">Hapus notifikasi yang sudah dibaca</button></form><?php endif; foot();
