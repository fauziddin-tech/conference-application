<?php
/*
 * Alur naskah & review berversi (terinspirasi MyThesis):
 *  - setiap unggahan naskah disimpan sebagai versi baru (riwayat tidak dihapus)
 *  - review berjalan per ronde; reviewer boleh mengunggah file Word berisi komentar
 *  - penulis mengunggah revisi + tanggapan untuk reviewer -> ronde berikutnya otomatis ke reviewer yang sama
 *  - notifikasi di aplikasi (lonceng) selain email
 */

/** Ekstensi naskah yang diterima (Word saja bila diaktifkan, agar reviewer bisa memberi komentar di file). */
function paperExts() { return setting('paper_word_only') === '1' ? ['doc', 'docx'] : ['pdf', 'doc', 'docx']; }
function paperExtLabel() { return strtoupper(implode('/', paperExts())); }

function paperFiles($rid)
{
    global $pdo;
    $q = $pdo->prepare("SELECT * FROM paper_files WHERE reg_id=? ORDER BY version,id"); $q->execute([$rid]);
    return $q->fetchAll();
}
/** Simpan berkas naskah sebagai versi baru dan jadikan berkas aktif pada pendaftaran. */
function addPaperFile($R, $kind, $file, $note = '')
{
    global $pdo;
    $q = $pdo->prepare("SELECT COALESCE(MAX(version),0)+1 FROM paper_files WHERE reg_id=? AND kind<>'camera'"); $q->execute([$R['id']]); $ver = (int)$q->fetchColumn();
    if ($kind === 'camera') $ver = max(1, $ver - 1);
    $pdo->prepare("INSERT INTO paper_files(reg_id,round,version,kind,file,name,note) VALUES(?,?,?,?,?,?,?)")->execute([$R['id'], (int)($R['review_round'] ?? 1), $ver, $kind, $file[0], $file[1], $note]);
    if ($kind === 'camera') $pdo->prepare("UPDATE registrations SET camera_file=?,camera_name=? WHERE id=?")->execute([$file[0], $file[1], $R['id']]);
    else $pdo->prepare("UPDATE registrations SET article_file=?,article_name=? WHERE id=?")->execute([$file[0], $file[1], $R['id']]);
    return $ver;
}
/** Hapus semua berkas naskah (mis. kategori berubah menjadi non-presenter atau akun dihapus). */
function deletePaper($rid)
{
    global $pdo;
    foreach (paperFiles($rid) as $f) rmPriv($f['file']);
    $q = $pdo->prepare("SELECT file FROM reviews WHERE reg_id=?"); $q->execute([$rid]); foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $f) rmPriv($f);
    $pdo->prepare("DELETE FROM paper_files WHERE reg_id=?")->execute([$rid]);
    $pdo->prepare("DELETE FROM reviews WHERE reg_id=?")->execute([$rid]);
}
function reviewsOf($rid, $round = null, $reviewer = null)
{
    global $pdo;
    $sql = "SELECT v.*,u.name,u.email FROM reviews v JOIN users u ON u.id=v.reviewer_id WHERE v.reg_id=?"; $p = [$rid];
    if ($round !== null) { $sql .= " AND v.round=?"; $p[] = $round; }
    if ($reviewer !== null) { $sql .= " AND v.reviewer_id=?"; $p[] = $reviewer; }
    $q = $pdo->prepare($sql . " ORDER BY v.round,v.id"); $q->execute($p);
    return $q->fetchAll();
}

/* ---------- notifikasi di aplikasi ---------- */
function notifyUser($uid, $msg, $link = '')
{
    global $pdo;
    try { $pdo->prepare("INSERT INTO notifications(user_id,msg,link) VALUES(?,?,?)")->execute([$uid, mb_substr($msg, 0, 300), $link]); } catch (Exception $x) {}
}
function notifyAdmins($msg, $link = '')
{
    global $pdo;
    foreach ($pdo->query("SELECT id FROM users WHERE role='admin'")->fetchAll(PDO::FETCH_COLUMN) as $id) notifyUser($id, $msg, $link);
}
function unreadCount($uid)
{
    global $pdo;
    $q = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0"); $q->execute([$uid]);
    return (int)$q->fetchColumn();
}

