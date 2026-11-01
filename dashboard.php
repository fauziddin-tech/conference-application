<?php require 'inc.php';$u=need('peserta');schema();chk();
$q=$pdo->prepare("SELECT * FROM registrations WHERE user_id=?");$q->execute([$u['id']]);$R=$q->fetch()?:null;
$PL=['pending'=>'Menunggu verifikasi','verified'=>'Terverifikasi','rejected'=>'Ditolak'];$AL=['submitted'=>'Diterima sistem','under_review'=>'Dalam review','revision'=>'Perlu revisi','accepted'=>'Diterima','rejected'=>'Ditolak'];
if($_SERVER['REQUEST_METHOD']==='POST'){
 $pdo->prepare("UPDATE users SET name=?,institution=?,phone=? WHERE id=?")->execute([trim($_POST['name']),trim($_POST['institution']),trim($_POST['phone']),$u['id']]);
 if(strlen($_POST['password'])>=6)$pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($_POST['password'],PASSWORD_DEFAULT),$u['id']]);
 flash('Profil diperbarui.');header('Location: dashboard.php');exit;}
$lbl=['pending'=>'Menunggu konfirmasi','confirmed'=>'Terkonfirmasi','rejected'=>'Ditolak'];
head('Dashboard');?><h2>Dashboard Peserta</h2>
<p>Status pendaftaran: <span class="badge <?=e($u['status'])?>"><?=e($lbl[$u['status']]??$u['status'])?></span> &middot; Kategori: <b><?=e($u['category'])?></b> &middot; ID Peserta: <b>P<?=str_pad($u['id'],4,'0',STR_PAD_LEFT)?></b></p>
<h3>Pendaftaran Konferensi</h3>
<?php if(!$R):?><div class="panel"><p>Anda belum melengkapi formulir pendaftaran (data peserta, naskah untuk presenter, dan bukti pembayaran).</p><a class="btn" href="registrasi.php">Lengkapi Pendaftaran</a></div>
<?php else:?><div class="panel"><p>No. Registrasi: <b>R<?=str_pad($R['id'],4,'0',STR_PAD_LEFT)?></b> &middot; Kategori: <b><?=e($R['category'])?></b> &middot; Kehadiran: <b><?=e($R['attendance'])?></b></p>
<p>Pembayaran: <span class="badge <?=e($R['pay_status'])?>"><?=e(lbl($PL,$R['pay_status']))?></span><?php if($R['article_title']):?> &middot; Naskah: <span class="badge <?=e($R['paper_status'])?>"><?=e(lbl($AL,$R['paper_status']))?></span><br>Judul: <i><?=e($R['article_title'])?></i><?php endif;?></p>
<?php if($R['admin_note']):?><p><b>Catatan panitia:</b> <?=nl2br(e($R['admin_note']))?></p><?php endif;?>
<?php if($R['pay_status']!=='verified'):?><a class="btn" href="registrasi.php">Ubah / Lengkapi Data</a><?php endif;?></div><?php endif;?>
<h3>Profil</h3><form method="post" class="card"><?=csrf()?>
<label>Email<input value="<?=e($u['email'])?>" disabled></label>
<label>Nama<input name="name" required value="<?=e($u['name'])?>"></label>
<label>Institusi<input name="institution" value="<?=e($u['institution'])?>"></label>
<label>No. HP<input name="phone" value="<?=e($u['phone'])?>"></label>
<label>Password baru (kosongkan jika tidak diubah)<input type="password" name="password"></label>
<button class="btn">Simpan</button></form><h3>Agenda</h3><?php agenda();foot();
