<?php
require_once __DIR__ . '/../api/db.php';

// ── DB-backed session handler ─────────────────────────────────────────
class KalamperDbSessionHandler implements SessionHandlerInterface {
    private PDO $pdo;
    public function __construct(PDO $pdo) { $this->pdo = $pdo; }
    public function open($p,$n): bool { return true; }
    public function close(): bool { return true; }
    public function read($id): string|false {
        $s = $this->pdo->prepare('SELECT data FROM kalamper_admin_sessions WHERE id=? AND expires_at>NOW()');
        $s->execute([$id]);
        return $s->fetchColumn() ?: '';
    }
    public function write($id,$data): bool {
        $s = $this->pdo->prepare('INSERT INTO kalamper_admin_sessions (id,data,expires_at) VALUES (?,?,DATE_ADD(NOW(),INTERVAL 4 HOUR)) ON DUPLICATE KEY UPDATE data=VALUES(data),expires_at=VALUES(expires_at)');
        return $s->execute([$id,$data]);
    }
    public function destroy($id): bool { $this->pdo->prepare('DELETE FROM kalamper_admin_sessions WHERE id=?')->execute([$id]); return true; }
    public function gc($max): int|false { $this->pdo->exec('DELETE FROM kalamper_admin_sessions WHERE expires_at<NOW()'); return 1; }
}

try { $__spdo = kalamper_pdo(); kalamper_initialize_database(); session_set_save_handler(new KalamperDbSessionHandler($__spdo), true); } catch(Throwable $e) {}
$sec = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$sec,'httponly'=>true,'samesite'=>'Lax']);
session_start();