/**
 * Riwayat naskah per ronde: versi berkas, tanggapan penulis, dan review.
 * $view: 'author' (review anonim, tampil setelah keputusan), 'reviewer' (hanya review miliknya), 'admin' (semua).
 */
function paperHistory($R, $view, $reviewerId = null)
{
    global $REC;
    $files = paperFiles($R['id']); $rv = reviewsOf($R['id'], null, $view === 'reviewer' ? $reviewerId : null);
    $cur = (int)$R['review_round']; $decided = in_array($R['paper_status'], ['revision', 'accepted', 'rejected'], true);
    $base = BASE;
    $byRound = [];
    foreach ($files as $f) if ($f['kind'] !== 'camera') $byRound[(int)$f['round']]['files'][] = $f;
    foreach ($rv as $v) $byRound[(int)$v['round']]['reviews'][] = $v;
    if (!$byRound) { echo '<p><small>Belum ada berkas naskah.</small></p>'; return; }
    krsort($byRound);
    $latest = 0; foreach ($files as $f) if ($f['kind'] !== 'camera') $latest = max($latest, (int)$f['id']);
    echo '<div class="history">';
    foreach ($byRound as $r => $d) {
        echo '<div class="round"><h4>Ronde ' . (int)$r . ($r === $cur ? ' <span class="tag on">aktif</span>' : '') . '</h4>';
        foreach ($d['files'] ?? [] as $f) {
            echo '<div class="hv"><span class="vtag">v' . (int)$f['version'] . '</span> <a href="' . $base . 'file.php?pf=' . (int)$f['id'] . '">' . e($view === 'reviewer' ? 'naskah-' . no('R', $R['id']) . '-v' . $f['version'] . '.' . pathinfo($f['file'], PATHINFO_EXTENSION) : $f['name']) . '</a>'
                . ' <small>' . e($f['kind'] === 'revision' ? 'revisi' : 'naskah awal') . ' &middot; ' . e(date('d M Y H:i', strtotime($f['created_at']))) . '</small>' . ((int)$f['id'] === $latest ? ' <span class="tag">terbaru</span>' : '');
            if ($f['note'] !== null && $f['note'] !== '') echo '<div class="note"><small>Tanggapan penulis untuk reviewer:</small><br>' . nl2br(e($f['note'])) . '</div>';
            echo '</div>';
        }
        $list = $d['reviews'] ?? [];
        if ($view === 'author') $list = ($r < $cur || $decided) ? array_values(array_filter($list, function ($v) { return $v['submitted_at']; })) : [];
        foreach ($list as $i => $v) {
            $who = $view === 'admin' ? e($v['name']) : ($view === 'author' ? 'Reviewer ' . ($i + 1) : 'Review Anda');
            echo '<div class="review"><b>' . $who . '</b> ';
            if (!$v['submitted_at']) echo '<span class="badge pending">Belum mereview</span>' . ($v['due_date'] ? ' <small>tenggat ' . e(fdate($v['due_date'])) . ($v['due_date'] < date('Y-m-d') ? ' <span class="tag spam">terlambat</span>' : '') . '</small>' : '');
            else {
                if ($view !== 'author') echo '&middot; Skor <b>' . (int)$v['score'] . '/5</b> &middot; ';
                echo '<span class="badge">' . e(lbl($REC, $v['recommendation'])) . '</span> <small>' . e(date('d M Y', strtotime($v['submitted_at']))) . '</small>';
                echo '<p>' . nl2br(e($v['to_author'])) . '</p>';
                if ($v['file']) echo '<p>&#128206; <a href="' . $base . 'file.php?rv=' . (int)$v['id'] . '">Unduh file berisi komentar reviewer</a></p>';
                if ($view === 'admin' && $v['to_committee']) echo '<p><small>Rahasia untuk panitia:</small><br>' . nl2br(e($v['to_committee'])) . '</p>';
            }
            echo '</div>';
        }
        echo '</div>';
    }
    foreach ($files as $f) if ($f['kind'] === 'camera') echo '<div class="hv final"><span class="vtag">final</span> <a href="' . $base . 'file.php?pf=' . (int)$f['id'] . '">' . e($f['name']) . '</a> <small>camera-ready &middot; ' . e(date('d M Y H:i', strtotime($f['created_at']))) . '</small></div>';
    echo '</div>';
}
