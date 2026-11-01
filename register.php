<?php require 'inc.php';chk();$err='';$ajax=($_SERVER['HTTP_X_REQUESTED_WITH']??'')==='fetch';
if($_SERVER['REQUEST_METHOD']==='POST'){
 $n=trim($_POST['name']??'');$m=strtolower(trim($_POST['email']??''));$pw=$_POST['password']??'';
 if(!$n||!filter_var($m,FILTER_VALIDATE_EMAIL))$err='Nama dan email valid wajib diisi.';
 elseif(strlen($pw)<6)$err='Password minimal 6 karakter.';
 else{$s=$pdo->prepare("SELECT id FROM users WHERE email=?");$s->execute([$m]);
  if($s->fetch())$err='Email sudah terdaftar. Silakan masuk.';
  else{$pdo->prepare("INSERT INTO users(name,email,password,role,status) VALUES(?,?,?,'peserta','pending')")->execute([$n,$m,password_hash($pw,PASSWORD_DEFAULT)]);
   session_regenerate_id(true);$_SESSION['uid']=$pdo->lastInsertId();
   flash('Akun berhasil dibuat. Lanjutkan dengan melengkapi formulir pendaftaran konferensi.');
   if($ajax){header('Content-Type: application/json');echo json_encode(['ok'=>1,'to'=>BASE.'registrasi.php']);exit;}
   header('Location: registrasi.php');exit;}}
 if($err&&$ajax){header('Content-Type: application/json');echo json_encode(['error'=>$err]);exit;}}
head('Daftar');?><h2>Buat Akun Peserta</h2>
<p><b>Langkah 1 dari 2.</b> Buat akun terlebih dahulu. Setelah itu Anda langsung diarahkan ke formulir pendaftaran konferensi.</p>
<?php if($err):?><p class="msg err"><?=e($err)?></p><?php endif;?>
<form method="post" class="card"><?=csrf()?>
<label>Nama Lengkap<input name="name" required value="<?=e($_POST['name']??'')?>"></label>
<label>Email<input type="email" name="email" required value="<?=e($_POST['email']??'')?>"></label>
<label>Password (min. 6 karakter)<input type="password" name="password" required minlength="6"></label>
<button class="btn">Buat Akun &amp; Lanjutkan</button>
<p>Sudah punya akun? <a href="login.php">Masuk</a></p></form><?php foot();
