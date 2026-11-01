<?php require '../inc.php';$u=need('admin');schema();chk();$p=$_GET['p']??'peserta';
function clean($s){return trim(str_replace(['|',"\r","\n"],' ',(string)$s));}
function jclean($s){return trim(str_replace(['|',"\r","\n"],' ',(string)$s));}
function jurl($s){$s=trim((string)$s);if($s!==''&&!preg_match('#^https?://#i',$s))$s='https://'.$s;return jclean($s);}
function jimg($v){if($v&&!isurl($v))rmPhoto($v);}
function saveJr($rs){global $pdo;$o=[];foreach($rs as $r){$r=array_pad($r,9,'');$o[]=implode('|',array_slice($r,0,9));}$pdo->prepare("REPLACE INTO settings(k,v) VALUES('journals',?)")->execute([implode("\n",$o)]);}
function jrow(){return [jclean($_POST['name']??''),jurl($_POST['url']??''),jclean($_POST['accr']??''),jclean($_POST['publisher']??''),jclean($_POST['issn']??''),jurl($_POST['scope']??''),jurl($_POST['guide']??''),jurl($_POST['cover']??''),jclean($_POST['note']??'')];}
function saveSp($rs){global $pdo;$o=[];foreach($rs as $r){$r=array_pad($r,4,'');$o[]=implode('|',array_slice($r,0,4));}$pdo->prepare("REPLACE INTO settings(k,v) VALUES('speakers',?)")->execute([implode("\n",$o)]);}
function rmPhoto($fn){if($fn)@unlink(__DIR__.'/../uploads/'.basename($fn));}
function upPhoto($f,&$err){$err='';if(!$f||$f['error']!==0||$f['size']>2097152){$err='Gagal: gunakan JPG/PNG/WEBP maksimal 2 MB.';return '';}
 $im=@getimagesize($f['tmp_name']);$ext=$im?([IMAGETYPE_JPEG=>'jpg',IMAGETYPE_PNG=>'png',IMAGETYPE_WEBP=>'webp'][$im[2]]??null):null;
 if(!$ext){$err='Gagal: file bukan gambar JPG/PNG/WEBP.';return '';}
 $d=__DIR__.'/../uploads/';if(!is_dir($d))mkdir($d,0755,true);$fn='sp_'.bin2hex(random_bytes(8)).'.'.$ext;
 if(!move_uploaded_file($f['tmp_name'],$d.$fn)){$err='Gagal menyimpan file (cek izin folder uploads).';return '';}return $fn;}
if($p==='exportreg'){header('Content-Type: text/csv');header('Content-Disposition: attachment; filename=registrasi.csv');$o=fopen('php://output','w');
 fputcsv($o,['No','Title','Nama','Email','Institusi','Negara','HP','ORCID','Kategori','Kehadiran','Judul Artikel','Keywords','Biaya','Tgl Bayar','Pengirim','Status Bayar','Status Naskah','Diet','Daftar']);
 foreach($pdo->query("SELECT r.id,r.prefix,r.full_name,u.email,u.institution,r.country,r.phone,r.orcid,r.category,r.attendance,r.article_title,r.keywords,r.pay_amount,r.pay_date,r.pay_sender,r.pay_status,r.paper_status,r.dietary,r.created_at FROM registrations r JOIN users u ON u.id=r.user_id ORDER BY r.id") as $r)fputcsv($o,$r);exit;}
if($p==='export'){header('Content-Type: text/csv');header('Content-Disposition: attachment; filename=peserta.csv');$o=fopen('php://output','w');fputcsv($o,['ID','Kategori','Nama','Email','Institusi','HP','Status','Daftar']);
 foreach($pdo->query("SELECT id,category,name,email,institution,phone,status,created_at FROM users WHERE role='peserta'") as $r)fputcsv($o,$r);exit;}
