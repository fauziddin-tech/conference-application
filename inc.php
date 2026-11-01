<?php
session_start();
if(!file_exists(__DIR__.'/config.php')){header('Location: install/');exit;}
require __DIR__.'/config.php';
$pdo=new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
function e($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function setting($k){global $pdo;static $c=null;if($c===null)$c=$pdo->query("SELECT k,v FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);return $c[$k]??'';}
function rows($k){$o=[];foreach(preg_split('/\R/',setting($k)) as $l){$l=trim($l);if($l!=='')$o[]=array_map('trim',explode('|',$l));}return $o;}
function csrf(){if(empty($_SESSION['t']))$_SESSION['t']=bin2hex(random_bytes(16));return '<input type="hidden" name="t" value="'.$_SESSION['t'].'">';}
function chk(){if($_SERVER['REQUEST_METHOD']==='POST'&&!hash_equals($_SESSION['t']??'',$_POST['t']??''))die('Token tidak valid, muat ulang halaman.');}
function user(){global $pdo;if(empty($_SESSION['uid']))return null;$s=$pdo->prepare("SELECT * FROM users WHERE id=?");$s->execute([$_SESSION['uid']]);return $s->fetch()?:null;}
function need($role=null){$u=user();if(!$u){header('Location: '.BASE.'login.php');exit;}if($role&&$u['role']!==$role){http_response_code(403);die('Akses ditolak');}return $u;}
function flash($m=null){if($m!==null){$_SESSION['f']=$m;return;}$f=$_SESSION['f']??'';unset($_SESSION['f']);return $f;}
function brand(){$n=setting('name');return preg_match('/\(([^)]+)\)/',$n,$m)?$m[1]:$n;}
function head($t){$u=user();$H=BASE;?><!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e($t)?> - <?=e(brand())?></title><link rel="stylesheet" href="<?=BASE?>style.css?v=<?=@filemtime(__DIR__."/style.css")?>"></head><body id="top">
<nav><a class="brand" href="<?=$H?>"><?=e(brand())?></a><button class="burger" type="button" aria-label="Menu" onclick="this.parentNode.classList.toggle('open')">&#9776;</button><div class="links">
<a href="<?=$H?>#about">About</a><a href="<?=$H?>#speakers">Speakers</a><a href="<?=$H?>#venue">Venue</a><?php if(setting('journals')):?><a href="<?=$H?>#publication">Publication</a><?php endif;?><a href="<?=$H?>#register">Register</a><a href="<?=$H?>#contact">Contact</a><?php if(setting('submission_url')):?><a href="<?=e(setting('submission_url'))?>" target="_blank" rel="noopener">Make Submission</a><?php endif;?>
<?php if($u):?><?php if($u['role']==='admin'):?><a href="<?=$H?>admin/">Admin</a><?php else:?><a href="<?=$H?>dashboard.php">Dashboard</a><?php endif;?><a href="<?=$H?>logout.php">Keluar (<?=e($u['name'])?>)</a>
<?php else:?><a href="<?=$H?>login.php">Masuk</a><a class="btn" href="<?=$H?>register.php">Daftar</a><?php endif;?></div></nav>
<?php if(!$u):?><div id="regm" class="modal" hidden><div class="mbox"><button type="button" class="mx" aria-label="Tutup" onclick="document.getElementById('regm').hidden=true">&times;</button>
<h3>Buat Akun Peserta</h3><p><small>Langkah 1 dari 2. Setelah akun dibuat, Anda langsung diarahkan ke formulir pendaftaran konferensi.</small></p>
<p class="msg err merr" hidden></p>
<form method="post" action="<?=$H?>register.php"><?=csrf()?>
<label>Nama Lengkap<input name="name" required autocomplete="name"></label>
<label>Email<input type="email" name="email" required autocomplete="off"></label>
<label>Password (min. 6 karakter)<input type="password" name="password" required minlength="6" autocomplete="new-password"></label>
<button class="btn">Buat Akun &amp; Lanjutkan</button><p><small>Sudah punya akun? <a href="<?=$H?>login.php">Masuk</a></small></p></form></div></div><?php endif;?><main>
<?php if($f=flash()):?><p class="msg"><?=e($f)?></p><?php endif;}
function foot(){$H=BASE;$em=setting('email')?:'picoe@universitaspahlawan.ac.id';?></main><footer><div class="fgrid">
<div><b><?=e(setting('name'))?></b><p><?=e(setting('tagline'))?></p></div>
<div><h4>Tautan</h4><a href="<?=$H?>#about">About</a><a href="<?=$H?>#speakers">Speakers</a><a href="<?=$H?>#venue">Venue</a><a href="<?=$H?>#register">Register</a><a href="<?=$H?>login.php">Masuk</a></div>
<div><h4>Kontak</h4><p><?=e(setting('secretariat'))?></p><p><a href="mailto:<?=e($em)?>"><?=e($em)?></a></p></div></div>
<div class="copy">&copy; <?=date('Y')?> <?=e(setting('name'))?></div></footer><a href="#top" class="totop" aria-label="Ke atas">&uarr;</a>
<script>(function(){var l=document.querySelectorAll('.fade');if(!('IntersectionObserver' in window)){l.forEach(function(e){e.classList.add('in')});return}var o=new IntersectionObserver(function(a){a.forEach(function(x){if(x.isIntersecting){x.target.classList.add('in');o.unobserve(x.target)}})},{threshold:.08});l.forEach(function(e){o.observe(e)})})();</script>
<script>(function(){var m=document.getElementById('regm');if(!m)return;
var op=function(e){if(/register\.php$/.test(location.pathname))return;e.preventDefault();m.hidden=false;var i=m.querySelector('input[name=name]');if(i)i.focus()};
document.querySelectorAll('a[href$="register.php"]').forEach(function(a){a.addEventListener('click',op)});
m.addEventListener('click',function(e){if(e.target===m)m.hidden=true});
document.addEventListener('keydown',function(e){if(e.key==='Escape')m.hidden=true});
var f=m.querySelector('form'),er=m.querySelector('.merr');
f.addEventListener('submit',function(e){e.preventDefault();er.hidden=true;var b=f.querySelector('button.btn');b.disabled=true;
fetch(f.action,{method:'POST',body:new FormData(f),headers:{'X-Requested-With':'fetch'},credentials:'same-origin'}).then(function(r){return r.json()}).then(function(j){if(j.ok){location.href=j.to}else{er.textContent=j.error||'Gagal membuat akun.';er.hidden=false;b.disabled=false}}).catch(function(){er.textContent='Terjadi kesalahan. Muat ulang halaman lalu coba lagi.';er.hidden=false;b.disabled=false})})})();</script></body></html><?php }
function agenda(){global $pdo;$r=$pdo->query("SELECT * FROM agenda ORDER BY day,time")->fetchAll();if(!$r){echo '<p>Agenda belum tersedia.</p>';return;}
echo '<div class="scroll"><table><tr><th>Hari</th><th>Waktu</th><th>Sesi</th><th>Pembicara</th><th>Ruang</th></tr>';
foreach($r as $a)echo '<tr><td>'.e($a['day']).'</td><td>'.e($a['time']).'</td><td>'.e($a['title']).'</td><td>'.e($a['speaker']).'</td><td>'.e($a['room']).'</td></tr>';echo '</table></div>';}

function schema(){global $pdo;$pdo->exec("CREATE TABLE IF NOT EXISTS registrations(id INT AUTO_INCREMENT PRIMARY KEY,user_id INT NOT NULL UNIQUE,prefix VARCHAR(20),full_name VARCHAR(150),country VARCHAR(100),orcid VARCHAR(40),phone VARCHAR(30),category VARCHAR(100),attendance VARCHAR(10),article_title VARCHAR(300),coauthors TEXT,abstract TEXT,keywords VARCHAR(300),article_file VARCHAR(100),article_name VARCHAR(200),pay_amount VARCHAR(60),pay_date DATE NULL,pay_sender VARCHAR(150),pay_file VARCHAR(100),pay_name VARCHAR(200),dietary VARCHAR(150),paper_status ENUM('submitted','under_review','revision','accepted','rejected') DEFAULT 'submitted',pay_status ENUM('pending','verified','rejected') DEFAULT 'pending',admin_note TEXT,agreed TINYINT DEFAULT 0,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");}
function words($s){return count(preg_split('/\s+/u',trim((string)$s),-1,PREG_SPLIT_NO_EMPTY));}
function lbl($map,$k){return $map[$k]??$k;}
function magicOk($p,$ext){$h=bin2hex((string)file_get_contents($p,false,null,0,8));$m=['pdf'=>['25504446'],'docx'=>['504b0304'],'doc'=>['d0cf11e0'],'jpg'=>['ffd8ff'],'jpeg'=>['ffd8ff'],'png'=>['89504e47']];
 foreach($m[$ext]??[] as $x)if(strpos($h,$x)===0)return true;return false;}
function upFile($f,$exts,&$err,$label){$err='';
 if(!$f||($f['error']??4)===4){$err="$label: file belum dipilih.";return null;}
 if($f['error']!==0){$err="$label: gagal mengunggah (file mungkin melebihi batas server).";return null;}
 if($f['size']>10485760){$err="$label: ukuran maksimal 10 MB.";return null;}
 $ext=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));
 if(!in_array($ext,$exts,true)||!magicOk($f['tmp_name'],$ext)){$err="$label: format harus ".strtoupper(implode('/',$exts)).'.';return null;}
 $d=__DIR__.'/uploads/private/';
 if(!is_dir($d)){@mkdir($d,0755,true);@file_put_contents($d.'.htaccess',"Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");}
 $fn=bin2hex(random_bytes(10)).'.'.$ext;
 if(!move_uploaded_file($f['tmp_name'],$d.$fn)){$err="$label: gagal menyimpan (cek izin folder uploads).";return null;}
 return [$fn,substr(basename($f['name']),0,190)];}
function rmPriv($fn){if($fn)@unlink(__DIR__.'/uploads/private/'.basename($fn));}
function isurl($v){return (bool)preg_match('#^https?://#i',(string)$v);}
function initials($n){$w=preg_split('/[\s,]+/',(string)$n,-1,PREG_SPLIT_NO_EMPTY);$o='';foreach($w as $x){if(strpos($x,'.')!==false)continue;$o.=mb_strtoupper(mb_substr($x,0,1));if(mb_strlen($o)>=2)break;}return $o!==''?$o:'?';}
