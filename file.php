<?php require 'inc.php';$u=need();$id=(int)($_GET['id']??0);$k=($_GET['k']??'')==='pay'?'pay':'article';
$s=$pdo->prepare("SELECT * FROM registrations WHERE id=?");$s->execute([$id]);$r=$s->fetch();
if(!$r||($u['role']!=='admin'&&(int)$r['user_id']!==(int)$u['id'])){http_response_code(403);die('Akses ditolak');}
$fn=basename((string)$r[$k.'_file']);$path=__DIR__.'/uploads/private/'.$fn;
if($fn===''||!is_file($path)){http_response_code(404);die('File tidak ditemukan');}
$ext=strtolower(pathinfo($fn,PATHINFO_EXTENSION));
$mime=['pdf'=>'application/pdf','doc'=>'application/msword','docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png'][$ext]??'application/octet-stream';
header('Content-Type: '.$mime);header('Content-Length: '.filesize($path));header('X-Content-Type-Options: nosniff');
header('Content-Disposition: attachment; filename="'.preg_replace('/[^A-Za-z0-9._-]/','_',$r[$k.'_name']?:$fn).'"');
readfile($path);
