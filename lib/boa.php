<?php
/*
 * Penyusun otomatis draf Book of Abstracts (.docx, Word 2007+), tanpa pustaka tambahan (hanya ZipArchive).
 * Isi: sampul, halaman judul/kolofon, sambutan (placeholder), panitia, daftar isi (field TOC),
 * jadwal acara, pembicara utama, abstrak per track (Heading 2 = judul makalah), indeks penulis
 * (field INDEX + XE), jurnal publikasi, sponsor. Field TOC/INDEX diperbarui Word saat dokumen dibuka.
 */

/** Daftar makalah yang masuk ke buku sesuai pilihan admin. */
function boaPapers(array $statuses, $paidOnly, $sort)
{
    global $pdo;
    if (!$statuses) return [];
    $in = implode(',', array_fill(0, count($statuses), '?'));
    $q = $pdo->prepare("SELECT r.*,u.email,u.institution FROM registrations r JOIN users u ON u.id=r.user_id WHERE r.article_title<>'' AND r.abstract<>'' AND r.paper_status IN($in)" . ($paidOnly ? " AND r.pay_status='verified'" : ''));
    $q->execute(array_values($statuses));
    $rs = $q->fetchAll();
    $topics = array_map(function ($t) { return $t['title']; }, items('topic'));
    $rank = function ($tr) use ($topics) { $i = array_search($tr, $topics, true); return $i === false ? 999 : $i; };
    usort($rs, function ($a, $b) use ($rank, $sort) {
        $ta = trim((string)$a['track']); $tb = trim((string)$b['track']);
        if ($ta !== $tb) { if ($ta === '') return 1; if ($tb === '') return -1; return $rank($ta) <=> $rank($tb) ?: strnatcasecmp($ta, $tb); }
        if ($sort === 'title') return strnatcasecmp($a['article_title'], $b['article_title']);
        if ($sort === 'author') return strnatcasecmp($a['full_name'], $b['full_name']);
        return $a['id'] <=> $b['id'];
    });
    return $rs;
}

/** Nama-nama penulis pendamping dari isian bebas "nama & afiliasi, satu per baris". */
function boaCoauthors($txt)
{
    $o = [];
    foreach (preg_split('/\R/', (string)$txt) as $l) {
        $l = trim(preg_replace('/^\s*(\d+[.)]|[-*•])\s*/u', '', $l)); if ($l === '') continue;
        $name = trim(preg_split('/\s*(,|\(|\s-\s|\s–\s|;|\|)\s*/u', $l, 2)[0]);
        // gelar di depan nama dipertahankan di teks, tapi tidak dipakai untuk indeks
        $o[] = [$l, $name !== '' ? $name : $l];
    }
    return $o;
}

