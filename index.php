<?php require 'inc.php';head('Beranda');
$u=user();$em=setting('email')?:'picoe@universitaspahlawan.ac.id';
$map=setting('map_url')?:'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3989.6635694481533!2d101.45034407509675!3d0.5046369637092568!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x31d5aebce81ef37f%3A0x9b837fcba8f106e9!2sHotel%20Pangeran!5e0!3m2!1sen!2sid!4v1716732941113!5m2!1sen!2sid';
$reg=$u?($u['role']==='admin'?'admin/':'registrasi.php'):'register.php';?>
<section class="hero"><h1><?=e(setting('name'))?></h1><p><?=e(setting('tagline'))?></p>
<p><b><?=e(setting('date'))?></b> &middot; <?=e(setting('time'))?> &middot; <?=e(setting('location'))?></p>
<div class="actions"><a class="btn" href="#about">About The Event</a><a class="btn ghost" href="<?=$reg?>"><?=$u?'Form Pendaftaran':'Daftar Sekarang'?></a></div></section>

<section id="about" class="s fade"><h2>Tentang Konferensi</h2><div class="two"><div><p><?=nl2br(e(setting('about')))?></p></div>
<aside class="panel"><h3>When</h3><p><?=e(setting('date'))?><br><?=e(setting('time'))?></p><h3>Where</h3><p><?=e(setting('location'))?></p>
<?php if($b=rows('benefits')):?><h3>Benefit</h3><ul><?php foreach($b as $x):?><li><?=e($x[0])?></li><?php endforeach;?></ul><?php endif;?>
<?php if($sp=rows('supporters')):?><h3>Dukungan Institusi</h3><ul><?php foreach($sp as $x):?><li><?=e($x[0])?></li><?php endforeach;?></ul><?php endif;?></aside></div></section>

<section id="speakers" class="s fade"><h2>Pembicara</h2>
<?php $G=[];foreach(rows('speakers') as $s){$G[strtolower(trim($s[0]))][]=$s;}
$pri=function($r){return stripos($r,'open speech')!==false?0:(stripos($r,'keynote')!==false?1:2);};
$keys=array_keys($G);usort($keys,function($a,$b)use($pri,$keys){$d=$pri($a)<=>$pri($b);return $d?$d:(array_search($a,$keys)<=>array_search($b,$keys));});
foreach($keys as $k):$pr=$pri($k);$sz=[2,1.5,1][$pr];$cl=['k2','k15','k1'][$pr];?>
<div class="spgroup <?=$cl?>" style="--k:<?=$sz?>"><?php foreach($G[$k] as $s):?><div class="card sp"><?php if(!empty($s[3])):?><img src="uploads/<?=e($s[3])?>" alt="<?=e($s[1]??'')?>"><?php else:?><div class="ph" aria-hidden="true"><?=e(initials($s[1]??''))?></div><?php endif;?><small><?=e($s[0])?></small><b><?=e($s[1]??'')?></b><span><?=e($s[2]??'')?></span></div><?php endforeach;?></div>
<?php endforeach;?></section>

<section id="venue" class="s fade"><h2>Lokasi Acara</h2><div class="venue"><div class="map"><iframe src="<?=e($map)?>" loading="lazy" allowfullscreen referrerpolicy="no-referrer-when-downgrade" title="Peta lokasi"></iframe></div>
<div class="panel"><h3><?=e(setting('location'))?></h3><p>Hotel Pangeran Pekanbaru adalah hotel keluarga dan bisnis yang menawarkan layanan terbaik untuk kenyamanan setiap tamu.</p><p><a href="https://pekanbaru.hotelpangeran.com" target="_blank" rel="noopener">Info hotel &rarr;</a></p></div></div></section>

<?php if($j=rows('journals')):?><section id="publication" class="s fade"><h2>Peluang Publikasi</h2><p>Jurnal mitra untuk publikasi artikel konferensi. Baca Focus &amp; Scope dan panduan penulis masing-masing jurnal sebelum mengirim naskah.</p><div class="grid jgrid"><?php foreach($j as $x):$x=array_pad($x,9,'');?>
<div class="card jr"><div class="jrow"><?php if(isurl($x[7])):?><img class="jcover" src="<?=e($x[7])?>" alt="Cover <?=e($x[0])?>" loading="lazy" referrerpolicy="no-referrer" onerror="this.style.display='none'"><?php endif;?>
<div class="jinfo"><h3><?=isurl($x[1])?'<a href="'.e($x[1]).'" target="_blank" rel="noopener">'.e($x[0]).'</a>':e($x[0])?></h3>
<?php if($x[2]):?><span class="badge"><?=e($x[2])?></span><?php endif;?>
<?php if($x[3]):?><p><small>Penerbit:</small> <?=e($x[3])?></p><?php endif;?>
<?php if($x[4]):?><p><small>ISSN:</small> <?=e($x[4])?></p><?php endif;?>
<?php if($x[5]&&!isurl($x[5])):?><p class="jscope"><?=nl2br(e($x[5]))?></p><?php endif;?>
<?php if($x[8]):?><p><small>Catatan:</small> <?=e($x[8])?></p><?php endif;?>
<p class="jbtn"><?php if(isurl($x[1])):?><a class="btn" href="<?=e($x[1])?>" target="_blank" rel="noopener">Kunjungi Jurnal</a><?php endif;?><?php if(isurl($x[5])):?><a class="btn ghost2" href="<?=e($x[5])?>" target="_blank" rel="noopener">Focus &amp; Scope</a><?php endif;?><?php if(isurl($x[6])):?><a class="btn ghost2" href="<?=e($x[6])?>" target="_blank" rel="noopener">Panduan Penulis</a><?php endif;?></p></div></div></div><?php endforeach;?></div></section><?php endif;?>

<section id="register" class="s fade"><h2>Registrasi &amp; Biaya</h2><div class="scroll"><table><tr><th>Kategori</th><th>IDR</th><th>USD</th></tr><?php foreach(rows('fees') as $f):?><tr><td><?=e($f[0])?></td><td><?=e($f[1]??'')?></td><td><?=e($f[2]??'')?></td></tr><?php endforeach;?></table></div>
<p class="center"><a class="btn big" href="<?=$reg?>"><?=$u?'Form Pendaftaran':'Daftar Sekarang'?></a><?php if(setting('submission_url')):?> <a class="btn ghost2 big" href="<?=e(setting('submission_url'))?>" target="_blank" rel="noopener">Make Submission</a><?php endif;?></p></section>

<section class="s fade"><h2>Agenda</h2><?php agenda();?></section>

<section id="contact" class="s fade"><h2>Kontak</h2><div class="cg">
<div class="card ci"><h3>Alamat</h3><p><?=e(setting('secretariat'))?></p></div>
<div class="card ci"><h3>WhatsApp</h3><?php foreach(rows('contacts') as $c):?><p><?=e($c[0])?><br><a href="https://wa.me/<?=preg_replace('/\D/','',$c[1]??'')?>" target="_blank" rel="noopener"><?=e($c[1]??'')?></a></p><?php endforeach;?></div>
<div class="card ci"><h3>Email &amp; Web</h3><p><a href="mailto:<?=e($em)?>"><?=e($em)?></a></p><?php if(setting('website')):?><p><?=e(setting('website'))?></p><?php endif;?></div></div></section>
<?php foot();
