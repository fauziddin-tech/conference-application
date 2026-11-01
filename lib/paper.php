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
/** $msg dalam bahasa Indonesia; $msgEn (opsional) ditampilkan bila pengguna memakai bahasa Inggris. */
function notifyUser($uid, $msg, $link = '', $msgEn = null)
{
    global $pdo;
    try { $pdo->prepare("INSERT INTO notifications(user_id,msg,link,msg_en) VALUES(?,?,?,?)")->execute([$uid, mb_substr($msg, 0, 300), $link, $msgEn !== null ? mb_substr($msgEn, 0, 300) : null]); } catch (Exception $x) {}
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
    if (!$byRound) { echo '<p><small>' . te('No paper files yet.') . '</small></p>'; return; }
    krsort($byRound);
    $latest = 0; foreach ($files as $f) if ($f['kind'] !== 'camera') $latest = max($latest, (int)$f['id']);
    echo '<div class="history">';
    foreach ($byRound as $r => $d) {
        echo '<div class="round"><h4>' . te('Round %d', (int)$r) . ($r === $cur ? ' <span class="tag on">' . te('active') . '</span>' : '') . '</h4>';
        foreach ($d['files'] ?? [] as $f) {
            echo '<div class="hv"><span class="vtag">v' . (int)$f['version'] . '</span> <a href="' . $base . 'file.php?pf=' . (int)$f['id'] . '">' . e($view === 'reviewer' ? 'naskah-' . no('R', $R['id']) . '-v' . $f['version'] . '.' . pathinfo($f['file'], PATHINFO_EXTENSION) : $f['name']) . '</a>'
                . ' <small>' . e($f['kind'] === 'revision' ? t('revision') : t('initial submission')) . ' &middot; ' . e(date('d M Y H:i', strtotime($f['created_at']))) . '</small>' . ((int)$f['id'] === $latest ? ' <span class="tag">' . te('latest') . '</span>' : '');
            if ($f['note'] !== null && $f['note'] !== '') echo '<div class="note"><small>' . te('Author\'s response to reviewers') . ':</small><br>' . nl2br(e($f['note'])) . '</div>';
            echo '</div>';
        }
        $list = $d['reviews'] ?? [];
        if ($view === 'author') $list = ($r < $cur || $decided) ? array_values(array_filter($list, function ($v) { return $v['submitted_at']; })) : [];
        foreach ($list as $i => $v) {
            $who = $view === 'admin' ? e($v['name']) : ($view === 'author' ? 'Reviewer ' . ($i + 1) : te('Your review'));
            echo '<div class="review"><b>' . $who . '</b> ';
            if (!$v['submitted_at']) echo '<span class="badge pending">' . te('Not reviewed yet') . '</span>' . ($v['due_date'] ? ' <small>' . te('due') . ' ' . e(fdateL($v['due_date'])) . ($v['due_date'] < date('Y-m-d') ? ' <span class="tag spam">' . te('overdue') . '</span>' : '') . '</small>' : '');
            else {
                if ($view !== 'author') echo '&middot; ' . te('Score') . ' <b>' . (int)$v['score'] . '/5</b> &middot; ';
                echo '<span class="badge">' . e(lbl($REC, $v['recommendation'])) . '</span> <small>' . e(date('d M Y', strtotime($v['submitted_at']))) . '</small>';
                echo '<p>' . nl2br(e($v['to_author'])) . '</p>';
                if ($v['file']) echo '<p class="att"><svg class="ic" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg> <a href="' . $base . 'file.php?rv=' . (int)$v['id'] . '">' . te('Download file with reviewer comments') . '</a></p>';
                if ($view === 'admin' && $v['to_committee']) echo '<p><small>' . te('Confidential to the committee') . ':</small><br>' . nl2br(e($v['to_committee'])) . '</p>';
            }
            echo '</div>';
        }
        echo '</div>';
    }
    foreach ($files as $f) if ($f['kind'] === 'camera') echo '<div class="hv final"><span class="vtag">final</span> <a href="' . $base . 'file.php?pf=' . (int)$f['id'] . '">' . e($f['name']) . '</a> <small>camera-ready &middot; ' . e(date('d M Y H:i', strtotime($f['created_at']))) . '</small></div>';
    echo '</div>';
}

/* ---------- sertifikat reviewer ---------- */
/** Jumlah naskah berbeda yang sudah direview (minimal satu review terkirim). */
function reviewerDone($uid)
{
    global $pdo;
    $q = $pdo->prepare("SELECT COUNT(DISTINCT reg_id) FROM reviews WHERE reviewer_id=? AND submitted_at IS NOT NULL"); $q->execute([$uid]);
    return (int)$q->fetchColumn();
}
function reviewerCertReady($uid) { return setting('cert_reviewer', '1') === '1' && reviewerDone($uid) > 0; }

/* ---------- sertifikat pembicara & panitia (tanpa akun; dibuat admin) ---------- */
/** Kode verifikasi stabil (HMAC dengan rahasia aplikasi), mis. SP3F9A…, CM12AB…. */
function certCode($kind, $key)
{
    $sec = setting('app_secret');
    if ($sec === '') { $sec = bin2hex(random_bytes(16)); setSetting('app_secret', $sec); }
    return $kind . strtoupper(substr(hash_hmac('sha256', $kind . '|' . $key, $sec), 0, 10));
}
/** Peran pembicara dalam kalimat sertifikat. */
function speakerRole($r)
{
    $r = trim((string)$r);
    if ($r === '') return 'Speaker';
    if (stripos($r, 'open speech') !== false || stripos($r, 'opening') !== false) return 'Opening Speaker';
    return $r;
}
/** [nama, afiliasi, peran, kode, no] untuk satu baris pembicara. */
function speakerCert($sp, $i) { $sp = array_pad($sp, 4, ''); return [$sp[1], $sp[2], speakerRole($sp[0]), certCode('SP', $sp[1]), no('SP', $i + 1)]; }
function committeeCert($c)
{
    $role = trim($c['subtitle'] ?: 'Member');
    if ($c['extra']) $role .= ' — ' . $c['extra'];
    elseif (stripos($role, 'committee') === false) $role .= ' of the Organizing Committee';
    return [$c['title'], $c['body'], $role, certCode('CM', $c['id']), no('CM', $c['id'])];
}
function moderatorCert($c)
{
    $role = 'Moderator' . (trim((string)$c['subtitle']) !== '' ? ' of ' . trim($c['subtitle']) : '');
    return [$c['title'], $c['body'], $role, certCode('MD', $c['id']), no('MD', $c['id'])];
}
/** Cari pemilik kode SP/CM/MD untuk halaman verifikasi: [jenis, nama, afiliasi, peran] atau null. */
function findCertByCode($code)
{
    if (strpos($code, 'SP') === 0) foreach (rows('speakers') as $i => $sp) { $x = speakerCert($sp, $i); if (hash_equals($x[3], $code)) return ['Pembicara', $x[0], $x[1], $x[2]]; }
    if (strpos($code, 'CM') === 0) foreach (items('committee') as $c) { $x = committeeCert($c); if (hash_equals($x[3], $code)) return ['Panitia', $x[0], $x[1], $x[2]]; }
    if (strpos($code, 'MD') === 0) foreach (items('moderator') as $c) { $x = moderatorCert($c); if (hash_equals($x[3], $code)) return ['Moderator', $x[0], $x[1], $x[2]]; }
    return null;
}