class BoaDoc
{
    public $body = '';
    private $bm = 0;
    public static function x($s) { return htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', (string)$s), ENT_XML1 | ENT_QUOTES, 'UTF-8'); }
    /** run teks; $o: b,i,sz(pt),color,caps,hl(highlight) */
    public static function r($t, array $o = [])
    {
        $p = '';
        if (!empty($o['b'])) $p .= '<w:b/>';
        if (!empty($o['i'])) $p .= '<w:i/>';
        if (!empty($o['caps'])) $p .= '<w:caps/>';
        if (!empty($o['color'])) $p .= '<w:color w:val="' . $o['color'] . '"/>';
        if (!empty($o['sz'])) $p .= '<w:sz w:val="' . (int)round($o['sz'] * 2) . '"/><w:szCs w:val="' . (int)round($o['sz'] * 2) . '"/>';
        if (!empty($o['hl'])) $p .= '<w:highlight w:val="yellow"/>';
        if (!empty($o['sp'])) $p .= '<w:spacing w:val="' . (int)$o['sp'] . '"/>';
        $out = '';
        foreach (explode("\n", (string)$t) as $k => $line) $out .= ($k ? '<w:br/>' : '') . '<w:t xml:space="preserve">' . self::x($line) . '</w:t>';
        return '<w:r>' . ($p ? '<w:rPr>' . $p . '</w:rPr>' : '') . $out . '</w:r>';
    }
    /** paragraf; $o: style, jc, before, after, pb (page break before), keep, ind */
    public function p($runs, array $o = [])
    {
        $pp = '';
        if (!empty($o['style'])) $pp .= '<w:pStyle w:val="' . $o['style'] . '"/>';
        if (!empty($o['keep'])) $pp .= '<w:keepNext/>';
        if (!empty($o['pb'])) $pp .= '<w:pageBreakBefore/>';
        if (isset($o['before']) || isset($o['after'])) $pp .= '<w:spacing' . (isset($o['before']) ? ' w:before="' . (int)$o['before'] . '"' : '') . (isset($o['after']) ? ' w:after="' . (int)$o['after'] . '"' : '') . '/>';
        if (!empty($o['ind'])) $pp .= '<w:ind w:left="' . (int)$o['ind'] . '"/>';
        if (!empty($o['jc'])) $pp .= '<w:jc w:val="' . $o['jc'] . '"/>';
        $this->body .= '<w:p>' . ($pp ? '<w:pPr>' . $pp . '</w:pPr>' : '') . (is_array($runs) ? implode('', $runs) : $runs) . '</w:p>';
    }
    public function h($level, $text, $pb = false) { $this->p(self::r($text), ['style' => 'Heading' . $level, 'pb' => $pb]); }
    public function pageBreak() { $this->body .= '<w:p><w:r><w:br w:type="page"/></w:r></w:p>'; }
    /** field kompleks (TOC/INDEX/XE/PAGE) */
    public static function field($instr, $placeholder = '')
    {
        return '<w:r><w:fldChar w:fldCharType="begin" w:dirty="true"/></w:r><w:r><w:instrText xml:space="preserve"> ' . self::x($instr) . ' </w:instrText></w:r><w:r><w:fldChar w:fldCharType="separate"/></w:r>'
            . ($placeholder !== '' ? self::r($placeholder, ['i' => true, 'color' => '7F7F7F']) : '') . '<w:r><w:fldChar w:fldCharType="end"/></w:r>';
    }
    public static function xe($entry) { return '<w:r><w:fldChar w:fldCharType="begin"/></w:r><w:r><w:instrText xml:space="preserve"> XE "' . self::x(str_replace(['"', ':'], ["'", ' '], $entry)) . '" </w:instrText></w:r><w:r><w:fldChar w:fldCharType="end"/></w:r>'; }
    /** tabel sederhana; baris pertama = header */
    public function table(array $rows, array $widths)
    {
        $tw = array_sum($widths);
        $x = '<w:tbl><w:tblPr><w:tblStyle w:val="BoaTable"/><w:tblW w:w="' . $tw . '" w:type="dxa"/><w:tblLayout w:type="fixed"/></w:tblPr><w:tblGrid>';
        foreach ($widths as $w) $x .= '<w:gridCol w:w="' . $w . '"/>';
        $x .= '</w:tblGrid>';
        foreach ($rows as $i => $row) {
            $x .= '<w:tr>' . ($i === 0 ? '<w:trPr><w:tblHeader/></w:trPr>' : '');
            foreach ($row as $c => $cell) {
                $x .= '<w:tc><w:tcPr><w:tcW w:w="' . $widths[$c] . '" w:type="dxa"/>' . ($i === 0 ? '<w:shd w:val="clear" w:color="auto" w:fill="E7EFEE"/>' : '') . '</w:tcPr><w:p><w:pPr><w:spacing w:before="40" w:after="40"/></w:pPr>' . self::r($cell, ['b' => $i === 0, 'sz' => 10]) . '</w:p></w:tc>';
            }
            $x .= '</w:tr>';
        }
        $this->body .= $x . '</w:tbl><w:p/>';
    }

    public function save($path, $title, $headerText)
    {
        $W = 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"';
        $sect = '<w:sectPr><w:headerReference w:type="default" r:id="rIdH"/><w:footerReference w:type="default" r:id="rIdF"/><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1418" w:right="1418" w:bottom="1418" w:left="1418" w:header="709" w:footer="709" w:gutter="0"/><w:titlePg/></w:sectPr>';
        $doc = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document ' . $W . '><w:body>' . $this->body . $sect . '</w:body></w:document>';
        $font = '<w:rFonts w:ascii="Times New Roman" w:hAnsi="Times New Roman" w:cs="Times New Roman" w:eastAsia="Times New Roman"/>';
        $hd = function ($id, $name, $sz, $before, $after, $extra = '', $color = '1D3A3A') use ($font) {
            return '<w:style w:type="paragraph" w:styleId="' . $id . '"><w:name w:val="' . $name . '"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:uiPriority w:val="9"/><w:qFormat/><w:pPr><w:keepNext/><w:keepLines/><w:spacing w:before="' . $before . '" w:after="' . $after . '"/>' . $extra . '<w:outlineLvl w:val="' . ((int)substr($id, -1) - 1) . '"/></w:pPr><w:rPr>' . $font . '<w:b/><w:color w:val="' . $color . '"/><w:sz w:val="' . ($sz * 2) . '"/><w:szCs w:val="' . ($sz * 2) . '"/></w:rPr></w:style>';
        };
        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:styles ' . $W . '>'
            . '<w:docDefaults><w:rPrDefault><w:rPr>' . $font . '<w:sz w:val="22"/><w:szCs w:val="22"/><w:lang w:val="en-US"/></w:rPr></w:rPrDefault><w:pPrDefault><w:pPr><w:spacing w:after="120" w:line="264" w:lineRule="auto"/></w:pPr></w:pPrDefault></w:docDefaults>'
            . '<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/><w:qFormat/></w:style>'
            . '<w:style w:type="paragraph" w:styleId="Title"><w:name w:val="Title"/><w:basedOn w:val="Normal"/><w:qFormat/><w:pPr><w:jc w:val="center"/><w:spacing w:after="240"/></w:pPr><w:rPr>' . $font . '<w:b/><w:color w:val="1D3A3A"/><w:sz w:val="48"/><w:szCs w:val="48"/></w:rPr></w:style>'
            . $hd('Heading1', 'heading 1', 16, 0, 240, '<w:pageBreakBefore/><w:pBdr><w:bottom w:val="single" w:sz="8" w:space="4" w:color="D4A373"/></w:pBdr>')
            . $hd('Heading2', 'heading 2', 13, 240, 120, '', '27706F')
            . $hd('Heading3', 'heading 3', 11, 200, 80)
            . '<w:style w:type="paragraph" w:styleId="TOC1"><w:name w:val="toc 1"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:uiPriority w:val="39"/><w:pPr><w:tabs><w:tab w:val="right" w:leader="dot" w:pos="9060"/></w:tabs><w:spacing w:before="120" w:after="40"/></w:pPr><w:rPr><w:b/></w:rPr></w:style>'
            . '<w:style w:type="paragraph" w:styleId="TOC2"><w:name w:val="toc 2"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:uiPriority w:val="39"/><w:pPr><w:tabs><w:tab w:val="right" w:leader="dot" w:pos="9060"/></w:tabs><w:spacing w:after="40"/><w:ind w:left="284" w:hanging="0"/></w:pPr><w:rPr><w:sz w:val="20"/></w:rPr></w:style>'
            . '<w:style w:type="paragraph" w:styleId="Index1"><w:name w:val="index 1"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:pPr><w:spacing w:after="0"/><w:ind w:left="220" w:hanging="220"/></w:pPr><w:rPr><w:sz w:val="20"/></w:rPr></w:style>'
            . '<w:style w:type="paragraph" w:styleId="IndexHeading"><w:name w:val="index heading"/><w:basedOn w:val="Normal"/><w:next w:val="Index1"/><w:pPr><w:spacing w:before="160" w:after="40"/></w:pPr><w:rPr><w:b/><w:color w:val="27706F"/></w:rPr></w:style>'
            . '<w:style w:type="paragraph" w:styleId="Header"><w:name w:val="header"/><w:basedOn w:val="Normal"/><w:pPr><w:spacing w:after="0"/></w:pPr><w:rPr><w:sz w:val="18"/><w:color w:val="7F7F7F"/></w:rPr></w:style>'
            . '<w:style w:type="paragraph" w:styleId="Footer"><w:name w:val="footer"/><w:basedOn w:val="Normal"/><w:pPr><w:spacing w:after="0"/></w:pPr><w:rPr><w:sz w:val="18"/></w:rPr></w:style>'
            . '<w:style w:type="table" w:styleId="BoaTable"><w:name w:val="Boa Table"/><w:tblPr><w:tblBorders><w:top w:val="single" w:sz="4" w:color="BFBFBF"/><w:left w:val="single" w:sz="4" w:color="BFBFBF"/><w:bottom w:val="single" w:sz="4" w:color="BFBFBF"/><w:right w:val="single" w:sz="4" w:color="BFBFBF"/><w:insideH w:val="single" w:sz="4" w:color="BFBFBF"/><w:insideV w:val="single" w:sz="4" w:color="BFBFBF"/></w:tblBorders><w:tblCellMar><w:left w:w="85" w:type="dxa"/><w:right w:w="85" w:type="dxa"/></w:tblCellMar></w:tblPr></w:style>'
            . '</w:styles>';
        $header = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:hdr ' . $W . '><w:p><w:pPr><w:pStyle w:val="Header"/><w:pBdr><w:bottom w:val="single" w:sz="4" w:space="4" w:color="D4A373"/></w:pBdr><w:jc w:val="right"/></w:pPr>' . self::r($headerText, ['i' => true]) . '</w:p></w:hdr>';
        $footer = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:ftr ' . $W . '><w:p><w:pPr><w:pStyle w:val="Footer"/><w:jc w:val="center"/></w:pPr><w:r><w:fldChar w:fldCharType="begin"/></w:r><w:r><w:instrText xml:space="preserve"> PAGE </w:instrText></w:r><w:r><w:fldChar w:fldCharType="separate"/></w:r><w:r><w:t>1</w:t></w:r><w:r><w:fldChar w:fldCharType="end"/></w:r></w:p></w:ftr>';
        $settings = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:settings ' . $W . '><w:updateFields w:val="true"/><w:defaultTabStop w:val="720"/><w:compat><w:compatSetting w:name="compatibilityMode" w:uri="http://schemas.microsoft.com/office/word" w:val="15"/></w:compat></w:settings>';
        $ct = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/><Override PartName="/word/settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml"/><Override PartName="/word/header1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.header+xml"/><Override PartName="/word/footer1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/></Types>';
        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/></Relationships>';
        $drels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdS" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/><Relationship Id="rIdT" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings" Target="settings.xml"/><Relationship Id="rIdH" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/header" Target="header1.xml"/><Relationship Id="rIdF" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer1.xml"/></Relationships>';
        $core = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>' . self::x($title) . '</dc:title><dc:creator>' . self::x(brand()) . '</dc:creator><dcterms:created xsi:type="dcterms:W3CDTF">' . gmdate('Y-m-d\TH:i:s\Z') . '</dcterms:created></cp:coreProperties>';
        $z = new ZipArchive();
        if ($z->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) return false;
        foreach (['[Content_Types].xml' => $ct, '_rels/.rels' => $rels, 'word/document.xml' => $doc, 'word/styles.xml' => $styles, 'word/settings.xml' => $settings, 'word/header1.xml' => $header, 'word/footer1.xml' => $footer, 'word/_rels/document.xml.rels' => $drels, 'docProps/core.xml' => $core] as $n => $c) $z->addFromString($n, $c);
        return $z->close();
    }
}

/** Bangun draf; $o: statuses[], paid, sort, email, onepage, sections[] */
function boaBuild(array $o, &$count = 0)
{
    $R = 'BoaDoc::r';
    $d = new BoaDoc();
    $sec = array_flip($o['sections']);
    $papers = boaPapers($o['statuses'], !empty($o['paid']), $o['sort']); $count = count($papers);
    $name = setting('name'); $short = brand(); $year = date('Y', strtotime(setting('event_start') ?: 'now'));
    $ph = function ($t) { return BoaDoc::r('[' . $t . ']', ['hl' => true, 'i' => true]); };

    // --- sampul ---
    $d->p([], ['before' => 1800]);
    if (setting('organizer')) $d->p($R(setting('organizer'), ['caps' => true, 'sp' => 20, 'sz' => 11, 'color' => '27706F']), ['jc' => 'center', 'after' => 600]);
    $d->p($R($name), ['style' => 'Title']);
    if (setting('tagline')) $d->p($R(setting('tagline'), ['i' => true, 'sz' => 14]), ['jc' => 'center', 'after' => 900]);
    $d->p($R('BOOK OF ABSTRACTS', ['b' => true, 'sz' => 22, 'color' => 'B07B4A', 'sp' => 40]), ['jc' => 'center', 'after' => 120]);
    $d->p($R('and Conference Programme', ['i' => true, 'sz' => 13]), ['jc' => 'center', 'after' => 1400]);
    $d->p($R(trim(setting('date') . (setting('location') ? "\n" . setting('location') : '')), ['sz' => 13]), ['jc' => 'center']);

    // --- halaman judul / kolofon ---
    if (isset($sec['colophon'])) {
        $d->pageBreak();
        $d->p($R('Book of Abstracts', ['b' => true, 'sz' => 16]), ['after' => 60]);
        $d->p($R($name, ['sz' => 12]), ['after' => 600]);
        $rows = [['Theme', setting('tagline')], ['Date & venue', trim(setting('date') . ', ' . setting('location'), ', ')], ['Organizer', setting('organizer')]];
        foreach ($rows as $rw) if ($rw[1] !== '') $d->p([$R($rw[0] . ': ', ['b' => true]), $R($rw[1])], ['after' => 60]);
        $d->p([$R('Editors: ', ['b' => true]), $ph('names of the editors')], ['before' => 240, 'after' => 60]);
        $d->p([$R('Cover design: ', ['b' => true]), $ph('name')], ['after' => 60]);
        $d->p([$R('ISBN: ', ['b' => true]), $ph('ISBN, if any')], ['after' => 60]);
        $d->p([$R('Publisher: ', ['b' => true]), setting('organizer') ? $R(setting('organizer')) : $ph('publisher')], ['after' => 60]);
        $d->p($R('© ' . $year . ' ' . $short . '. All rights reserved. The abstracts in this book are reproduced as submitted by the authors; the authors are responsible for the content of their abstracts.', ['sz' => 9, 'color' => '595959']), ['before' => 2400, 'jc' => 'both']);
    }

    // --- sambutan ---
    if (isset($sec['welcome'])) {
        $d->h(1, 'Welcome Messages');
        $signers = rows('signers') ?: [['', 'Chair of the Organizing Committee']];
        foreach ($signers as $s) {
            $role = $s[1] ?? ''; $who = $s[0] ?? '';
            $d->h(2, 'Message from the ' . ($role !== '' ? $role : 'Committee'));
            $d->p($ph('Write the welcome message here (about 300–500 words).'), ['jc' => 'both']);
            $d->p($who !== '' ? $R($who, ['b' => true]) : $ph('name'), ['before' => 360, 'after' => 0]);
            if ($role !== '') $d->p($R($role, ['i' => true]));
        }
    }

    // --- panitia ---
    if (isset($sec['committee'])) {
        $cm = items('committee');
        $d->h(1, 'Committees');
        if (!$cm) $d->p($ph('Add the committee members in Admin → Konten → Komite'));
        $groups = [];
        foreach ($cm as $c) $groups[trim($c['extra']) ?: 'Committee'][] = $c;
        foreach ($groups as $g => $list) {
            $d->h(3, $g);
            foreach ($list as $c) $d->p([$R($c['title'], ['b' => true]), $c['subtitle'] ? $R(' — ' . $c['subtitle']) : '', $c['body'] ? $R(', ' . $c['body'], ['i' => true, 'color' => '595959']) : ''], ['after' => 40]);
        }
    }

    // --- daftar isi ---
    if (isset($sec['toc'])) {
        $d->h(1, 'Table of Contents');
        $d->p(BoaDoc::field('TOC \\o "1-2" \\h \\z \\u', 'The table of contents is generated when the document is opened in Microsoft Word (answer "Yes" to update the fields), or right-click here → Update Field.'));
    }

    // --- jadwal ---
    if (isset($sec['program'])) {
        $ag = agendaRows();
        $d->h(1, 'Conference Programme');
        if (!$ag) $d->p($ph('The programme will be added here (Admin → Agenda)'));
        $days = [];
        foreach ($ag as $a) $days[$a['day']][] = $a;
        foreach ($days as $day => $list) {
            $d->h(3, $day);
            $rows = [['Time', 'Session', 'Speaker / Moderator', 'Room']];
            foreach ($list as $a) $rows[] = [$a['time'], $a['title'] . ($a['track'] ? ' (' . $a['track'] . ')' : '') . ($a['description'] ? "\n" . $a['description'] : ''), $a['speaker'], $a['room']];
            $d->table($rows, [1400, 3900, 2300, 1460]);
        }
    }

    // --- pembicara ---
    if (isset($sec['speakers'])) {
        $sp = rows('speakers');
        $d->h(1, 'Keynote and Invited Speakers');
        if (!$sp) $d->p($ph('Add the speakers in Admin → Pembicara'));
        foreach ($sp as $s) {
            $s = array_pad($s, 4, '');
            $d->h(2, $s[1]);
            $d->p([$R($s[0], ['b' => true, 'color' => 'B07B4A']), $s[2] ? $R(' · ' . $s[2], ['i' => true]) : ''], ['after' => 60]);
            $d->p([$R('Title: ', ['b' => true]), $ph('title of the talk')], ['after' => 60]);
            $d->p([$R('Short biography: ', ['b' => true]), $ph('100–150 words')], ['jc' => 'both']);
        }
    }

    // --- abstrak ---
    $d->h(1, 'Abstracts');
    $per = [];
    foreach ($papers as $x) { $k = trim((string)$x['track']) ?: 'Other Topics'; $per[$k] = ($per[$k] ?? 0) + 1; }
    if ($per) { $rows = [['Track', 'Abstracts']]; foreach ($per as $k => $v) $rows[] = [$k, (string)$v]; $rows[] = ['Total', (string)$count]; $d->table($rows, [7260, 1800]); }
    $d->p($R($count . ' abstract' . ($count === 1 ? '' : 's') . ', grouped by track' . ($o['sort'] === 'title' ? ' and sorted by title' : ($o['sort'] === 'author' ? ' and sorted by presenting author' : ' in order of submission')) . '. The code before each title is the paper ID used in the programme.', ['i' => true, 'color' => '595959']));
    $track = null; $first = true;
    foreach ($papers as $x) {
        $tr = trim((string)$x['track']) ?: 'Other Topics';
        if ($tr !== $track) { $d->h(1, $tr); $track = $tr; $first = true; }
        $pb = !empty($o['onepage']) && !$first; $first = false;
        $d->p([$R(no('R', $x['id']) . '  ', ['color' => 'B07B4A']), $R($x['article_title'])], ['style' => 'Heading2', 'pb' => $pb]);
        $co = boaCoauthors($x['coauthors']);
        $auth = [$R($x['full_name'], ['b' => true]), BoaDoc::xe($x['full_name'])];
        $d->p($auth, ['after' => 0, 'keep' => true]);
        $d->p($R(trim(regInstitution($x) . ($x['country'] ? ', ' . $x['country'] : ''), ', '), ['i' => true, 'sz' => 10]), ['after' => $co ? 60 : 0, 'keep' => true]);
        foreach ($co as $c) $d->p([$R($c[0], ['sz' => 10]), BoaDoc::xe($c[1])], ['after' => 0, 'keep' => true]);
        if (!empty($o['email'])) $d->p([$R('Corresponding author: ', ['sz' => 9, 'color' => '595959']), $R($x['email'], ['sz' => 9, 'color' => '27706F'])], ['before' => 60, 'after' => 0, 'keep' => true]);
        $d->p($R('Abstract', ['b' => true, 'sz' => 10]), ['before' => 160, 'after' => 40, 'keep' => true]);
        foreach (preg_split('/\R{2,}|\R/', trim($x['abstract'])) as $para) if (trim($para) !== '') $d->p($R(trim($para)), ['jc' => 'both', 'after' => 80]);
        if (trim((string)$x['keywords']) !== '') $d->p([$R('Keywords: ', ['b' => true, 'i' => true]), $R(implode(', ', array_filter(array_map('trim', preg_split('/[,;]+/', $x['keywords'])))), ['i' => true])], ['before' => 80]);
    }
    if (!$papers) $d->p($ph('No abstracts match the selected criteria yet.'));

    // --- indeks penulis ---
    if (isset($sec['index'])) {
        $d->h(1, 'Author Index');
        $d->body .= '<w:p>' . BoaDoc::field('INDEX \\e ", " \\h "A" \\c "2" \\z "1033"', 'The author index is generated when the document is opened in Microsoft Word, or right-click here → Update Field.') . '</w:p>';
    }

    // --- jurnal ---
    if (isset($sec['journals']) && ($js = rows('journals'))) {
        $d->h(1, 'Publication Opportunities');
        $d->p($R('Selected papers presented at the conference may be published in the following partner journals, subject to each journal\'s review process and scope.'), ['jc' => 'both']);
        foreach ($js as $j) {
            $j = array_pad($j, 9, '');
            $d->p([$R($j[0], ['b' => true]), $j[2] ? $R(' · ' . $j[2]) : ''], ['before' => 120, 'after' => 0]);
            $meta = array_filter([$j[3], $j[4], $j[1]]);
            if ($meta) $d->p($R(implode(' · ', $meta), ['sz' => 10, 'color' => '595959']), ['after' => 0]);
        }
    }

    // --- sponsor ---
    if (isset($sec['sponsors']) && ($ss = items('sponsor'))) {
        $d->h(1, 'Sponsors and Partners');
        $g = [];
        foreach ($ss as $s) $g[trim($s['extra']) ?: 'Partners'][] = $s['title'];
        foreach ($g as $k => $names) { $d->h(3, $k); $d->p($R(implode(' · ', $names))); }
        $d->p($R('Thank you to all sponsors and partners for supporting ' . $short . ' ' . $year . '.', ['i' => true]), ['before' => 240]);
    }

    $tmp = tempnam(sys_get_temp_dir(), 'boa');
    return $d->save($tmp, 'Book of Abstracts - ' . $name, $short . ' ' . $year . ' · Book of Abstracts') ? $tmp : null;
}

/** Institusi pendaftar (kolom registrasi; cadangan dari akun). */
function regInstitution($x) { return trim((string)($x['institution'] ?? '')); }
