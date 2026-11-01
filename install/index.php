<?php
$root=dirname(__DIR__);$err='';$done=false;
if(file_exists("$root/config.php")||file_exists(__DIR__.'/install.lock'))die('Sudah terinstal. Hapus folder /install demi keamanan.');
$SP="Open Speech|Prof. Brian Yuliarto, Ph.D|Diktisaintek
Open Speech|Prof. Dr. Amir Luthfi|Rektor Universitas Pahlawan Tuanku Tambusai
Keynote Speaker|Prof. Syamsul Bahri, M.Sc. Ph.D|North-West University, Potchefstroom
Keynote Speaker|Prof. Hansde Ridder|North-West University, Potchefstroom
Keynote Speaker|Dr. Nur Ehsan Mohd Said|Universiti Kebangsaan Malaysia
Keynote Speaker|Dr. Oksana Afitska|Manchester University
Keynote Speaker|Dr. Musnar Indra Daulay, M.Pd|Universitas Pahlawan Tuanku Tambusai
Chairman of the Committee|Dr. Rusdial Marta, M.Pd|Universitas Pahlawan Tuanku Tambusai";
$FE="Offline Participant|IDR 800.000|US$ 45
Online Participant|IDR 100.000|US$ 6
Offline Presenter|IDR 1.500.000|US$ 85
Offline Student Presenter|IDR 1.300.000|US$ 74";
$BE="E-Certificate
Meals, Seminar Kits
National Journal Indonesia Accredited SINTA (S2, S3, S4, S5)";
$CO="Rusdial Marta|+6281266432727
Kasman Ediputra|+62822 8800 9315
Yenny Fitra Surya|+62821 6902 9303";
$SE="Sekretariat: Fakultas Keguruan dan Ilmu Pendidikan, Universitas Pahlawan Tuanku Tambusai, Jalan Tuanku Tambusai No. 23, Bangkinang, Riau, Indonesia";
function e($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
if($_SERVER['REQUEST_METHOD']==='POST'){
 $f=function($k){return trim($_POST[$k]??'');};
 try{
  if(!filter_var($f('a_email'),FILTER_VALIDATE_EMAIL))throw new Exception('Email admin tidak valid.');
  if(strlen($_POST['a_pass'])<8)throw new Exception('Password admin minimal 8 karakter.');
  $pdo=new PDO("mysql:host=".$f('db_host').";dbname=".$f('db_name').";charset=utf8mb4",$f('db_user'),$_POST['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
  $pdo->exec("CREATE TABLE IF NOT EXISTS settings(k VARCHAR(50) PRIMARY KEY,v TEXT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $pdo->exec("CREATE TABLE IF NOT EXISTS users(id INT AUTO_INCREMENT PRIMARY KEY,category VARCHAR(100),name VARCHAR(150) NOT NULL,email VARCHAR(150) NOT NULL UNIQUE,institution VARCHAR(150),phone VARCHAR(30),password VARCHAR(255) NOT NULL,role ENUM('admin','peserta') DEFAULT 'peserta',status ENUM('pending','confirmed','rejected') DEFAULT 'pending',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $pdo->exec("CREATE TABLE IF NOT EXISTS agenda(id INT AUTO_INCREMENT PRIMARY KEY,day VARCHAR(50),time VARCHAR(50),title VARCHAR(255),speaker VARCHAR(150),room VARCHAR(100)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $st=$pdo->prepare("REPLACE INTO settings(k,v) VALUES(?,?)");
  foreach(['name'=>$f('s_name'),'tagline'=>$f('s_tagline'),'date'=>$f('s_date'),'location'=>$f('s_loc'),'email'=>'picoe@universitaspahlawan.ac.id','about'=>"Konferensi internasional terbuka untuk umum yang diselenggarakan oleh Fakultas Keguruan dan Ilmu Pendidikan (FKIP), Universitas Pahlawan Tuanku Tambusai, dengan topik yang tidak terbatas pada bidang pendidikan.\n\nTujuan kegiatan:\na) Menjadi wadah internasional bagi peneliti dan akademisi untuk berbagi informasi dan gagasan baru tentang praktik pendidikan yang baik di tengah perkembangan teknologi.\nb) Membuka kolaborasi dengan akademisi, peneliti, dan anggota asosiasi pendidikan secara internasional.\nc) Membuka peluang baru kolaborasi riset pendidikan yang kompetitif secara internasional.",'time'=>'08.00 - 18.00 WIB','website'=>'www.picoe.org','speakers'=>$SP,'fees'=>$FE,'benefits'=>$BE,'contacts'=>$CO,'secretariat'=>$SE] as $k=>$v)$st->execute([$k,$v]);
  $pdo->prepare("INSERT INTO users(name,email,password,role,status) VALUES(?,?,?,'admin','confirmed')")->execute([$f('a_name'),strtolower($f('a_email')),password_hash($_POST['a_pass'],PASSWORD_DEFAULT)]);
  $base=rtrim(str_replace('\\','/',dirname(dirname($_SERVER['SCRIPT_NAME']))),'/').'/';
  $cfg="<?php\ndefine('DB_HOST',".var_export($f('db_host'),true).");\ndefine('DB_NAME',".var_export($f('db_name'),true).");\ndefine('DB_USER',".var_export($f('db_user'),true).");\ndefine('DB_PASS',".var_export($_POST['db_pass'],true).");\ndefine('BASE',".var_export($base,true).");\n";
  if(file_put_contents("$root/config.php",$cfg)===false)throw new Exception('Gagal menulis config.php (cek izin folder).');
  @file_put_contents(__DIR__.'/install.lock','1');$done=true;
 }catch(Exception $x){$err=$x->getMessage();}
}
?><!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Instalasi</title><link rel="stylesheet" href="../style.css"></head><body><main>
<h2>Instalasi Website Konferensi</h2>
<?php if($done):?><p class="msg">Instalasi berhasil! <b>Hapus folder /install</b> dari server, lalu <a href="../login.php">masuk sebagai admin</a>.</p>
<?php else:?><?php if($err):?><p class="msg err"><?=e($err)?></p><?php endif;?>
<form method="post" class="card"><h3>Database MySQL (dari cPanel)</h3>
<label>Host<input name="db_host" value="<?=e($_POST['db_host']??'localhost')?>" required></label>
<label>Nama Database<input name="db_name" value="<?=e($_POST['db_name']??'')?>" required></label>
<label>User Database<input name="db_user" value="<?=e($_POST['db_user']??'')?>" required></label>
<label>Password Database<input type="password" name="db_pass"></label>
<h3>Konferensi</h3>
<label>Nama Konferensi<input name="s_name" value="<?=e($_POST['s_name']??'3rd Pahlawan International Conference on Education (PICOE)')?>" required></label>
<label>Tagline<input name="s_tagline" value="<?=e($_POST['s_tagline']??'The New Face of Education: Reimagining Learning in the Era of Artificial Intelligence and Digital Transformation')?>"></label>
<label>Tanggal<input name="s_date" value="<?=e($_POST['s_date']??'Kamis, 3 Desember 2026')?>"></label>
<label>Lokasi<input name="s_loc" value="<?=e($_POST['s_loc']??'Hotel Pangeran Pekanbaru')?>"></label>
<h3>Akun Admin</h3>
<label>Nama<input name="a_name" value="<?=e($_POST['a_name']??'')?>" required></label>
<label>Email<input type="email" name="a_email" value="<?=e($_POST['a_email']??'')?>" required></label>
<label>Password (min. 8 karakter)<input type="password" name="a_pass" required minlength="8"></label>
<button class="btn">Install</button></form><?php endif;?></main></body></html>