if($_SERVER['REQUEST_METHOD']==='POST'){$a=$_POST['a']??'';$id=(int)($_POST['id']??0);
 if($a==='status'){$st=in_array($_POST['status'],['pending','confirmed','rejected'])?$_POST['status']:'pending';$pdo->prepare("UPDATE users SET status=? WHERE id=? AND role='peserta'")->execute([$st,$id]);}
 if($a==='del'){$q=$pdo->prepare("SELECT article_file,pay_file FROM registrations WHERE user_id=?");$q->execute([$id]);if($r0=$q->fetch()){rmPriv($r0['article_file']);rmPriv($r0['pay_file']);}
  $pdo->prepare("DELETE FROM registrations WHERE user_id=?")->execute([$id]);$pdo->prepare("DELETE FROM users WHERE id=? AND role='peserta'")->execute([$id]);}
 if($a==='jradd'){$r=jrow();
  if($r[0]==='')$msg='Nama jurnal wajib diisi.';
  else{$rs=rows('journals');$rs[]=$r;saveJr($rs);$msg='Jurnal ditambahkan.';}}
 if(in_array($a,['jredit','jrdel'])){$rs=rows('journals');$i=(int)($_POST['i']??-1);
  if(isset($rs[$i])){$rs[$i]=array_pad($rs[$i],9,'');
   if($a==='jredit'){$r=jrow();if($r[0]==='')$msg='Nama jurnal wajib diisi.';else{if($rs[$i][7]!==$r[7])jimg($rs[$i][7]);$rs[$i]=$r;saveJr($rs);$msg='Data jurnal diperbarui.';}}
   if($a==='jrdel'){jimg($rs[$i][7]);array_splice($rs,$i,1);saveJr($rs);$msg='Jurnal dihapus.';}
  }}
 if($a==='uedit'){$n=trim($_POST['name']??'');$m=strtolower(trim($_POST['email']??''));$back='?p=edit&id='.$id;
  $q=$pdo->prepare("SELECT id FROM users WHERE email=? AND id<>?");$q->execute([$m,$id]);
  if($n===''||!filter_var($m,FILTER_VALIDATE_EMAIL))$msg='Nama dan email valid wajib diisi.';
  elseif($q->fetch())$msg='Email sudah dipakai akun lain.';
  else{$sv=in_array($_POST['status']??'',['pending','confirmed','rejected'])?$_POST['status']:'pending';
   $pdo->prepare("UPDATE users SET name=?,email=?,institution=?,phone=?,category=?,status=? WHERE id=? AND role='peserta'")->execute([$n,$m,trim($_POST['institution']??''),trim($_POST['phone']??''),trim($_POST['category']??''),$sv,$id]);
   $msg='Akun diperbarui.';$back='?p=pengguna';}}
 if($a==='pwreset'){$back='?p=edit&id='.$id;$pw=$_POST['password']??'';
  if($pw==='')$pw=substr(strtr(base64_encode(random_bytes(9)),'+/=','xyz'),0,10);
  if(strlen($pw)<6)$msg='Password minimal 6 karakter.';
  else{$pdo->prepare("UPDATE users SET password=? WHERE id=? AND role='peserta'")->execute([password_hash($pw,PASSWORD_DEFAULT),$id]);
   $msg='Password direset. Password baru: '.$pw.' (catat dan sampaikan ke peserta; tidak ditampilkan lagi).';}}
 if($a==='regsave'){$back='?p=detail&id='.$id;$ps=in_array($_POST['pay_status']??'',['pending','verified','rejected'])?$_POST['pay_status']:'pending';
  $pp=in_array($_POST['paper_status']??'',['submitted','under_review','revision','accepted','rejected'])?$_POST['paper_status']:'submitted';
  $pdo->prepare("UPDATE registrations SET pay_status=?,paper_status=?,admin_note=? WHERE id=?")->execute([$ps,$pp,trim($_POST['admin_note']??''),$id]);
  $q=$pdo->prepare("SELECT user_id FROM registrations WHERE id=?");$q->execute([$id]);
  if($uid=$q->fetchColumn())$pdo->prepare("UPDATE users SET status=? WHERE id=?")->execute([['pending'=>'pending','verified'=>'confirmed','rejected'=>'rejected'][$ps],$uid]);}
 if($a==='agenda')$pdo->prepare("INSERT INTO agenda(day,time,title,speaker,room) VALUES(?,?,?,?,?)")->execute([$_POST['day'],$_POST['time'],$_POST['title'],$_POST['speaker'],$_POST['room']]);
 if($a==='spadd'){$n=clean($_POST['name']??'');
  if($n==='')$msg='Nama pembicara wajib diisi.';
  else{$rs=rows('speakers');$ph='';$er='';if(!empty($_FILES['photo']['name']))$ph=upPhoto($_FILES['photo'],$er);
   $rs[]=[clean($_POST['role']??''),$n,clean($_POST['affil']??''),$ph];saveSp($rs);$msg='Pembicara ditambahkan.'.($er?' '.$er:'');}}
 if(in_array($a,['spedit','spdel','photo','rmphoto'])){$rs=rows('speakers');$i=(int)($_POST['i']??-1);
  if(isset($rs[$i])){$rs[$i]=array_pad($rs[$i],4,'');
   if($a==='spedit'){$n=clean($_POST['name']??'');if($n==='')$msg='Nama wajib diisi.';else{$rs[$i][0]=clean($_POST['role']??'');$rs[$i][1]=$n;$rs[$i][2]=clean($_POST['affil']??'');saveSp($rs);$msg='Data pembicara diperbarui.';}}
   if($a==='spdel'){rmPhoto($rs[$i][3]);array_splice($rs,$i,1);saveSp($rs);$msg='Pembicara dihapus.';}
   if($a==='rmphoto'){rmPhoto($rs[$i][3]);$rs[$i][3]='';saveSp($rs);$msg='Foto dihapus.';}
   if($a==='photo'){$er='';$fn=upPhoto($_FILES['photo']??null,$er);if($fn===''){$msg=$er;}else{rmPhoto($rs[$i][3]);$rs[$i][3]=$fn;saveSp($rs);$msg='Foto diunggah.';}}
  }}
 if($a==='delag')$pdo->prepare("DELETE FROM agenda WHERE id=?")->execute([$id]);
 if($a==='set')foreach(['name','tagline','date','time','location','about','website','payment_info','email','submission_url','map_url','supporters','speakers','fees','benefits','contacts','secretariat'] as $k)$pdo->prepare("REPLACE INTO settings(k,v) VALUES(?,?)")->execute([$k,trim($_POST[$k]??'')]);
 flash($msg??'Perubahan disimpan.');header('Location: '.($back??'?p='.$p));exit;}
