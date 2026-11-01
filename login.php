<?php require 'inc.php';chk();$err='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 $s=$pdo->prepare("SELECT * FROM users WHERE email=?");$s->execute([strtolower(trim($_POST['email']))]);$u=$s->fetch();
 if($u&&password_verify($_POST['password'],$u['password'])){session_regenerate_id(true);$_SESSION['uid']=$u['id'];$to='admin/';if($u['role']!=='admin'){schema();$q=$pdo->prepare('SELECT id FROM registrations WHERE user_id=?');$q->execute([$u['id']]);$to=$q->fetch()?'dashboard.php':'registrasi.php';}header('Location: '.$to);exit;}
 $err='Email atau password salah.';}
head('Masuk');?><h2>Masuk</h2><?php if($err):?><p class="msg err"><?=e($err)?></p><?php endif;?>
<form method="post" class="card"><?=csrf()?><label>Email<input type="email" name="email" required></label>
<label>Password<input type="password" name="password" required></label><button class="btn">Masuk</button>
<p>Belum punya akun? <a href="register.php">Daftar</a></p></form><?php foot();
