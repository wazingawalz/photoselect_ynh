<?php
function ps_db(): PDO {
  static $db=null; if($db) return $db;
  $file=PHOTOSELECT_DATA.'/photoselect.sqlite';
  $db=new PDO('sqlite:'.$file,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
  $db->exec('PRAGMA foreign_keys=ON; PRAGMA journal_mode=WAL;');
  $db->exec("CREATE TABLE IF NOT EXISTS users(id INTEGER PRIMARY KEY, username TEXT UNIQUE NOT NULL, password_hash TEXT NOT NULL);
  CREATE TABLE IF NOT EXISTS albums(id INTEGER PRIMARY KEY, name TEXT NOT NULL, slug TEXT UNIQUE NOT NULL, password_hash TEXT NOT NULL, quota INTEGER NOT NULL DEFAULT 0, allow_download INTEGER NOT NULL DEFAULT 0, watermark INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL);
  CREATE TABLE IF NOT EXISTS photos(id INTEGER PRIMARY KEY, album_id INTEGER NOT NULL REFERENCES albums(id) ON DELETE CASCADE, filename TEXT NOT NULL, created_at TEXT NOT NULL);
  CREATE TABLE IF NOT EXISTS selections(id INTEGER PRIMARY KEY, album_id INTEGER NOT NULL REFERENCES albums(id) ON DELETE CASCADE, photo_id INTEGER NOT NULL REFERENCES photos(id) ON DELETE CASCADE, created_at TEXT NOT NULL, UNIQUE(album_id,photo_id));");
  return $db;
}
function ps_user_exists(){return (bool)ps_db()->query('SELECT 1 FROM users LIMIT 1')->fetchColumn();}
function ps_album($id){$q=ps_db()->prepare('SELECT * FROM albums WHERE id=?');$q->execute([$id]);return $q->fetch();}
function ps_album_by_slug($s){$q=ps_db()->prepare('SELECT * FROM albums WHERE slug=?');$q->execute([$s]);return $q->fetch();}
function ps_albums(){return ps_db()->query('SELECT * FROM albums ORDER BY id DESC')->fetchAll();}
function ps_photos($id){$q=ps_db()->prepare('SELECT * FROM photos WHERE album_id=? ORDER BY id');$q->execute([$id]);return $q->fetchAll();}
function ps_photo_count($id){$q=ps_db()->prepare('SELECT count(*) FROM photos WHERE album_id=?');$q->execute([$id]);return (int)$q->fetchColumn();}
function ps_selected_count($id){$q=ps_db()->prepare('SELECT count(*) FROM selections WHERE album_id=?');$q->execute([$id]);return (int)$q->fetchColumn();}
function ps_selected_ids($id){$q=ps_db()->prepare('SELECT photo_id FROM selections WHERE album_id=?');$q->execute([$id]);return array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));}
function ps_csrf(){if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(32));}
function ps_token_input(){return '<input type="hidden" name="csrf" value="'.htmlspecialchars($_SESSION['csrf'],ENT_QUOTES).'">';}
function ps_check_csrf(){if(!hash_equals($_SESSION['csrf']??'',$_POST['csrf']??'')){http_response_code(400);exit('Neveljaven zahtevek.');}}