head('Admin');?>
<h2>Panel Admin</h2><div class="tabs"><a href="?p=peserta">Peserta</a><a href="?p=pengguna">Pengguna</a><a href="?p=pembicara">Pembicara</a><a href="?p=jurnal">Jurnal</a><a href="?p=agenda">Agenda</a><a href="?p=setting">Pengaturan</a><a href="?p=exportreg">Ekspor CSV</a></div>
<?php if($p==='pengguna'):$r=$pdo->query("SELECT * FROM users WHERE role='peserta' ORDER BY id DESC")->fetchAll();?>
<p>Total pengguna (akun): <b><?=count($r)?></b> &middot; <a href="?p=export">Ekspor akun (CSV)</a></p><div class="scroll"><table><tr><th>ID Akun</th><th>Nama</th><th>Email</th><th>Kategori</th><th>Institusi</th><th>HP</th><th>Status</th><th></th></tr>
<?php foreach($r as $x):?><tr><td>U<?=str_pad($x['id'],4,'0',STR_PAD_LEFT)?></td><td><?=e($x['name'])?></td><td><?=e($x['email'])?></td><td><?=e($x['category'])?></td><td><?=e($x['institution'])?></td><td><?=e($x['phone'])?></td>
<td><form method="post" class="inline"><?=csrf()?><input type="hidden" name="a" value="status"><input type="hidden" name="id" value="<?=$x['id']?>">
<select name="status" onchange="this.form.submit()"><?php foreach(['pending','confirmed','rejected'] as $s):?><option <?=$x['status']===$s?'selected':''?>><?=$s?></option><?php endforeach;?></select></form></td>
<td><a href="?p=edit&id=<?=$x['id']?>">Edit</a> &nbsp; <form method="post" class="inline" onsubmit="return confirm('Hapus peserta?')"><?=csrf()?><input type="hidden" name="a" value="del"><input type="hidden" name="id" value="<?=$x['id']?>"><button class="link">Hapus</button></form></td></tr><?php endforeach;?></table></div>
<?php elseif($p==='edit'):$eid=(int)($_GET['id']??0);$q=$pdo->prepare("SELECT * FROM users WHERE id=? AND role='peserta'");$q->execute([$eid]);$x=$q->fetch();
if(!$x):?><p>Akun tidak ditemukan. <a href="?p=pengguna">Kembali</a></p>
<?php else:$cats=array_column(rows('fees'),0);if($x['category']&&!in_array($x['category'],$cats))$cats[]=$x['category'];?>
<p><a href="?p=pengguna">&larr; Kembali ke daftar pengguna</a></p><h3>Edit Akun: <?=e($x['name'])?></h3>
<form method="post" class="card"><?=csrf()?><input type="hidden" name="a" value="uedit"><input type="hidden" name="id" value="<?=$x['id']?>">
<label>Nama<input name="name" required value="<?=e($x['name'])?>"></label>
<label>Email (dipakai untuk login)<input type="email" name="email" required value="<?=e($x['email'])?>"></label>
<label>Institusi<input name="institution" value="<?=e($x['institution'])?>"></label>
<label>No. HP<input name="phone" value="<?=e($x['phone'])?>"></label>
<label>Kategori<select name="category"><option value="">-</option><?php foreach($cats as $c):?><option <?=$x['category']===$c?'selected':''?>><?=e($c)?></option><?php endforeach;?></select></label>
<label>Status<select name="status"><?php foreach(['pending','confirmed','rejected'] as $s):?><option <?=$x['status']===$s?'selected':''?>><?=$s?></option><?php endforeach;?></select></label>
<button class="btn">Simpan Perubahan</button></form>
<h3>Reset Password</h3>
<form method="post" class="card" onsubmit="return confirm('Reset password peserta ini?')"><?=csrf()?><input type="hidden" name="a" value="pwreset"><input type="hidden" name="id" value="<?=$x['id']?>">
<label class="full">Password baru (kosongkan untuk dibuatkan password acak)<input name="password" type="text" autocomplete="off" minlength="6"></label>
<button class="btn">Reset Password</button></form><?php endif;?>
<?php elseif($p==='peserta'):$qs=trim($_GET['q']??'');$w='';$par=[];
if($qs!==''){$w="WHERE (r.full_name LIKE ? OR u.email LIKE ? OR u.institution LIKE ? OR r.article_title LIKE ? OR r.country LIKE ?)";$par=array_fill(0,5,'%'.$qs.'%');}
$sq=$pdo->prepare("SELECT r.*,u.email,u.institution FROM registrations r JOIN users u ON u.id=r.user_id $w ORDER BY r.id DESC");$sq->execute($par);$rg=$sq->fetchAll();
$belum=(int)$pdo->query("SELECT COUNT(*) FROM users u WHERE u.role='peserta' AND NOT EXISTS(SELECT 1 FROM registrations r WHERE r.user_id=u.id)")->fetchColumn();?>
<p>Total peserta terdaftar: <b><?=count($rg)?></b><?php if($belum):?> &middot; <a href="?p=pengguna"><?=$belum?> akun belum melengkapi pendaftaran</a><?php endif;?> &middot; <a href="?p=exportreg">Ekspor CSV</a></p>
<form method="get" class="inline"><input type="hidden" name="p" value="peserta"><input name="q" value="<?=e($qs)?>" placeholder="Cari nama, email, institusi, negara, judul..." style="max-width:380px;display:inline-block"> <button class="btn">Cari</button></form>
<div class="scroll"><table><tr><th>No. Reg</th><th>Nama</th><th>Email / HP</th><th>Institusi, Negara</th><th>Kategori</th><th>Hadir</th><th>Judul Naskah</th><th>Bayar</th><th>Naskah</th><th></th></tr>
<?php foreach($rg as $x):?><tr><td>R<?=str_pad($x['id'],4,'0',STR_PAD_LEFT)?></td><td><?=e($x['prefix'].' '.$x['full_name'])?></td><td><?=e($x['email'])?><br><small><?=e($x['phone'])?></small></td><td><?=e($x['institution'])?><br><small><?=e($x['country'])?></small></td><td><?=e($x['category'])?></td><td><?=e($x['attendance'])?></td>
<td><?=$x['article_title']?e($x['article_title']):'-'?></td><td><span class="badge <?=e($x['pay_status'])?>"><?=e($x['pay_status'])?></span></td><td><?=$x['article_title']?'<span class="badge '.e($x['paper_status']).'">'.e($x['paper_status']).'</span>':'-'?></td>
<td><a href="?p=detail&id=<?=$x['id']?>">Detail</a></td></tr><?php endforeach;?></table></div>
<?php elseif($p==='detail'):$did=(int)($_GET['id']??0);$q=$pdo->prepare("SELECT r.*,u.email,u.institution FROM registrations r JOIN users u ON u.id=r.user_id WHERE r.id=?");$q->execute([$did]);$x=$q->fetch();
if(!$x):?><p>Data tidak ditemukan. <a href="?p=peserta">Kembali</a></p>
<?php else:$info=['Title'=>e($x['prefix']),'Nama lengkap'=>e($x['full_name']),'Email'=>e($x['email']),'Institusi'=>e($x['institution']),'Negara'=>e($x['country']),'No. HP / WhatsApp'=>e($x['phone']),'ORCID iD'=>e($x['orcid']?:'-'),
 'Kategori'=>e($x['category']),'Kehadiran'=>e($x['attendance']),'Kebutuhan khusus / diet'=>e($x['dietary']?:'-'),
 'Judul artikel'=>e($x['article_title']?:'-'),'Co-authors'=>$x['coauthors']?nl2br(e($x['coauthors'])):'-','Keywords'=>e($x['keywords']?:'-'),'Abstract'=>$x['abstract']?nl2br(e($x['abstract'])).' <small>('.words($x['abstract']).' kata)</small>':'-',
 'File naskah'=>$x['article_file']?'<a href="../file.php?id='.$x['id'].'&k=article">'.e($x['article_name']).'</a>':'-',
 'Biaya'=>e($x['pay_amount']),'Tanggal bayar'=>e($x['pay_date']),'Nama pengirim'=>e($x['pay_sender']),'Bukti bayar'=>$x['pay_file']?'<a href="../file.php?id='.$x['id'].'&k=pay">'.e($x['pay_name']).'</a>':'-',
 'Mendaftar'=>e($x['created_at']),'Terakhir diubah'=>e($x['updated_at'])];?>
