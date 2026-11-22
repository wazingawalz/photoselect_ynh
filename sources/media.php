<?php
require __DIR__.'/config.php'; require __DIR__.'/lib.php'; session_start();
$a=ps_album_by_slug($_GET['slug']??''); $id=(int)($_GET['photo']??0);
if(!$a || empty($_SESSION['album_'.$a['id']])) { http_response_code(403); exit; }
$q=ps_db()->prepare('SELECT filename FROM photos WHERE id=? AND album_id=?'); $q->execute([$id,$a['id']]); $p=$q->fetch();
if(!$p){http_response_code(404);exit;}
$base=PHOTOSELECT_DATA.'/previews/'.$a['slug'].'/'; $file=$base.basename($p['filename']);
if(!is_file($file)){ $file=PHOTOSELECT_DATA.'/storage/'.$a['slug'].'/'.basename($p['filename']); }
if(!is_file($file)){http_response_code(404);exit;}
$mime=(new finfo(FILEINFO_MIME_TYPE))->file($file); if(!in_array($mime,['image/jpeg','image/png','image/webp'])){http_response_code(415);exit;}
header('Content-Type: '.$mime); header('Cache-Control: private, max-age=300'); header('X-Content-Type-Options: nosniff'); readfile($file);
