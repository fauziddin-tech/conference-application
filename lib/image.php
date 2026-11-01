<?php
/*
 * Optimasi gambar (GD): perkecil sesuai ukuran tampil, putar otomatis foto HP (EXIF),
 * simpan sebagai WebP (atau JPEG bila WebP tidak tersedia). Dipakai saat unggah dan
 * lewat tombol "Optimalkan Gambar" di Admin → Sistem untuk gambar lama.
 */

/** Ukuran maksimum (piksel) per jenis gambar. */
function imgMax($prefix)
{
    return ['sp' => 800, 'logo' => 480, 'poster' => 1920, 'img' => 1200][$prefix] ?? 1200;
}

/**
 * Optimalkan file gambar di $path. Mengembalikan nama file baru (bisa berganti ekstensi) atau nama lama bila tidak diubah.
 * $keepOriginal: jangan hapus file asli (mis. poster untuk diunduh).
 */
function optimizeImage($path, $maxW, $quality = 82, $keepOriginal = false)
{
    if (!extension_loaded('gd') || !is_file($path)) return basename($path);
    $info = @getimagesize($path); if (!$info) return basename($path);
    [$w, $h, $type] = $info;
    $load = [IMAGETYPE_JPEG => 'imagecreatefromjpeg', IMAGETYPE_PNG => 'imagecreatefrompng', IMAGETYPE_WEBP => 'imagecreatefromwebp'][$type] ?? null;
    if (!$load || !function_exists($load)) return basename($path);
    // hindari kehabisan memori pada gambar raksasa
    if ($w * $h > 40000000) return basename($path);
    @ini_set('memory_limit', '256M');
    $src = @$load($path); if (!$src) return basename($path);
    // putar sesuai orientasi EXIF (foto HP sering tersimpan miring)
    if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $o = (int)(@exif_read_data($path)['Orientation'] ?? 1);
        $deg = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
        if ($deg) { $r = imagerotate($src, $deg, 0); if ($r) { imagedestroy($src); $src = $r; [$w, $h] = [imagesx($src), imagesy($src)]; } }
    }
    $scale = min(1, $maxW / max($w, $h));
    $nw = max(1, (int)round($w * $scale)); $nh = max(1, (int)round($h * $scale));
    $dst = imagecreatetruecolor($nw, $nh);
    imagealphablending($dst, false); imagesavealpha($dst, true);
    imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagedestroy($src);
    $dir = dirname($path); $base = pathinfo($path, PATHINFO_FILENAME);
    $webp = function_exists('imagewebp');
    $new = $base . ($keepOriginal ? '-web' : '') . ($webp ? '.webp' : ($type === IMAGETYPE_PNG ? '.png' : '.jpg'));
    $tmp = $dir . '/' . $new . '.tmp';
    $ok = $webp ? imagewebp($dst, $tmp, $quality) : ($type === IMAGETYPE_PNG ? imagepng($dst, $tmp, 8) : imagejpeg($dst, $tmp, $quality));
    imagedestroy($dst);
    if (!$ok || !is_file($tmp)) { @unlink($tmp); return basename($path); }
    // pakai hasil hanya bila lebih kecil (atau ukurannya memang diperkecil)
    if (filesize($tmp) >= filesize($path) && $scale >= 1 && !$keepOriginal) { @unlink($tmp); return basename($path); }
    rename($tmp, $dir . '/' . $new);
    if (!$keepOriginal && $dir . '/' . $new !== $path) @unlink($path);
    return $new;
}
/** Lebar & tinggi gambar di uploads/ (untuk atribut width/height agar halaman tidak "melompat"). */
function imgDims($file)
{
    if (!$file || isurl($file)) return '';
    $i = @getimagesize(__DIR__ . '/../uploads/' . basename($file));
    return $i ? ' width="' . (int)$i[0] . '" height="' . (int)$i[1] . '"' : '';
}