<p><a href="?p=peserta">&larr; Kembali ke daftar peserta</a> &middot; <a href="?p=edit&id=<?=$x['user_id']?>">Edit akun / reset password</a></p>
<h3>R<?=str_pad($x['id'],4,'0',STR_PAD_LEFT)?> &middot; <?=e($x['prefix'].' '.$x['full_name'])?></h3>
<div class="scroll"><table><?php foreach($info as $k=>$v):?><tr><th style="width:220px"><?=e($k)?></th><td><?=$v?></td></tr><?php endforeach;?></table></div>
<h3>Verifikasi &amp; Status</h3>
<form method="post" class="card"><?=csrf()?><input type="hidden" name="a" value="regsave"><input type="hidden" name="id" value="<?=$x['id']?>">
<label>Status pembayaran<select name="pay_status"><?php foreach(['pending','verified','rejected'] as $s):?><option <?=$x['pay_status']===$s?'selected':''?>><?=$s?></option><?php endforeach;?></select></label>
<?php if($x['article_title']):?><label>Status naskah<select name="paper_status"><?php foreach(['submitted','under_review','revision','accepted','rejected'] as $s):?><option <?=$x['paper_status']===$s?'selected':''?>><?=$s?></option><?php endforeach;?></select></label><?php else:?><input type="hidden" name="paper_status" value="<?=e($x['paper_status'])?>"><?php endif;?>
<label class="full">Catatan untuk peserta<textarea name="admin_note" rows="3"><?=e($x['admin_note'])?></textarea></label><button class="btn">Simpan</button></form><?php endif;?>
<?php elseif($p==='jurnal'):?>
<h3>Tambah Jurnal</h3><form method="post" class="card"><?=csrf()?><input type="hidden" name="a" value="jradd">
<label>Nama jurnal *<input name="name" required></label><label>Link / URL jurnal<input name="url" placeholder="https://..."></label>
<label>Akreditasi<input name="accr" placeholder="SINTA 2"></label><label>Penerbit<input name="publisher"></label>
<label>ISSN<input name="issn" placeholder="p-ISSN 0000-0000 / e-ISSN 0000-0000"></label><label>Link Focus &amp; Scope<input name="scope" placeholder="https://.../about/editorialPolicies#focusAndScope"></label>
<label>Link panduan penulis<input name="guide" placeholder="https://..."></label><label>Link gambar cover jurnal<input name="cover" placeholder="https://.../cover.jpg"></label>
<label class="full">Catatan (biaya publikasi, jadwal terbit, dll.)<input name="note"></label><button class="btn">Tambah</button></form>
<h3>Daftar Jurnal</h3><?php foreach(rows('journals') as $i=>$j):$j=array_pad($j,9,'');?>
<details class="card reg"><summary><b><?=e($j[0])?></b><?=$j[2]?' &middot; '.e($j[2]):''?><?=$j[1]?' &middot; <small>'.e($j[1]).'</small>':''?></summary>
<form method="post" class="sub"><?=csrf()?><input type="hidden" name="a" value="jredit"><input type="hidden" name="i" value="<?=$i?>">
<label>Nama jurnal *<input name="name" required value="<?=e($j[0])?>"></label><label>Link / URL jurnal<input name="url" value="<?=e($j[1])?>"></label>
<label>Akreditasi<input name="accr" value="<?=e($j[2])?>"></label><label>Penerbit<input name="publisher" value="<?=e($j[3])?>"></label>
<label>ISSN<input name="issn" value="<?=e($j[4])?>"></label><label>Link Focus &amp; Scope<input name="scope" value="<?=isurl($j[5])?e($j[5]):''?>" placeholder="https://..."></label>
<label>Link panduan penulis<input name="guide" value="<?=e($j[6])?>"></label><label>Link gambar cover jurnal<input name="cover" value="<?=isurl($j[7])?e($j[7]):''?>" placeholder="https://.../cover.jpg"></label>
<label class="full">Catatan<input name="note" value="<?=e($j[8])?>"></label>
<?php if(isurl($j[7])):?><div class="full"><small>Pratinjau cover:</small><br><img class="thumb" style="width:80px;height:auto;aspect-ratio:3/4" src="<?=e($j[7])?>" alt="" referrerpolicy="no-referrer"></div><?php endif;?>
<div class="full"><button class="btn">Simpan</button></div></form>
<form method="post" onsubmit="return confirm('Hapus jurnal ini?')" style="margin-top:10px"><?=csrf()?><input type="hidden" name="a" value="jrdel"><input type="hidden" name="i" value="<?=$i?>"><button class="link">Hapus jurnal</button></form></details><?php endforeach;?>
<?php elseif($p==='pembicara'):?>
<h3>Tambah Pembicara</h3><form method="post" enctype="multipart/form-data" class="card"><?=csrf()?><input type="hidden" name="a" value="spadd">
<label>Peran<input name="role" list="roles" placeholder="Keynote Speaker"></label><datalist id="roles"><option>Open Speech</option><option>Keynote Speaker</option><option>Invited Speaker</option><option>Chairman of the Committee</option></datalist>
<label>Nama (beserta gelar)<input name="name" required></label><label>Afiliasi / Institusi<input name="affil"></label>
<label>Foto (opsional)<input type="file" name="photo" accept="image/jpeg,image/png,image/webp"></label><button class="btn">Tambah</button></form>
<h3>Daftar Pembicara</h3><p>Edit nama, peran, dan afiliasi langsung di tiap baris lalu klik Simpan. Foto: JPG/PNG/WEBP, maks. 2 MB, disarankan persegi.</p>
<?php foreach(rows('speakers') as $i=>$s):?><div class="sprow"><?php if(!empty($s[3])):?><img class="thumb" src="../uploads/<?=e($s[3])?>" alt=""><?php else:?><span class="thumb"></span><?php endif;?>
<form method="post" class="edit"><?=csrf()?><input type="hidden" name="a" value="spedit"><input type="hidden" name="i" value="<?=$i?>">
<input name="role" value="<?=e($s[0])?>" placeholder="Peran"><input name="name" value="<?=e($s[1]??'')?>" placeholder="Nama" required><input name="affil" value="<?=e($s[2]??'')?>" placeholder="Afiliasi"><button class="btn">Simpan</button></form>
<div class="acts"><form method="post" enctype="multipart/form-data"><?=csrf()?><input type="hidden" name="a" value="photo"><input type="hidden" name="i" value="<?=$i?>"><input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required><button class="btn">Unggah foto</button></form>
<?php if(!empty($s[3])):?><form method="post"><?=csrf()?><input type="hidden" name="a" value="rmphoto"><input type="hidden" name="i" value="<?=$i?>"><button class="link">Hapus foto</button></form><?php endif;?>
<form method="post" onsubmit="return confirm('Hapus pembicara ini?')"><?=csrf()?><input type="hidden" name="a" value="spdel"><input type="hidden" name="i" value="<?=$i?>"><button class="link">Hapus pembicara</button></form></div></div><?php endforeach;?>
<?php elseif($p==='agenda'):?><form method="post" class="card"><?=csrf()?><input type="hidden" name="a" value="agenda">
<label>Hari/Tanggal<input name="day" required placeholder="Hari 1 - 10 Nov"></label><label>Waktu<input name="time" required placeholder="09:00-10:00"></label>
<label>Judul Sesi<input name="title" required></label><label>Pembicara<input name="speaker"></label><label>Ruang<input name="room"></label><button class="btn">Tambah</button></form>
<div class="scroll"><table><tr><th>Hari</th><th>Waktu</th><th>Sesi</th><th>Pembicara</th><th>Ruang</th><th></th></tr>
<?php foreach($pdo->query("SELECT * FROM agenda ORDER BY day,time") as $x):?><tr><td><?=e($x['day'])?></td><td><?=e($x['time'])?></td><td><?=e($x['title'])?></td><td><?=e($x['speaker'])?></td><td><?=e($x['room'])?></td>
<td><form method="post" class="inline"><?=csrf()?><input type="hidden" name="a" value="delag"><input type="hidden" name="id" value="<?=$x['id']?>"><button class="link">Hapus</button></form></td></tr><?php endforeach;?></table></div>
<?php else:?><form method="post" class="card"><?=csrf()?><input type="hidden" name="a" value="set">
<label>Nama Konferensi<input name="name" value="<?=e(setting('name'))?>"></label><label>Tagline<input name="tagline" value="<?=e(setting('tagline'))?>"></label>
<label>Tanggal<input name="date" value="<?=e(setting('date'))?>"></label><label>Waktu<input name="time" value="<?=e(setting('time'))?>"></label><label>Lokasi<input name="location" value="<?=e(setting('location'))?>"></label><label>Website<input name="website" value="<?=e(setting('website'))?>"></label><label>Email resmi<input name="email" value="<?=e(setting('email'))?>"></label><label>Link Make Submission (kosongkan jika tidak dipakai)<input name="submission_url" value="<?=e(setting('submission_url'))?>" placeholder="https://..."></label><label>URL embed Google Maps (kosongkan untuk peta Hotel Pangeran)<input name="map_url" value="<?=e(setting('map_url'))?>"></label>
<label>Tentang<textarea name="about" rows="6"><?=e(setting('about'))?></textarea></label><label>Pembicara (Peran|Nama|Afiliasi|file foto &mdash; bagian ke-4 diisi otomatis, jangan diubah)<textarea name="speakers" rows="8"><?=e(setting('speakers'))?></textarea></label>
<label>Biaya (Kategori|IDR|USD) &mdash; juga jadi pilihan kategori pendaftaran<textarea name="fees" rows="5"><?=e(setting('fees'))?></textarea></label>
<label>Instruksi pembayaran (rekening bank, atas nama, dll; tampil di form registrasi)<textarea name="payment_info" rows="4"><?=e(setting('payment_info'))?></textarea></label>
<label>Dukungan institusi (satu nama per baris, kosongkan untuk menyembunyikan)<textarea name="supporters" rows="5"><?=e(setting('supporters'))?></textarea></label>
<label>Benefit (satu per baris)<textarea name="benefits" rows="4"><?=e(setting('benefits'))?></textarea></label>
<label>Kontak (Nama|Nomor WhatsApp)<textarea name="contacts" rows="4"><?=e(setting('contacts'))?></textarea></label>
<label>Sekretariat<textarea name="secretariat" rows="3"><?=e(setting('secretariat'))?></textarea></label><button class="btn">Simpan</button></form><?php endif;foot();
