<?php
/*
 * Jurnal mitra (bagian "Publication Opportunities" di beranda), disimpan di setting 'journals'
 * sebagai baris: nama|url|akreditasi|penerbit|issn|scope|panduan|cover|catatan.
 * Dipakai bersama oleh Admin → Publikasi dan halaman Koordinator Jurnal (jurnal.php).
 */
function jrnClean($s) { return trim(str_replace(['|', "\r", "\n"], ' ', (string)$s)); }
function jrnUrl($s) { $s = trim((string)$s); if ($s !== '' && !preg_match('#^https?://#i', $s)) $s = 'https://' . $s; return jrnClean($s); }
function jrnRow() { return [jrnClean(post('name')), jrnUrl(post('url')), jrnClean(post('accr')), jrnClean(post('publisher')), jrnClean(post('issn')), jrnUrl(post('scope')), jrnUrl(post('guide')), jrnUrl(post('cover')), jrnClean(post('note'))]; }
function jrnSave(array $rs) { $o = []; foreach ($rs as $r) { $r = array_pad($r, 9, ''); $o[] = implode('|', array_slice($r, 0, 9)); } setSetting('journals', implode("\n", $o)); }
/** Proses aksi jradd/jredit/jrdel/jrup/jrdown; mengembalikan pesan untuk pengguna. */
function jrnHandle($a, $u)
{
    $rs = rows('journals'); $i = (int)($_POST['i'] ?? -1); $msg = ''; $log = '';
    if ($a === 'jradd') {
        $r = jrnRow(); if ($r[0] === '') return 'Nama jurnal wajib diisi.';
        $rs[] = $r; jrnSave($rs); $msg = 'Jurnal "' . $r[0] . '" ditambahkan dan langsung tampil di beranda.'; $log = 'menambahkan jurnal "' . $r[0] . '"';
    } else {
        if (!isset($rs[$i])) return 'Jurnal tidak ditemukan (mungkin baru saja diubah orang lain). Muat ulang halaman.';
        $old = $rs[$i][0] ?? '';
        if ($a === 'jredit') { $r = jrnRow(); if ($r[0] === '') return 'Nama jurnal wajib diisi.'; $rs[$i] = $r; $msg = 'Data jurnal "' . $r[0] . '" diperbarui.'; $log = 'mengubah jurnal "' . $r[0] . '"'; }
        elseif ($a === 'jrdel') { array_splice($rs, $i, 1); $msg = 'Jurnal "' . $old . '" dihapus dari beranda.'; $log = 'menghapus jurnal "' . $old . '"'; }
        elseif ($a === 'jrup' && $i > 0) { [$rs[$i - 1], $rs[$i]] = [$rs[$i], $rs[$i - 1]]; $msg = 'Urutan diperbarui.'; }
        elseif ($a === 'jrdown' && $i < count($rs) - 1) { [$rs[$i + 1], $rs[$i]] = [$rs[$i], $rs[$i + 1]]; $msg = 'Urutan diperbarui.'; }
        else return '';
        jrnSave($rs);
    }
    // admin diberi tahu bila perubahan dilakukan koordinator jurnal
    if ($log && $u['role'] !== 'admin' && function_exists('notifyAdmins')) notifyAdmins($u['name'] . ' (koordinator jurnal) ' . $log . '.', 'admin/?p=jurnal');
    return $msg;
}
