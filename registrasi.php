<?php require 'inc.php';$u=need('peserta');schema();
$q=$pdo->prepare("SELECT * FROM registrations WHERE user_id=?");$q->execute([$u['id']]);$R=$q->fetch()?:null;
$fees=rows('fees');$locked=$R&&$R['pay_status']==='verified';$err=[];
function isPres($c){return stripos($c,'presenter')!==false;}
function modeOf($c){return stripos($c,'online')!==false?'Online':(stripos($c,'offline')!==false?'Offline':'');}
function t($k){return trim((string)($_POST[$k]??''));}
function v($k,$d=''){global $R;return $_POST[$k]??($R[$k]??$d);}
if($_SERVER['REQUEST_METHOD']==='POST'&&!$locked){
 if(!$_POST)$err[]='Data tidak terkirim. Ukuran total file mungkin melebihi batas server (post_max_size).';
 else{chk();
  $cat=t('category');$att=t('attendance');$pres=isPres($cat);$inst=t('institution');
  $f=['prefix'=>t('prefix'),'full_name'=>t('full_name'),'country'=>t('country'),'orcid'=>t('orcid'),'phone'=>t('phone'),'category'=>$cat,'attendance'=>$att,
   'article_title'=>$pres?t('article_title'):'','coauthors'=>$pres?t('coauthors'):'','abstract'=>$pres?t('abstract'):'','keywords'=>$pres?t('keywords'):'',
   'pay_sender'=>t('pay_sender'),'pay_date'=>t('pay_date'),'dietary'=>t('dietary'),'agreed'=>1];
  $feeRow=null;foreach($fees as $x)if($x[0]===$cat)$feeRow=$x;
  if(!$feeRow)$err[]='Pilih kategori pendaftaran.';
  if(!in_array($att,['Offline','Online']))$err[]='Pilih jenis kehadiran (Offline/Online).';
  elseif($feeRow&&modeOf($cat)&&modeOf($cat)!==$att)$err[]='Kategori "'.$cat.'" hanya untuk kehadiran '.modeOf($cat).'.';
  if($f['full_name']===''||$inst===''||$f['country']===''||$f['phone']==='')$err[]='Nama lengkap, institusi, negara, dan nomor HP wajib diisi.';
  if($f['orcid']!==''&&!preg_match('/^\d{4}-\d{4}-\d{4}-\d{3}[\dX]$/',$f['orcid']))$err[]='Format ORCID iD harus 0000-0000-0000-0000.';
  if($pres){
   $wc=words($f['abstract']);$kw=array_filter(array_map('trim',explode(',',$f['keywords'])));
   if($f['article_title']==='')$err[]='Article title wajib diisi.';
   if($wc<50||$wc>250)$err[]='Abstract harus 50-250 kata (saat ini '.$wc.' kata).';
   if(count($kw)<3||count($kw)>5)$err[]='Keywords harus 3-5 kata kunci, dipisah koma.';
  }
  $d=DateTime::createFromFormat('Y-m-d',$f['pay_date']);
  if(!$d||$d->format('Y-m-d')!==$f['pay_date']||$d>new DateTime('today'))$err[]='Tanggal pembayaran tidak valid.';
  if($f['pay_sender']==='')$err[]='Nama pengirim pembayaran wajib diisi.';
  $need=['agree_info','agree_privacy'];if($pres)$need=array_merge($need,['agree_orig','agree_pub']);
  foreach($need as $k)if(empty($_POST[$k])){$err[]='Semua pernyataan persetujuan wajib dicentang.';break;}
  $art=null;$pay=null;$e='';
  if(!$err&&$pres){
   if(!empty($_FILES['article']['name'])){$art=upFile($_FILES['article'],['pdf','doc','docx'],$e,'Article file');if(!$art)$err[]=$e;}
   elseif(!($R&&$R['article_file']))$err[]='Article file wajib diunggah.';
  }
  if(!$err){
   if(!empty($_FILES['proof']['name'])){$pay=upFile($_FILES['proof'],['pdf','jpg','jpeg','png'],$e,'Proof of payment');if(!$pay)$err[]=$e;}
   elseif(!($R&&$R['pay_file']))$err[]='Proof of payment wajib diunggah.';
  }
  if($err&&$art){rmPriv($art[0]);$art=null;}
  if(!$err){
   $row=$f;$row['pay_amount']=$feeRow[1].' / '.($feeRow[2]??'');
   if($art){rmPriv($R['article_file']??'');$row['article_file']=$art[0];$row['article_name']=$art[1];}
   if(!$pres&&$R&&$R['article_file']){rmPriv($R['article_file']);$row['article_file']='';$row['article_name']='';}
   if($pay){rmPriv($R['pay_file']??'');$row['pay_file']=$pay[0];$row['pay_name']=$pay[1];}
   if($R){
    if($R['pay_status']==='rejected')$row['pay_status']='pending';
    if($R['paper_status']==='revision')$row['paper_status']='submitted';
    $sets=implode(',',array_map(function($k){return "$k=?";},array_keys($row)));
    $pdo->prepare("UPDATE registrations SET $sets WHERE id=?")->execute(array_merge(array_values($row),[$R['id']]));
   }else{
    $row['user_id']=$u['id'];$cols=implode(',',array_keys($row));$ph=implode(',',array_fill(0,count($row),'?'));
    $pdo->prepare("INSERT INTO registrations($cols) VALUES($ph)")->execute(array_values($row));
   }
   $pdo->prepare("UPDATE users SET category=?,institution=?,phone=? WHERE id=?")->execute([$cat,$inst,$f['phone'],$u['id']]);
   flash('Pendaftaran tersimpan. Panitia akan memverifikasi pembayaran Anda.');header('Location: dashboard.php');exit;
  }
 }
}
$cur=v('category',$u['category']);
head('Pendaftaran');?>
<h2>Conference Registration</h2>
<?php if($err):?><div class="msg err"><ul><?php foreach($err as $m):?><li><?=e($m)?></li><?php endforeach;?></ul></div><?php endif;?>
<?php if($locked):?><p class="msg">Pembayaran Anda sudah terverifikasi, sehingga data pendaftaran dikunci. Hubungi panitia jika perlu perubahan. <a href="dashboard.php">Kembali ke dashboard</a></p><?php else:?>
<p>Lengkapi formulir berikut dengan data yang akurat. Tanda <b>*</b> wajib diisi. Nama lengkap akan dipakai pada e-certificate.</p>
<form method="post" enctype="multipart/form-data" class="card"><?=csrf()?>
<h3>1. Participant Information</h3>
<label>Title *<select name="prefix"><?php foreach(['Prof.','Dr.','Mr.','Mrs.','Ms.','Other'] as $p):?><option <?=v('prefix')===$p?'selected':''?>><?=e($p)?></option><?php endforeach;?></select></label>
<label>Full name (as on certificate) *<input name="full_name" required value="<?=e(v('full_name',$u['name']))?>"></label>
<label>Email<input value="<?=e($u['email'])?>" disabled></label>
<label>Institution / Affiliation *<input name="institution" required value="<?=e($_POST['institution']??$u['institution'])?>"></label>
<label>Country *<input name="country" required value="<?=e(v('country','Indonesia'))?>"></label>
<label>Phone / WhatsApp *<input name="phone" required value="<?=e(v('phone',$u['phone']))?>"></label>
<label>ORCID iD (optional)<input name="orcid" placeholder="0000-0000-0000-0000" value="<?=e(v('orcid'))?>"></label>
<label>Dietary / special needs (optional)<input name="dietary" value="<?=e(v('dietary'))?>"></label>
<h3>2. Registration Category &amp; Attendance</h3>
<label>Registration category *<select name="category" id="cat" required><option value="">-- select --</option><?php foreach($fees as $f):?><option value="<?=e($f[0])?>" data-fee="<?=e($f[1].' / '.($f[2]??''))?>" <?=$cur===$f[0]?'selected':''?>><?=e($f[0].' ('.$f[1].')')?></option><?php endforeach;?></select></label>
<div><b>Attendance participation *</b><label class="chk"><input type="radio" name="attendance" value="Offline" required <?=v('attendance')==='Offline'?'checked':''?>> Offline</label><label class="chk"><input type="radio" name="attendance" value="Online" <?=v('attendance')==='Online'?'checked':''?>> Online</label></div>
<div id="paper" class="full sub"><h3 class="full">3. Paper Submission (presenters)</h3>
<label class="full">Article title *<input name="article_title" data-need="1" value="<?=e(v('article_title'))?>"></label>
<label class="full">Co-authors (name &amp; affiliation, one per line; optional)<textarea name="coauthors" rows="3"><?=e(v('coauthors'))?></textarea></label>
<label class="full">Abstract * <small>(50-250 words, no references) <span id="wc"></span></small><textarea name="abstract" id="abs" rows="8" data-need="1"><?=e(v('abstract'))?></textarea></label>
<label>Keywords * <small>(3-5, comma separated)</small><input name="keywords" data-need="1" value="<?=e(v('keywords'))?>"></label>
<label>Article file * <small>(PDF/DOC/DOCX, max 10 MB)</small><input type="file" name="article" accept=".pdf,.doc,.docx" <?=($R&&$R['article_file'])?'':'data-need="1"'?>><?php if($R&&$R['article_file']):?><small>File saat ini: <a href="file.php?id=<?=$R['id']?>&k=article"><?=e($R['article_name'])?></a> (kosongkan jika tidak diganti)</small><?php endif;?></label>
<label class="chk full"><input type="checkbox" class="pres-only" name="agree_orig" <?=($_POST?!empty($_POST['agree_orig']):($R&&$R['article_title']))?'checked':''?>> I declare that this paper is original, has not been published, and is not under review elsewhere.</label>
<label class="chk full"><input type="checkbox" class="pres-only" name="agree_pub" <?=($_POST?!empty($_POST['agree_pub']):($R&&$R['article_title']))?'checked':''?>> I agree that the accepted paper may be published in the conference proceedings / partner journals.</label></div>
<h3>4. Payment</h3>
<?php if(setting('payment_info')):?><div class="panel full"><b>Payment instructions</b><br><?=nl2br(e(setting('payment_info')))?></div><?php endif;?>
<p class="full">Registration fee: <b id="fee">-</b></p>
<label>Sender name *<input name="pay_sender" required value="<?=e(v('pay_sender'))?>"></label>
<label>Payment date *<input type="date" name="pay_date" required max="<?=date('Y-m-d')?>" value="<?=e(v('pay_date'))?>"></label>
<label>Proof of payment * <small>(PDF/JPG/PNG, max 10 MB)</small><input type="file" name="proof" accept=".pdf,.jpg,.jpeg,.png" <?=($R&&$R['pay_file'])?'':'required'?>><?php if($R&&$R['pay_file']):?><small>File saat ini: <a href="file.php?id=<?=$R['id']?>&k=pay"><?=e($R['pay_name'])?></a> (kosongkan jika tidak diganti)</small><?php endif;?></label>
<h3>5. Declaration</h3>
<label class="chk full"><input type="checkbox" name="agree_info" required <?=($_POST?!empty($_POST['agree_info']):$R)?'checked':''?>> I confirm that all information provided is accurate.</label>
<label class="chk full"><input type="checkbox" name="agree_privacy" required <?=($_POST?!empty($_POST['agree_privacy']):$R)?'checked':''?>> I consent to the processing of my personal data for conference administration, certificates, and publication purposes.</label>
<button class="btn"><?=$R?'Update Registration':'Submit Registration'?></button></form>
<script>(function(){var c=document.getElementById('cat'),p=document.getElementById('paper'),fee=document.getElementById('fee'),ab=document.getElementById('abs'),wc=document.getElementById('wc'),rd=document.querySelectorAll('input[name=attendance]');
function upd(){var o=c.options[c.selectedIndex],n=o?o.value:'',pr=/presenter/i.test(n),m=/online/i.test(n)?'Online':(/offline/i.test(n)?'Offline':'');
p.style.display=pr?'':'none';p.querySelectorAll('[data-need]').forEach(function(e){e.required=pr&&e.dataset.need==='1'});p.querySelectorAll('.pres-only').forEach(function(e){e.required=pr});
rd.forEach(function(r){r.disabled=!!m&&r.value!==m;if(m&&r.value===m)r.checked=true});fee.textContent=(o&&o.dataset.fee)?o.dataset.fee:'-'}
function w(){var n=ab.value.trim().split(/\s+/).filter(Boolean).length;wc.textContent=n+' / 250 words';wc.style.color=n>250?'#b42323':''}
c.addEventListener('change',upd);ab.addEventListener('input',w);upd();w()})();</script>
<?php endif;foot();
