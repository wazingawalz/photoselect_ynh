<?php
require __DIR__ . '/config.php';
require __DIR__ . '/lib.php';
session_name('photoselect');
session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS']),'samesite'=>'Lax']);
session_start();
$db = ps_db();
ps_csrf();
$notice = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
  ps_check_csrf();
  $action=$_POST['action']??'';
  if ($action==='setup') {
    if (ps_user_exists()) { http_response_code(403); exit('Setup already completed.'); }
    $u=trim($_POST['username']??'admin'); $p=$_POST['password']??'';
    if (strlen($p)<14) $notice='Geslo mora imeti vsaj 14 znakov.';
    else { $q=$db->prepare('INSERT INTO users(username,password_hash) VALUES(?,?)'); $q->execute([$u,password_hash($p,PASSWORD_DEFAULT)]); $_SESSION['admin']=true; header('Location: ./'); exit; }
  } elseif ($action==='login') {
    $q=$db->prepare('SELECT * FROM users WHERE username=?'); $q->execute([$_POST['username']??'']); $u=$q->fetch();
    if ($u && password_verify($_POST['password']??'', $u['password_hash'])) { session_regenerate_id(true); $_SESSION['admin']=true; header('Location: ./'); exit; }
    $notice='Napačno uporabniško ime ali geslo.';
  } elseif ($action==='logout') { $_SESSION=[]; session_destroy(); header('Location: ./'); exit; }
  elseif (!empty($_SESSION['admin']) && $action==='album') {
    $name=trim($_POST['name']??''); $slug=bin2hex(random_bytes(6)); $pw=$_POST['album_password']??'';
    if ($name && strlen($pw)>=8) { $q=$db->prepare('INSERT INTO albums(name,slug,password_hash,quota,allow_download,watermark,created_at) VALUES(?,?,?,?,?,?,?)'); $q->execute([$name,$slug,password_hash($pw,PASSWORD_DEFAULT),max(0,(int)($_POST['quota']??0)),isset($_POST['download'])?1:0,isset($_POST['watermark'])?1:0,date('c')]); header('Location: ./?admin=1'); exit; }
    $notice='Vnesi ime albuma in geslo (vsaj 8 znakov).';
  } elseif (!empty($_SESSION['admin']) && $action==='upload') {
    $id=(int)$_POST['album_id']; $a=ps_album($id); if ($a && isset($_FILES['photos'])) {
      $dest=PHOTOSELECT_DATA.'/storage/'.$a['slug']; $prev=PHOTOSELECT_DATA.'/previews/'.$a['slug']; @mkdir($dest,0750,true); @mkdir($prev,0750,true);
      foreach ($_FILES['photos']['tmp_name'] as $i=>$tmp) {
        if (!is_uploaded_file($tmp) || $_FILES['photos']['error'][$i]!==UPLOAD_ERR_OK) continue;
        $mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp); if (!in_array($mime,['image/jpeg','image/png','image/webp'])) continue;
        $ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime]; $fn=bin2hex(random_bytes(12)).'.'.$ext;
        if (move_uploaded_file($tmp,$dest.'/'.$fn)) {
          $preview=$prev.'/'.$fn;
          if (extension_loaded('imagick')) { try { $im=new Imagick($dest.'/'.$fn); $im->thumbnailImage(1800,1800,true,true); if ($a['watermark']) { $draw=new ImagickDraw(); $draw->setFillColor('rgba(255,255,255,0.55)'); $draw->setFontSize(48); $im->annotateImage($draw,20,$im->getImageHeight()-30,0,'PREVIEW'); } $im->writeImage($preview); $im->clear(); } catch(Throwable $e) { copy($dest.'/'.$fn,$preview); } } else copy($dest.'/'.$fn,$preview);
          $q=$db->prepare('INSERT INTO photos(album_id,filename,created_at) VALUES(?,?,?)'); $q->execute([$id,$fn,date('c')]);
        }
      }
    } header('Location: ./?admin=1'); exit;
  } elseif ($action==='client_login') {
    $a=ps_album_by_slug($_POST['slug']??''); if ($a && password_verify($_POST['album_password']??'', $a['password_hash'])) { $_SESSION['album_'.$a['id']]=true; header('Location: ./?album='.$a['slug']); exit; } $notice='Napačno geslo albuma.';
  } elseif ($action==='like') {
    $a=ps_album_by_slug($_POST['slug']??''); if ($a && !empty($_SESSION['album_'.$a['id']])) {
      $count=ps_selected_count($a['id']); $photo=(int)$_POST['photo_id'];
      $q=$db->prepare('SELECT id FROM photos WHERE id=? AND album_id=?'); $q->execute([$photo,$a['id']]);
      if ($q->fetch()) {
        $q=$db->prepare('SELECT id FROM selections WHERE album_id=? AND photo_id=?'); $q->execute([$a['id'],$photo]); $exists=$q->fetch();
        if ($exists) { $db->prepare('DELETE FROM selections WHERE id=?')->execute([$exists['id']]); }
        elseif (!$a['quota'] || $count<$a['quota']) { $db->prepare('INSERT INTO selections(album_id,photo_id,created_at) VALUES(?,?,?)')->execute([$a['id'],$photo,date('c')]); }
        else { $_SESSION['overquota_'.$a['id']]=true; }
      }
      header('Location: ./?album='.$a['slug']); exit;
    }
  }
}
function h($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
?>
<!doctype html><html lang="sl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>PhotoSelect</title><link rel="stylesheet" href="style.css"></head><body><header><a href="./" class="brand">PhotoSelect</a><span>Zasebna galerija fotografij</span></header><main>
<?php if ($notice): ?><p class="notice"><?=h($notice)?></p><?php endif; ?>
<?php if (!ps_user_exists()): ?>
<section class="panel"><h1>Prva nastavitev</h1><p>Ustvari administratorski račun. Uporabi unikatno geslo, ki ga ne uporabljaš drugje.</p><form method="post"><?=ps_token_input()?><input type="hidden" name="action" value="setup"><label>Uporabniško ime<input name="username" required value="admin"></label><label>Geslo (najmanj 14 znakov)<input type="password" name="password" minlength="14" required></label><button>Ustvari račun</button></form></section>
<?php elseif (empty($_SESSION['admin']) && empty($_GET['album'])): ?>
<section class="panel"><h1>Administracija</h1><form method="post"><?=ps_token_input()?><input type="hidden" name="action" value="login"><label>Uporabniško ime<input name="username" required></label><label>Geslo<input type="password" name="password" required></label><button>Prijava</button></form></section>
<?php elseif (!empty($_SESSION['admin'])): ?>
<nav><h1>Nadzorna plošča</h1><form method="post" class="inline"><?=ps_token_input()?><input type="hidden" name="action" value="logout"><button class="secondary">Odjava</button></form></nav>
<section class="panel"><h2>Nov album</h2><form method="post"><?=ps_token_input()?><input type="hidden" name="action" value="album"><label>Ime albuma<input name="name" required></label><label>Geslo za stranko<input name="album_password" type="password" minlength="8" required></label><label>Vključenih fotografij (0 = brez omejitve)<input name="quota" type="number" min="0" value="30"></label><label class="check"><input type="checkbox" name="download"> Dovoli prenos originalov (funkcionalnost še ni vključena v ta začetni paket)</label><label class="check"><input type="checkbox" name="watermark"> Vodni žig na predogledu (PREVIEW)</label><button>Ustvari album</button></form></section>
<section><h2>Albumi</h2><div class="grid">
<?php foreach(ps_albums() as $a): ?><article class="card"><h3><?=h($a['name'])?></h3><p><?=ps_photo_count($a['id'])?> fotografij · <?=ps_selected_count($a['id'])?> všečkanih</p><p>Povezava: <code>?album=<?=h($a['slug'])?></code></p><form method="post" enctype="multipart/form-data"><?=ps_token_input()?><input type="hidden" name="action" value="upload"><input type="hidden" name="album_id" value="<?=$a['id']?>"><label>Dodaj fotografije<input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple required></label><button>Naloži</button></form></article><?php endforeach; ?>
</div></section>
<?php elseif (isset($_GET['album'])):
$a=ps_album_by_slug($_GET['album']); if (!$a): ?><p>Album ne obstaja.</p>
<?php elseif (empty($_SESSION['album_'.$a['id']])): ?><section class="panel"><h1><?=h($a['name'])?></h1><form method="post"><?=ps_token_input()?><input type="hidden" name="action" value="client_login"><input type="hidden" name="slug" value="<?=h($a['slug'])?>"><label>Geslo albuma<input type="password" name="album_password" required></label><button>Odpri galerijo</button></form></section>
<?php else: $photos=ps_photos($a['id']); $selected=ps_selected_ids($a['id']); ?><nav><h1><?=h($a['name'])?></h1><strong>Izbranih: <?=count($selected)?><?= $a['quota']?' / '.$a['quota']:'' ?></strong></nav><?php if(count($selected)>=$a['quota'] && $a['quota']>0): ?><p class="notice">Dosegli ste število fotografij, vključenih v paket. Nadaljnje izbrane fotografije se doplačajo po aktualnem ceniku. Nadaljevanje pomeni, da ste seznanjeni s tem obvestilom.</p><?php endif; ?><div class="grid photos"><?php foreach($photos as $p): ?><article class="photo"><img loading="lazy" src="media.php?slug=<?=h($a['slug'])?>&photo=<?=$p['id']?>"><form method="post"><?=ps_token_input()?><input type="hidden" name="action" value="like"><input type="hidden" name="slug" value="<?=h($a['slug'])?>"><input type="hidden" name="photo_id" value="<?=$p['id']?>"><button class="heart <?=$selected && in_array($p['id'],$selected)?'liked':''?>" aria-label="Všečkaj">♥</button></form></article><?php endforeach; ?></div>
<?php endif; endif; ?>
</main><footer>PhotoSelect · Začetna razvojna različica</footer></body></html>
