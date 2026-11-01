<?php require 'inc.php';
/* File kalender (.ics) agar peserta bisa menambahkan acara ke Google Calendar / Outlook / iOS. */
$s = setting('event_start'); if (!$s || !strtotime($s)) { http_response_code(404); die('Tanggal acara belum diatur.'); }
$st = strtotime($s); $en = setting('event_end') && strtotime(setting('event_end')) ? strtotime(setting('event_end')) : $st + 10 * 3600;
$esc = function ($v) { return preg_replace('/([,;\\\\])/', '\\\\$1', str_replace(["\r\n", "\n"], '\n', (string)$v)); };
$fmt = function ($t) { return gmdate('Ymd\THis\Z', $t); };
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9_-]/', '_', brand()) . '.ics"');
echo implode("\r\n", ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//' . $esc(brand()) . '//Conference//EN', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH', 'BEGIN:VEVENT',
    'UID:' . md5(absUrl() . $s) . '@' . host(), 'DTSTAMP:' . $fmt(time()), 'DTSTART:' . $fmt($st), 'DTEND:' . $fmt($en),
    'SUMMARY:' . $esc(setting('name')), 'DESCRIPTION:' . $esc(setting('tagline') . "\n" . absUrl()), 'LOCATION:' . $esc(setting('location')), 'URL:' . absUrl(),
    'END:VEVENT', 'END:VCALENDAR']) . "\r\n";