// ── Helpers ───────────────────────────────────────────────────────────
function h($v): string { return htmlspecialchars((string)($v??''), ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function redirect_admin(string $qs=''): void { header('Location: ./' . ($qs ? '?'.$qs : '')); exit; }
function require_admin(): void { if(empty($_SESSION['kalamper_admin'])) redirect_admin(); }
function csrf_token(): string { if(empty($_SESSION['kalamper_csrf'])) $_SESSION['kalamper_csrf']=bin2hex(random_bytes(32)); return $_SESSION['kalamper_csrf']; }
function csrf_field(): string { return '<input type="hidden" name="csrf_token" value="'.h(csrf_token()).'">'; }
function require_csrf(): void { $t=(string)($_POST['csrf_token']??''); $e=(string)($_SESSION['kalamper_csrf']??''); if($e===''||!hash_equals($e,$t)){http_response_code(403);exit('Invalid CSRF token');} }

// Default password hash for "kalamper2024"
const DEFAULT_PASS = 'kalamper2024';

function getAdminPasswordHash(): string {
    try {
        $pdo = kalamper_pdo();
        $pdo->exec("CREATE TABLE IF NOT EXISTS kalamper_admin_settings (
            k VARCHAR(120) NOT NULL PRIMARY KEY,
            v TEXT NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $s = $pdo->prepare('SELECT v FROM kalamper_admin_settings WHERE k=?');
        $s->execute(['admin_password_hash']);
        $v = $s->fetchColumn();
        if ($v) return (string)$v;
    } catch(Throwable) {}
    return password_hash(DEFAULT_PASS, PASSWORD_BCRYPT, ['cost'=>12]);
}

function saveAdminPasswordHash(string $hash): void {
    try {
        kalamper_pdo()->prepare('INSERT INTO kalamper_admin_settings (k,v) VALUES (?,?) ON DUPLICATE KEY UPDATE v=VALUES(v)')->execute(['admin_password_hash',$hash]);
    } catch(Throwable) {}
}

// ── Bootstrap ─────────────────────────────────────────────────────────
$message = '';
$messageErr = false;
$pdo = kalamper_pdo();

// ── Logout ────────────────────────────────────────────────────────────
if(isset($_GET['logout'])){ $_SESSION=[]; session_destroy(); redirect_admin(); }

// ── POST: login ───────────────────────────────────────────────────────
if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??'')==='login'){
    require_csrf();
    $pass=(string)($_POST['password']??'');
    $hash = getAdminPasswordHash();
    if(password_verify($pass,$hash)){ session_regenerate_id(true); $_SESSION['kalamper_admin']=true; redirect_admin(); }
    else { $message='Неверный пароль.'; $messageErr=true; }
}

// ── Login page ────────────────────────────────────────────────────────
if(empty($_SESSION['kalamper_admin'])):
?><!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>KALAMPER Admin</title><style>
*,*::before,*::after{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;background:#0D0D0D;color:#fff;font:14px/1.5 'Arial',sans-serif}
.card{display:grid;gap:14px;width:min(calc(100% - 32px),400px);padding:32px;border:1px solid rgba(255,184,0,.2);border-radius:20px;background:#1A1A1A}
.card h1{margin:0;font-size:22px;color:#FFB800}.card h2{margin:0;font-size:17px;color:#fff}
label{display:grid;gap:6px;font-size:13px;font-weight:700;color:#888}
input[type=password]{padding:11px 14px;border-radius:10px;border:1px solid rgba(255,184,0,.2);background:#0D0D0D;color:#fff;font:inherit;font-size:14px;width:100%}
input:focus{outline:none;border-color:rgba(255,184,0,.6)}
button[type=submit]{padding:12px;border:0;border-radius:10px;background:#FFB800;color:#0D0D0D;font:inherit;font-size:14px;font-weight:700;cursor:pointer;width:100%}
.msg{font-size:13px;padding:10px 14px;border-radius:8px;border-left:3px solid #FFB800;background:rgba(255,184,0,.08);color:#888}
.msg-err{border-color:#ef4444;background:rgba(239,68,68,.08);color:#f87171}
</style></head><body>
<div class="card"><h1>KALAMPER</h1><h2>Панель администратора</h2>
<?php if($message): ?><p class="msg <?=$messageErr?'msg-err':''?>"><?=h($message)?></p><?php endif; ?>
<form method="post"><input type="hidden" name="action" value="login"><?=csrf_field()?>
<label>Пароль<input name="password" type="password" required autofocus></label>
<button type="submit">Войти</button></form></div>
</body></html>
<?php exit; endif;

// ── Authenticated POST actions ────────────────────────────────────────
if($_SERVER['REQUEST_METHOD']==='POST'){
    require_admin(); require_csrf();
    $action=(string)($_POST['action']??'');
    $id=(int)($_POST['id']??0);

    if($action==='add_dealer'){
        $pdo->prepare('INSERT INTO kalamper_dealers (city,name,address,phone,hours,website,email,sort_order,is_active) VALUES (?,?,?,?,?,?,?,?,1)')->execute([
            trim($_POST['city']??''), trim($_POST['name']??''), trim($_POST['address']??''), trim($_POST['phone']??''),
            trim($_POST['hours']??''), trim($_POST['website']??''), trim($_POST['email']??''), (int)($_POST['sort_order']??100)
        ]);
        redirect_admin('tab=dealers&msg='.urlencode('Дилер добавлен.'));
    }

    if($action==='save_dealer'){
        $pdo->prepare('UPDATE kalamper_dealers SET city=?,name=?,address=?,phone=?,hours=?,website=?,email=?,sort_order=? WHERE id=?')->execute([
            trim($_POST['city']??''), trim($_POST['name']??''), trim($_POST['address']??''), trim($_POST['phone']??''),
            trim($_POST['hours']??''), trim($_POST['website']??''), trim($_POST['email']??''), (int)($_POST['sort_order']??100), $id
        ]);
        redirect_admin('tab=dealers&msg='.urlencode('Дилер обновлён.'));
    }

    if($action==='toggle_dealer'){
        $pdo->prepare('UPDATE kalamper_dealers SET is_active=1-is_active WHERE id=?')->execute([$id]);
        redirect_admin('tab=dealers');
    }

    if($action==='delete_dealer'){
        $pdo->prepare('DELETE FROM kalamper_dealers WHERE id=?')->execute([$id]);
        redirect_admin('tab=dealers&msg='.urlencode('Дилер удалён.'));
    }

    if($action==='toggle_review'){
        $pdo->prepare('UPDATE kalamper_reviews SET is_published=1-is_published WHERE id=?')->execute([$id]);
        redirect_admin('tab=reviews');
    }

    if($action==='delete_review'){
        $pdo->prepare('DELETE FROM kalamper_reviews WHERE id=?')->execute([$id]);
        redirect_admin('tab=reviews&msg='.urlencode('Отзыв удалён.'));
    }

    if($action==='change_password'){
        $newPass=(string)($_POST['new_password']??''); $confirm=(string)($_POST['confirm_password']??'');
        if(mb_strlen($newPass)<8){ $message='Пароль должен быть не менее 8 символов.'; $messageErr=true; }
        elseif($newPass!==$confirm){ $message='Пароли не совпадают.'; $messageErr=true; }
        else { saveAdminPasswordHash(password_hash($newPass,PASSWORD_BCRYPT,['cost'=>12])); redirect_admin('msg='.urlencode('Пароль изменён.')); }
    }
}

// ── Load data ─────────────────────────────────────────────────────────
require_admin();
$dealers=$pdo->query('SELECT * FROM kalamper_dealers ORDER BY sort_order,city,name')->fetchAll();
$reviews=$pdo->query('SELECT * FROM kalamper_reviews ORDER BY created_at DESC')->fetchAll();
$activeTab=$_GET['tab']??'dealers';
$msgGet=urldecode((string)($_GET['msg']??''));
if($msgGet&&!$message){ $message=$msgGet; }

?><!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>KALAMPER Admin</title><style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#0D0D0D;--bg2:#1A1A1A;--fg:#ffffff;--muted:#888888;--accent:#FFB800;--danger:#ef4444;--border:rgba(255,184,0,.15);--radius:12px}
body{background:var(--bg);color:var(--fg);font:14px/1.5 Arial,sans-serif;min-height:100vh}
a{color:var(--accent);text-decoration:none}
input,select,textarea,button{font:inherit}
.topbar{display:flex;align-items:center;gap:16px;padding:12px 24px;border-bottom:1px solid var(--border);background:var(--bg2)}
.topbar strong{color:var(--accent);font-size:15px;font-weight:800;letter-spacing:.05em}
.topbar nav{display:flex;gap:4px;margin-left:16px}
.topbar nav a{padding:6px 14px;border-radius:8px;color:var(--muted);font-size:13px}
.topbar nav a.act,.topbar nav a:hover{background:rgba(255,184,0,.1);color:var(--fg)}
.topbar .out{margin-left:auto;font-size:13px;color:var(--muted)}
.main{padding:24px;max-width:1200px}
.card{background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius);padding:20px;margin-bottom:20px}
.card h2{font-size:13px;margin-bottom:16px;color:var(--accent);text-transform:uppercase;letter-spacing:.08em}
table{width:100%;border-collapse:collapse;font-size:13px}
th{text-align:left;padding:8px 10px;color:var(--muted);border-bottom:1px solid var(--border);font-weight:600;white-space:nowrap}
td{padding:8px 10px;border-bottom:1px solid rgba(255,255,255,.04);vertical-align:top}
tr:hover td{background:rgba(255,255,255,.02)}
.btn{display:inline-flex;align-items:center;padding:7px 14px;border-radius:8px;border:0;cursor:pointer;font-size:13px;font-weight:600;line-height:1}
.btn-primary{background:var(--accent);color:#0D0D0D}
.btn-ghost{background:transparent;border:1px solid var(--border);color:var(--muted)}
.btn-danger{background:var(--danger);color:#fff}
.btn-sm{padding:4px 9px;font-size:12px;border-radius:6px}
form.il{display:inline}
label.lbl{display:block;font-size:12px;color:var(--muted);margin-bottom:4px;font-weight:600}
input[type=text],input[type=email],input[type=url],input[type=number],input[type=tel],input[type=password],input[type=time],select,textarea{width:100%;padding:8px 10px;border-radius:8px;border:1px solid var(--border);background:var(--bg);color:var(--fg);font-size:13px}
input:focus,select:focus,textarea:focus{outline:none;border-color:rgba(255,184,0,.5)}
.g2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.g3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px}
.f{margin-bottom:12px}
.msg{padding:10px 14px;border-radius:8px;margin-bottom:16px;font-size:13px}
.err{background:rgba(239,68,68,.1);border-left:3px solid var(--danger);color:#f87171}
.ok{background:rgba(255,184,0,.08);border-left:3px solid var(--accent);color:var(--accent)}
.pill{display:inline-block;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:700}
.on{background:rgba(80,180,100,.15);color:#6dca85}
.off{background:rgba(239,68,68,.15);color:#f87171}
</style></head><body>
<div class="topbar">
  <strong>KALAMPER Admin</strong>
  <nav>
    <a href="?tab=dealers" class="<?=$activeTab==='dealers'?'act':''?>">Дилеры</a>
    <a href="?tab=reviews" class="<?=$activeTab==='reviews'?'act':''?>">Отзывы</a>
    <a href="?tab=settings" class="<?=$activeTab==='settings'?'act':''?>">Настройки</a>
  </nav>
  <a class="out" href="?logout=1">Выйти</a>
</div>
<div class="main">
<?php if($message): ?><div class="msg <?=$messageErr?'err':'ok'?>"><?=h($message)?></div><?php endif; ?>

<?php if($activeTab==='dealers'): ?>

<!-- ═══ ADD DEALER ═══ -->
<div class="card"><h2>Добавить дилера</h2>
<form method="post" action="./">
  <?=csrf_field()?><input type="hidden" name="action" value="add_dealer">
  <div class="g3">
    <div class="f"><label class="lbl">Город *</label><input name="city" type="text" required placeholder="Алматы"></div>
    <div class="f"><label class="lbl">Название *</label><input name="name" type="text" required></div>
    <div class="f"><label class="lbl">Телефон *</label><input name="phone" type="tel" required></div>
  </div>
  <div class="f"><label class="lbl">Адрес *</label><input name="address" type="text" required></div>
  <div class="g3">
    <div class="f"><label class="lbl">Часы работы</label><input name="hours" type="text" placeholder="Пн-Пт 09:00-18:00"></div>
    <div class="f"><label class="lbl">Сайт</label><input name="website" type="url"></div>
    <div class="f"><label class="lbl">Email</label><input name="email" type="email"></div>
  </div>
  <div class="f" style="max-width:200px"><label class="lbl">Порядок сортировки</label><input name="sort_order" type="number" value="100"></div>
  <button class="btn btn-primary" type="submit">+ Добавить</button>
</form></div>

<!-- ═══ DEALERS TABLE ═══ -->
<div class="card">
  <h2>Все дилеры (<?=count($dealers)?>)</h2>
  <div style="overflow-x:auto"><table>
    <thead><tr><th>Город</th><th>Название</th><th>Телефон</th><th>Адрес</th><th>Статус</th><th>Действия</th></tr></thead>
    <tbody>
    <?php foreach($dealers as $d): ?>
    <tr>
      <td><?=h($d['city'])?></td>
      <td><?=h($d['name'])?></td>
      <td><?=h($d['phone'])?></td>
      <td><?=h($d['address'])?></td>
      <td><span class="pill <?=$d['is_active']?'on':'off'?>"><?=$d['is_active']?'Активен':'Скрыт'?></span></td>
      <td style="white-space:nowrap">
        <a href="?tab=dealers&edit=<?=(int)$d['id']?>" class="btn btn-ghost btn-sm">Ред.</a>
        <form class="il" method="post" action="./">
          <?=csrf_field()?><input type="hidden" name="action" value="toggle_dealer"><input type="hidden" name="id" value="<?=(int)$d['id']?>">
          <button class="btn btn-ghost btn-sm" type="submit"><?=$d['is_active']?'Скрыть':'Показать'?></button>
        </form>
        <form class="il" method="post" action="./" onsubmit="return confirm('Удалить <?=h($d['name'])?>?')">
          <?=csrf_field()?><input type="hidden" name="action" value="delete_dealer"><input type="hidden" name="id" value="<?=(int)$d['id']?>">
          <button class="btn btn-danger btn-sm" type="submit">×</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>

<?php
$editId=(int)($_GET['edit']??0);
if($editId):
    $editD=null; foreach($dealers as $d){ if((int)$d['id']===$editId){$editD=$d;break;} }
    if($editD):
?>
<div class="card" id="edit-card"><h2>Редактировать дилера</h2>
<form method="post" action="./">
  <?=csrf_field()?><input type="hidden" name="action" value="save_dealer"><input type="hidden" name="id" value="<?=$editId?>">
  <div class="g3">
    <div class="f"><label class="lbl">Город *</label><input name="city" type="text" required value="<?=h($editD['city']??'')?>"></div>
    <div class="f"><label class="lbl">Название *</label><input name="name" type="text" required value="<?=h($editD['name']??'')?>"></div>
    <div class="f"><label class="lbl">Телефон *</label><input name="phone" type="tel" required value="<?=h($editD['phone']??'')?>"></div>
  </div>
  <div class="f"><label class="lbl">Адрес *</label><input name="address" type="text" required value="<?=h($editD['address']??'')?>"></div>
  <div class="g3">
    <div class="f"><label class="lbl">Часы работы</label><input name="hours" type="text" value="<?=h($editD['hours']??'')?>"></div>
    <div class="f"><label class="lbl">Сайт</label><input name="website" type="url" value="<?=h($editD['website']??'')?>"></div>
    <div class="f"><label class="lbl">Email</label><input name="email" type="email" value="<?=h($editD['email']??'')?>"></div>
  </div>
  <div class="f" style="max-width:200px"><label class="lbl">Порядок</label><input name="sort_order" type="number" value="<?=(int)($editD['sort_order']??100)?>"></div>
  <button class="btn btn-primary" type="submit">Сохранить</button>
  <a href="?tab=dealers" class="btn btn-ghost" style="margin-left:8px">Отмена</a>
</form></div>
<script>document.getElementById('edit-card').scrollIntoView({behavior:'smooth',block:'start'});</script>
<?php endif; endif; ?>

<?php elseif($activeTab==='reviews'): ?>

<!-- ═══ REVIEWS TAB ═══ -->
<div class="card"><h2>Отзывы (<?=count($reviews)?>)</h2>
<div style="overflow-x:auto"><table>
<thead><tr><th>Имя</th><th>Email</th><th>Оценка</th><th>Отзыв</th><th>Дата</th><th>Статус</th><th>Действия</th></tr></thead>
<tbody>
<?php foreach($reviews as $r): ?>
<tr>
  <td><?=h($r['name'])?></td>
  <td><?=h($r['email'])?></td>
  <td style="color:#FFB800"><?=str_repeat('★',(int)$r['rating'])?></td>
  <td style="max-width:260px;word-break:break-word"><?=h(mb_substr((string)($r['review']??''),0,100))?><?=mb_strlen((string)($r['review']??''))>100?'…':''?></td>
  <td style="white-space:nowrap"><?=h(substr((string)($r['created_at']??''),0,10))?></td>
  <td><span class="pill <?=$r['is_published']?'on':'off'?>"><?=$r['is_published']?'Опубл.':'Скрыт'?></span></td>
  <td style="white-space:nowrap">
    <form class="il" method="post" action="./">
      <?=csrf_field()?><input type="hidden" name="action" value="toggle_review"><input type="hidden" name="id" value="<?=(int)$r['id']?>">
      <button class="btn btn-ghost btn-sm" type="submit"><?=$r['is_published']?'Скрыть':'Показать'?></button>
    </form>
    <form class="il" method="post" action="./" onsubmit="return confirm('Удалить отзыв?')">
      <?=csrf_field()?><input type="hidden" name="action" value="delete_review"><input type="hidden" name="id" value="<?=(int)$r['id']?>">
      <button class="btn btn-danger btn-sm" type="submit">×</button>
    </form>
  </td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
</div>

<?php elseif($activeTab==='settings'): ?>

<!-- ═══ SETTINGS TAB ═══ -->
<div class="card"><h2>Смена пароля</h2>
<?php if($messageErr&&$message): ?><div class="msg err"><?=h($message)?></div><?php endif; ?>
<form method="post" action="./" style="max-width:400px">
  <?=csrf_field()?><input type="hidden" name="action" value="change_password">
  <div class="f"><label class="lbl">Новый пароль (мин. 8 символов)</label><input name="new_password" type="password" required minlength="8"></div>
  <div class="f"><label class="lbl">Повторите пароль</label><input name="confirm_password" type="password" required minlength="8"></div>
  <button class="btn btn-primary" type="submit">Сохранить пароль</button>
</form>
</div>

<?php endif; ?>

</div>
</body></html>
