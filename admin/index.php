<?php
require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/_lib.php';

admin_session_start();
admin_setup_db();
admin_require_auth();

$pdo        = kalamper_pdo();
$adminEmail = $_SESSION['kalamper_email'] ?? '';
$adminUid   = (int)$_SESSION['kalamper_uid'];
$activeTab  = $_GET['tab'] ?? 'dealers';
$flashOk    = '';
$flashErr   = '';

// ════════════════════════════════════════════════════════════════════════════════
// Вспомогательные функции (нужны до POST-обработки)
// ════════════════════════════════════════════════════════════════════════════════

function parse_coords(string $lat, string $lng): array {
    $lat = (float)str_replace(',', '.', $lat);
    $lng = (float)str_replace(',', '.', $lng);
    return [$lat ?: null, $lng ?: null];
}

function parse_coords_string(string $coords): array {
    $coords = preg_replace('/\s+/', '', $coords);
    $parts  = explode(',', $coords, 2);
    if (count($parts) === 2) {
        return [
            (float)str_replace(',', '.', $parts[0]) ?: null,
            (float)str_replace(',', '.', $parts[1]) ?: null,
        ];
    }
    return [null, null];
}

function star_rating(int $n): string {
    $s = '';
    for ($i = 1; $i <= 5; $i++) $s .= $i <= $n ? '★' : '☆';
    return $s;
}

// ════════════════════════════════════════════════════════════════════════════════
// POST — обработка всех действий
// ════════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $act = $_POST['act'] ?? '';

    // ── Дилеры ─────────────────────────────────────────────────────────────────

    if ($act === 'dealer_add') {
        $activeTab = 'dealers';
        $f = fn(string $k) => trim($_POST[$k] ?? '');
        [$lat, $lng] = parse_coords($f('lat'), $f('lng'));

        $pdo->prepare("INSERT INTO kalamper_dealers
            (city,name,address,hours,website,email,lat,lng,sort_order,is_active)
            VALUES (?,?,?,?,?,?,?,?,?,1)")
            ->execute([
                $f('city'), $f('name'), $f('address'),
                $f('hours'), $f('website'), $f('email'),
                $lat, $lng, (int)$f('sort_order'),
            ]);
        $flashOk = 'Дилер добавлен.';

    } elseif ($act === 'dealer_edit') {
        $activeTab = 'dealers';
        $f  = fn(string $k) => trim($_POST[$k] ?? '');
        $id = (int)($_POST['id'] ?? 0);
        [$lat, $lng] = parse_coords($f('lat'), $f('lng'));

        $pdo->prepare("UPDATE kalamper_dealers SET
            city=?,name=?,address=?,hours=?,website=?,email=?,
            lat=?,lng=?,sort_order=?,is_active=?
            WHERE id=?")
            ->execute([
                $f('city'), $f('name'), $f('address'),
                $f('hours'), $f('website'), $f('email'),
                $lat, $lng, (int)$f('sort_order'),
                isset($_POST['is_active']) ? 1 : 0,
                $id,
            ]);
        $flashOk = 'Дилер обновлён.';

    } elseif ($act === 'dealer_delete') {
        $activeTab = 'dealers';
        $pdo->prepare("DELETE FROM kalamper_dealers WHERE id=?")
            ->execute([(int)($_POST['id'] ?? 0)]);
        $flashOk = 'Дилер удалён.';

    } elseif ($act === 'dealer_toggle') {
        $activeTab = 'dealers';
        $pdo->prepare("UPDATE kalamper_dealers SET is_active=? WHERE id=?")
            ->execute([(int)($_POST['val'] ?? 0), (int)($_POST['id'] ?? 0)]);

    } elseif ($act === 'dealer_csv_export') {
        $rows = $pdo->query("
            SELECT city,name,address,phone,lat,lng,hours,website,email,sort_order
            FROM kalamper_dealers ORDER BY sort_order, id
        ")->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="dealers.csv"');
        echo "\xEF\xBB\xBF"; // UTF-8 BOM

        $out = fopen('php://output', 'w');
        fputcsv($out, ['city','name','address','phone','lat','lng','hours','website','email','sort_order']);
        foreach ($rows as $r) fputcsv($out, $r);
        fclose($out);
        exit;

    } elseif ($act === 'dealer_import') {
        $activeTab = 'dealers';
        $file    = $_FILES['import_file'] ?? null;
        $replace = !empty($_POST['replace_all']);

        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $flashErr = 'Ошибка загрузки файла.';
        } else {
            $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $tmp  = $file['tmp_name'];

            $data = null;
            if ($ext === 'csv') {
                $data = parse_csv_file($tmp);
            } elseif (in_array($ext, ['xlsx', 'xls'])) {
                $data = parse_xlsx_file($tmp);
            } else {
                $flashErr = 'Поддерживаются форматы: CSV, XLSX, XLS.';
            }

            if ($data !== null) {
                if ($replace) $pdo->exec("DELETE FROM kalamper_dealers");

                $ins = $pdo->prepare("INSERT INTO kalamper_dealers
                    (city,name,address,phone,lat,lng,hours,website,email,sort_order,is_active)
                    VALUES (?,?,?,?,?,?,?,?,?,?,1)");

                $count = 0;
                foreach ($data as $row) {
                    $r = [];
                    foreach ($row as $k => $v) $r[strtolower(trim($k))] = trim((string)$v);

                    $lat = $r['lat'] ?? '';
                    $lng = $r['lng'] ?? '';

                    if (!empty($r['coords'])) {
                        [$lat, $lng] = parse_coords_string($r['coords']);
                    } else {
                        [$lat, $lng] = parse_coords($lat, $lng);
                    }

                    $ins->execute([
                        $r['city']       ?? '',
                        $r['name']       ?? '',
                        $r['address']    ?? '',
                        $r['phone']      ?? '',
                        $lat,
                        $lng,
                        $r['hours']      ?? '',
                        $r['website']    ?? '',
                        $r['email']      ?? '',
                        (int)($r['sort_order'] ?? 0),
                    ]);
                    $count++;
                }
                $flashOk = "Импортировано дилеров: {$count}.";
            }
        }

    // ── Отзывы ─────────────────────────────────────────────────────────────────

    } elseif ($act === 'review_edit') {
        $activeTab = 'reviews';
        $id = (int)($_POST['id'] ?? 0);
        $f  = fn(string $k) => trim($_POST[$k] ?? '');

        $pdo->prepare("UPDATE kalamper_reviews SET
            name=?,email=?,rating=?,review=?,created_at=?,is_published=?
            WHERE id=?")
            ->execute([
                $f('name'), $f('email'),
                max(1, min(5, (int)$f('rating'))),
                $f('review'),
                $f('created_at') ?: date('Y-m-d H:i:s'),
                isset($_POST['is_published']) ? 1 : 0,
                $id,
            ]);
        $flashOk = 'Отзыв обновлён.';

    } elseif ($act === 'review_delete') {
        $activeTab = 'reviews';
        $pdo->prepare("DELETE FROM kalamper_reviews WHERE id=?")
            ->execute([(int)($_POST['id'] ?? 0)]);
        $flashOk = 'Отзыв удалён.';

    } elseif ($act === 'review_toggle') {
        $activeTab = 'reviews';
        $pdo->prepare("UPDATE kalamper_reviews SET is_published=? WHERE id=?")
            ->execute([(int)($_POST['val'] ?? 0), (int)($_POST['id'] ?? 0)]);

    // ── Настройки: контактный телефон ──────────────────────────────────────────

    } elseif ($act === 'save_contact_phone') {
        $activeTab = 'settings';
        $phone = trim($_POST['contact_phone'] ?? '');
        $pdo->prepare("INSERT INTO kalamper_settings (`key`,`value`) VALUES ('contact_phone',?) ON DUPLICATE KEY UPDATE `value`=?")
            ->execute([$phone, $phone]);
        $flashOk = 'Контактный телефон сохранён.';

    // ── Настройки: смена пароля ─────────────────────────────────────────────────

    } elseif ($act === 'change_password') {
        $activeTab = 'settings';
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $stmt = $pdo->prepare("SELECT password_hash FROM kalamper_users WHERE id=?");
        $stmt->execute([$adminUid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || !password_verify($current, $row['password_hash'])) {
            $flashErr = 'Неверный текущий пароль.';
        } elseif (strlen($new) < 8) {
            $flashErr = 'Новый пароль должен быть не менее 8 символов.';
        } elseif ($new !== $confirm) {
            $flashErr = 'Новые пароли не совпадают.';
        } else {
            $pdo->prepare("UPDATE kalamper_users SET password_hash=? WHERE id=?")
                ->execute([password_hash($new, PASSWORD_BCRYPT), $adminUid]);
            $flashOk = 'Пароль успешно изменён.';
        }

    // ── Настройки: добавить пользователя ───────────────────────────────────────

    } elseif ($act === 'user_add') {
        $activeTab = 'settings';
        $email     = trim($_POST['user_email']    ?? '');
        $password  = $_POST['user_password'] ?? '';
        $domain    = strtolower(explode('@', $email)[1] ?? '');

        if ($domain !== ALLOWED_DOMAIN) {
            $flashErr = 'Email должен быть на домене @' . ALLOWED_DOMAIN . '.';
        } elseif (strlen($password) < 8) {
            $flashErr = 'Пароль должен содержать не менее 8 символов.';
        } else {
            $chk = $pdo->prepare("SELECT id FROM kalamper_users WHERE email=?");
            $chk->execute([$email]);
            if ($chk->fetch()) {
                $flashErr = 'Пользователь с таким email уже существует.';
            } else {
                $pdo->prepare("INSERT INTO kalamper_users (email,password_hash) VALUES (?,?)")
                    ->execute([$email, password_hash($password, PASSWORD_BCRYPT)]);
                $flashOk = 'Пользователь добавлен.';
            }
        }

    // ── Настройки: удалить пользователя ────────────────────────────────────────

    } elseif ($act === 'user_delete') {
        $activeTab = 'settings';
        $uid = (int)($_POST['uid'] ?? 0);
        if ($uid === $adminUid) {
            $flashErr = 'Нельзя удалить собственную учётную запись.';
        } else {
            $pdo->prepare("DELETE FROM kalamper_users WHERE id=?")->execute([$uid]);
            $flashOk = 'Пользователь удалён.';
        }
    }

    // Redirect-after-Post
    $loc = 'index.php?tab=' . urlencode($activeTab);
    if ($flashOk)  $loc .= '&flash_ok='  . urlencode($flashOk);
    if ($flashErr) $loc .= '&flash_err=' . urlencode($flashErr);
    header('Location: ' . $loc);
    exit;
}

// Flash из GET (после redirect)
$flashOk  = $_GET['flash_ok']  ?? '';
$flashErr = $_GET['flash_err'] ?? '';
$activeTab = $_GET['tab'] ?? 'dealers';

// ── Данные ─────────────────────────────────────────────────────────────────────
$dealers = $pdo->query("SELECT * FROM kalamper_dealers ORDER BY sort_order, id")->fetchAll(PDO::FETCH_ASSOC);
$reviews = $pdo->query("SELECT * FROM kalamper_reviews ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$users   = $pdo->query("SELECT id, email, created_at FROM kalamper_users ORDER BY created_at")->fetchAll(PDO::FETCH_ASSOC);
$s = $pdo->query("SELECT value FROM kalamper_settings WHERE `key`='contact_phone' LIMIT 1");
$currentContactPhone = $s ? (string)($s->fetchColumn() ?: '') : '';

$editDealerId = (int)($_GET['edit_dealer'] ?? 0);
$editReviewId = (int)($_GET['edit_review'] ?? 0);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kalamper Admin</title>
<style>
/* ── Reset ──────────────────────────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

/* ── Base ───────────────────────────────────────────────────────────────── */
body {
    background: #0D0D0D;
    color: #E0E0E0;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 14px;
    min-height: 100vh;
}
a { color: #FFB800; text-decoration: none; }
a:hover { text-decoration: underline; }

/* ── Header ─────────────────────────────────────────────────────────────── */
.header {
    background: #111;
    border-bottom: 1px solid #222;
    padding: 0 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 56px;
    position: sticky;
    top: 0;
    z-index: 100;
}
.header-logo { font-size: 20px; font-weight: 800; color: #FFB800; letter-spacing: 3px; }
.header-user { display: flex; align-items: center; gap: 16px; font-size: 13px; color: #666; }
.btn-logout {
    background: #1A1A1A;
    border: 1px solid #2E2E2E;
    color: #AAA;
    padding: 6px 14px;
    border-radius: 6px;
    font-size: 13px;
    cursor: pointer;
    transition: border-color .15s, color .15s;
    text-decoration: none;
    display: inline-block;
}
.btn-logout:hover { border-color: #FFB800; color: #FFB800; text-decoration: none; }

/* ── Layout ─────────────────────────────────────────────────────────────── */
.main { padding: 24px; max-width: 1400px; margin: 0 auto; }

/* ── Flash ──────────────────────────────────────────────────────────────── */
.flash {
    padding: 12px 16px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 14px;
    line-height: 1.4;
}
.flash-ok  { background: #0F2018; border: 1px solid #1E5030; color: #6fa; }
.flash-err { background: #2D0F0F; border: 1px solid #6B1A1A; color: #f99; }

/* ── Tabs ───────────────────────────────────────────────────────────────── */
.tabs { display: flex; gap: 2px; border-bottom: 1px solid #222; margin-bottom: 24px; }
.tab-btn {
    background: none;
    border: none;
    border-bottom: 2px solid transparent;
    padding: 10px 20px;
    color: #666;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: color .15s, border-color .15s;
    text-decoration: none;
    display: inline-block;
    margin-bottom: -1px;
}
.tab-btn:hover { color: #CCC; text-decoration: none; }
.tab-btn.active { color: #FFB800; border-bottom-color: #FFB800; }
.tab-cnt { color: #444; font-size: 11px; margin-left: 4px; }

/* ── Card ───────────────────────────────────────────────────────────────── */
.card {
    background: #1A1A1A;
    border: 1px solid #252525;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
}
.card-title {
    font-size: 15px;
    font-weight: 600;
    color: #FFF;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid #222;
}
.card-sub { font-size: 13px; color: #666; margin-bottom: 14px; line-height: 1.6; }

/* ── Forms ──────────────────────────────────────────────────────────────── */
.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(175px, 1fr));
    gap: 12px;
    margin-bottom: 14px;
}
.form-group { display: flex; flex-direction: column; gap: 5px; }
label { font-size: 12px; color: #888; }

input[type="text"],
input[type="email"],
input[type="tel"],
input[type="url"],
input[type="number"],
input[type="password"],
input[type="datetime-local"],
select,
textarea {
    background: #111;
    border: 1px solid #2A2A2A;
    border-radius: 6px;
    padding: 8px 10px;
    color: #E0E0E0;
    font-size: 13px;
    outline: none;
    width: 100%;
    transition: border-color .15s;
    font-family: inherit;
}
input:focus, select:focus, textarea:focus { border-color: #FFB800; }
textarea { resize: vertical; min-height: 80px; }
select option { background: #1A1A1A; }

/* ── Buttons ────────────────────────────────────────────────────────────── */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: background .15s, opacity .15s;
    text-decoration: none;
    white-space: nowrap;
}
.btn-primary { background: #FFB800; color: #0D0D0D; }
.btn-primary:hover { background: #e0a500; text-decoration: none; color: #0D0D0D; }
.btn-sm { padding: 5px 10px; font-size: 12px; }
.btn-edit { background: #172030; color: #6AB3F8; border: 1px solid #1E3550; }
.btn-edit:hover { background: #1D2D40; text-decoration: none; color: #6AB3F8; }
.btn-delete { background: #2D1515; color: #f88; border: 1px solid #501A1A; }
.btn-delete:hover { background: #3a1818; text-decoration: none; color: #f88; }
.btn-secondary { background: #222; color: #BBB; border: 1px solid #333; }
.btn-secondary:hover { background: #2A2A2A; text-decoration: none; color: #DDD; }

/* ── Table ──────────────────────────────────────────────────────────────── */
.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; font-size: 13px; }
thead th {
    background: #141414;
    color: #888;
    font-weight: 600;
    padding: 10px 12px;
    text-align: left;
    white-space: nowrap;
    border-bottom: 1px solid #222;
}
tbody tr { border-bottom: 1px solid #1A1A1A; transition: background .1s; }
tbody tr:hover { background: #1E1E1E; }
tbody td { padding: 9px 12px; vertical-align: middle; color: #CCC; }

/* ── Badge ──────────────────────────────────────────────────────────────── */
.badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    border: none;
}
.badge-on  { background: #152515; color: #5da; border: 1px solid #1E4020; }
.badge-off { background: #1E1E1E; color: #666; border: 1px solid #2A2A2A; }

/* ── Stars ──────────────────────────────────────────────────────────────── */
.stars { color: #FFB800; }

/* ── Edit panel ─────────────────────────────────────────────────────────── */
.edit-panel {
    background: #111;
    border: 1px solid #222;
    border-radius: 8px;
    padding: 18px;
    margin: 4px 0;
}
.edit-panel .card-title { font-size: 14px; }

/* ── Misc ───────────────────────────────────────────────────────────────── */
input[type="file"] {
    background: #111;
    border: 1px dashed #2A2A2A;
    border-radius: 6px;
    padding: 8px 10px;
    color: #888;
    font-size: 13px;
    cursor: pointer;
    width: 100%;
}
input[type="file"]:hover { border-color: #FFB800; }

code {
    background: #111;
    border: 1px solid #2A2A2A;
    border-radius: 4px;
    padding: 2px 6px;
    font-size: 12px;
    color: #AAA;
    font-family: monospace;
}

.check-row { display: flex; align-items: center; gap: 8px; margin: 6px 0; }
input[type="checkbox"] { width: 15px; height: 15px; accent-color: #FFB800; cursor: pointer; flex-shrink: 0; }

.truncate { max-width: 180px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.actions { display: flex; gap: 6px; align-items: center; }
.divider { border: none; border-top: 1px solid #222; margin: 20px 0; }
.row-between {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 16px;
}
.self-tag {
    display: inline-block;
    background: #1A2818;
    color: #5da;
    border: 1px solid #1E4020;
    border-radius: 4px;
    font-size: 10px;
    padding: 1px 6px;
    margin-left: 6px;
    vertical-align: middle;
}
</style>
</head>
<body>

<header class="header">
    <div class="header-logo">KALAMPER</div>
    <div class="header-user">
        <span><?= h($adminEmail) ?></span>
        <a href="logout.php" class="btn-logout">Выйти</a>
    </div>
</header>

<main class="main">

<?php if ($flashOk): ?>
    <div class="flash flash-ok"><?= h($flashOk) ?></div>
<?php endif; ?>
<?php if ($flashErr): ?>
    <div class="flash flash-err"><?= h($flashErr) ?></div>
<?php endif; ?>

<!-- ── Tabs ── -->
<nav class="tabs">
    <?php
    $tabDefs = [
        'dealers'  => ['Дилеры',   count($dealers)],
        'reviews'  => ['Отзывы',   count($reviews)],
        'settings' => ['Настройки', null],
    ];
    foreach ($tabDefs as $t => [$label, $cnt]): ?>
        <a href="?tab=<?= $t ?>" class="tab-btn <?= $activeTab === $t ? 'active' : '' ?>">
            <?= $label ?>
            <?php if ($cnt !== null): ?>
                <span class="tab-cnt">(<?= $cnt ?>)</span>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
</nav>


<!-- ════════════════════════════════════════════════════════════════════════════
     ДИЛЕРЫ
════════════════════════════════════════════════════════════════════════════ -->
<?php if ($activeTab === 'dealers'): ?>

<div class="card">
    <div class="card-title">Добавить дилера</div>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="act" value="dealer_add">
        <div class="form-grid">
            <div class="form-group"><label>Город *</label>
                <input type="text" name="city" required></div>
            <div class="form-group"><label>Название *</label>
                <input type="text" name="name" required></div>
            <div class="form-group"><label>Адрес</label>
                <input type="text" name="address"></div>
            <div class="form-group"><label>Часы работы</label>
                <input type="text" name="hours" placeholder="Пн–Пт 9:00–18:00"></div>
            <div class="form-group"><label>Сайт</label>
                <input type="url" name="website" placeholder="https://"></div>
            <div class="form-group"><label>Email</label>
                <input type="email" name="email"></div>
            <div class="form-group"><label>Широта (lat)</label>
                <input type="text" name="lat" placeholder="55.7558"></div>
            <div class="form-group"><label>Долгота (lng)</label>
                <input type="text" name="lng" placeholder="37.6173"></div>
            <div class="form-group"><label>Порядок (sort)</label>
                <input type="number" name="sort_order" value="0" min="0"></div>
        </div>
        <button type="submit" class="btn btn-primary">+ Добавить</button>
    </form>
</div>

<div class="card">
    <div class="row-between">
        <div class="card-title" style="margin:0;padding:0;border:none">Все дилеры</div>
        <form method="post" style="margin:0">
            <?= csrf_field() ?>
            <input type="hidden" name="act" value="dealer_csv_export">
            <button type="submit" class="btn btn-secondary btn-sm">↓ Скачать CSV</button>
        </form>
    </div>

    <?php if ($dealers): ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Город</th>
                    <th>Название</th>
                    <th>Адрес</th>
                    <th>Статус</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($dealers as $i => $d): ?>
                <tr>
                    <td style="color:#444"><?= $i + 1 ?></td>
                    <td><?= h($d['city']) ?></td>
                    <td><?= h($d['name']) ?></td>
                    <td class="truncate"><?= h($d['address']) ?></td>
                    <td>
                        <form method="post" style="margin:0">
                            <?= csrf_field() ?>
                            <input type="hidden" name="act" value="dealer_toggle">
                            <input type="hidden" name="id"  value="<?= $d['id'] ?>">
                            <input type="hidden" name="val" value="<?= $d['is_active'] ? 0 : 1 ?>">
                            <button type="submit" class="badge <?= $d['is_active'] ? 'badge-on' : 'badge-off' ?>">
                                <?= $d['is_active'] ? 'Активен' : 'Скрыт' ?>
                            </button>
                        </form>
                    </td>
                    <td>
                        <div class="actions">
                            <a href="?tab=dealers&edit_dealer=<?= $d['id'] ?>#ep-<?= $d['id'] ?>"
                               class="btn btn-edit btn-sm">Ред.</a>
                            <form method="post" style="margin:0"
                                  onsubmit="return confirm('Удалить дилера «<?= h(addslashes($d['name'])) ?>»?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="act" value="dealer_delete">
                                <input type="hidden" name="id"  value="<?= $d['id'] ?>">
                                <button type="submit" class="btn btn-delete btn-sm">×</button>
                            </form>
                        </div>
                    </td>
                </tr>

                <?php if ($editDealerId === (int)$d['id']): ?>
                <tr id="ep-<?= $d['id'] ?>">
                    <td colspan="7" style="padding:0 0 8px 0">
                        <div class="edit-panel">
                            <div class="card-title">Редактировать: <?= h($d['name']) ?></div>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="act" value="dealer_edit">
                                <input type="hidden" name="id"  value="<?= $d['id'] ?>">
                                <div class="form-grid">
                                    <div class="form-group"><label>Город</label>
                                        <input type="text" name="city" value="<?= h($d['city']) ?>" required></div>
                                    <div class="form-group"><label>Название</label>
                                        <input type="text" name="name" value="<?= h($d['name']) ?>" required></div>
                                    <div class="form-group"><label>Адрес</label>
                                        <input type="text" name="address" value="<?= h($d['address']) ?>"></div>
                                    <div class="form-group"><label>Часы работы</label>
                                        <input type="text" name="hours" value="<?= h($d['hours']) ?>"></div>
                                    <div class="form-group"><label>Сайт</label>
                                        <input type="url" name="website" value="<?= h($d['website']) ?>"></div>
                                    <div class="form-group"><label>Email</label>
                                        <input type="email" name="email" value="<?= h($d['email']) ?>"></div>
                                    <div class="form-group"><label>Широта (lat)</label>
                                        <input type="text" name="lat" value="<?= h($d['lat']) ?>"></div>
                                    <div class="form-group"><label>Долгота (lng)</label>
                                        <input type="text" name="lng" value="<?= h($d['lng']) ?>"></div>
                                    <div class="form-group"><label>Порядок</label>
                                        <input type="number" name="sort_order" value="<?= (int)$d['sort_order'] ?>"></div>
                                </div>
                                <div class="check-row">
                                    <input type="checkbox" name="is_active" id="ea<?= $d['id'] ?>"
                                           <?= $d['is_active'] ? 'checked' : '' ?>>
                                    <label for="ea<?= $d['id'] ?>">Активен (показывать на сайте)</label>
                                </div>
                                <div style="display:flex;gap:10px;margin-top:14px">
                                    <button type="submit" class="btn btn-primary">Сохранить</button>
                                    <a href="?tab=dealers" class="btn btn-secondary">Отмена</a>
                                </div>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>

            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <p style="color:#444;text-align:center;padding:32px">Дилеры не добавлены.</p>
    <?php endif; ?>
</div>

<!-- Импорт -->
<div class="card">
    <div class="card-title">Импорт дилеров</div>
    <div class="card-sub">
        Поддерживаются форматы <strong style="color:#CCC">CSV</strong>,
        <strong style="color:#CCC">XLSX</strong>, <strong style="color:#CCC">XLS</strong>.<br>
        Колонки: <code>city</code> <code>name</code> <code>address</code> <code>phone</code>
        <code>lat</code> <code>lng</code> <code>hours</code> <code>website</code> <code>email</code>
        <code>sort_order</code><br>
        Вместо <code>lat</code>+<code>lng</code> можно использовать колонку
        <code>coords</code> со значением вида <code>55.7558, 37.6173</code>.
    </div>
    <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="act" value="dealer_import">
        <div class="form-group" style="margin-bottom:12px">
            <label>Файл (.csv / .xlsx / .xls)</label>
            <input type="file" name="import_file" accept=".csv,.xlsx,.xls" required>
        </div>
        <div class="check-row" style="margin-bottom:14px">
            <input type="checkbox" name="replace_all" id="replace_all" checked>
            <label for="replace_all">Заменить всех дилеров (удалить существующих перед импортом)</label>
        </div>
        <button type="submit" class="btn btn-primary">Загрузить</button>
    </form>
</div>


<!-- ════════════════════════════════════════════════════════════════════════════
     ОТЗЫВЫ
════════════════════════════════════════════════════════════════════════════ -->
<?php elseif ($activeTab === 'reviews'): ?>

<div class="card">
    <div class="card-title">Все отзывы</div>

    <?php if ($reviews): ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Имя</th>
                    <th>Email</th>
                    <th>Оценка</th>
                    <th>Текст</th>
                    <th>Дата</th>
                    <th>Статус</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($reviews as $rev): ?>
                <tr>
                    <td><?= h($rev['name']) ?></td>
                    <td style="color:#666"><?= h($rev['email']) ?></td>
                    <td><span class="stars"><?= star_rating((int)$rev['rating']) ?></span></td>
                    <td class="truncate"><?= h($rev['review']) ?></td>
                    <td style="white-space:nowrap;color:#555">
                        <?= h(substr($rev['created_at'], 0, 10)) ?>
                    </td>
                    <td>
                        <form method="post" style="margin:0">
                            <?= csrf_field() ?>
                            <input type="hidden" name="act" value="review_toggle">
                            <input type="hidden" name="id"  value="<?= $rev['id'] ?>">
                            <input type="hidden" name="val" value="<?= $rev['is_published'] ? 0 : 1 ?>">
                            <button type="submit" class="badge <?= $rev['is_published'] ? 'badge-on' : 'badge-off' ?>">
                                <?= $rev['is_published'] ? 'Опубл.' : 'Скрыт' ?>
                            </button>
                        </form>
                    </td>
                    <td>
                        <div class="actions">
                            <a href="?tab=reviews&edit_review=<?= $rev['id'] ?>#er-<?= $rev['id'] ?>"
                               class="btn btn-edit btn-sm">Ред.</a>
                            <form method="post" style="margin:0"
                                  onsubmit="return confirm('Удалить отзыв от <?= h(addslashes($rev['name'])) ?>?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="act" value="review_delete">
                                <input type="hidden" name="id"  value="<?= $rev['id'] ?>">
                                <button type="submit" class="btn btn-delete btn-sm">×</button>
                            </form>
                        </div>
                    </td>
                </tr>

                <?php if ($editReviewId === (int)$rev['id']): ?>
                <tr id="er-<?= $rev['id'] ?>">
                    <td colspan="7" style="padding:0 0 8px 0">
                        <div class="edit-panel">
                            <div class="card-title">Редактировать отзыв — <?= h($rev['name']) ?></div>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="act" value="review_edit">
                                <input type="hidden" name="id"  value="<?= $rev['id'] ?>">
                                <div class="form-grid">
                                    <div class="form-group"><label>Имя</label>
                                        <input type="text" name="name" value="<?= h($rev['name']) ?>"></div>
                                    <div class="form-group"><label>Email</label>
                                        <input type="email" name="email" value="<?= h($rev['email']) ?>"></div>
                                    <div class="form-group"><label>Оценка</label>
                                        <select name="rating">
                                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                                <option value="<?= $i ?>"
                                                    <?= (int)$rev['rating'] === $i ? 'selected' : '' ?>>
                                                    <?= $i ?> <?= star_rating($i) ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <div class="form-group"><label>Дата</label>
                                        <input type="datetime-local" name="created_at"
                                               value="<?= h(str_replace(' ', 'T', substr($rev['created_at'], 0, 16))) ?>">
                                    </div>
                                </div>
                                <div class="form-group" style="margin-bottom:12px">
                                    <label>Текст отзыва</label>
                                    <textarea name="review" rows="4"><?= h($rev['review']) ?></textarea>
                                </div>
                                <div class="check-row" style="margin-bottom:14px">
                                    <input type="checkbox" name="is_published" id="rp<?= $rev['id'] ?>"
                                           <?= $rev['is_published'] ? 'checked' : '' ?>>
                                    <label for="rp<?= $rev['id'] ?>">Опубликован (виден на сайте)</label>
                                </div>
                                <div style="display:flex;gap:10px">
                                    <button type="submit" class="btn btn-primary">Сохранить</button>
                                    <a href="?tab=reviews" class="btn btn-secondary">Отмена</a>
                                </div>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>

            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <p style="color:#444;text-align:center;padding:32px">Отзывов пока нет.</p>
    <?php endif; ?>
</div>


<!-- ════════════════════════════════════════════════════════════════════════════
     НАСТРОЙКИ
════════════════════════════════════════════════════════════════════════════ -->
<?php elseif ($activeTab === 'settings'): ?>

<!-- Контактный телефон -->
<div class="card">
    <div class="card-title">Контактный телефон</div>
    <p style="color:#888;font-size:14px;margin-bottom:16px">
        Этот номер отображается на всех карточках дилеров вместо индивидуальных телефонов.
    </p>
    <form method="post" style="max-width:420px">
        <?= csrf_field() ?>
        <input type="hidden" name="act" value="save_contact_phone">
        <div class="form-group" style="margin-bottom:16px">
            <label>Номер телефона</label>
            <input type="tel" name="contact_phone" value="<?= h($currentContactPhone) ?>" placeholder="+7 (XXX) XXX-XX-XX">
        </div>
        <button type="submit" class="btn btn-primary">Сохранить</button>
    </form>
</div>

<!-- Смена пароля -->
<div class="card">
    <div class="card-title">Смена пароля</div>
    <form method="post" style="max-width:420px" id="pwForm">
        <?= csrf_field() ?>
        <input type="hidden" name="act" value="change_password">
        <div class="form-group" style="margin-bottom:12px">
            <label>Текущий пароль</label>
            <input type="password" name="current_password" autocomplete="current-password" required>
        </div>
        <div class="form-group" style="margin-bottom:12px">
            <label>Новый пароль (мин. 8 символов)</label>
            <input type="password" name="new_password" id="np" autocomplete="new-password" required minlength="8">
        </div>
        <div class="form-group" style="margin-bottom:16px">
            <label>Подтверждение нового пароля</label>
            <input type="password" name="confirm_password" id="cp" autocomplete="new-password" required minlength="8">
        </div>
        <button type="submit" class="btn btn-primary">Изменить пароль</button>
    </form>
</div>

<!-- Пользователи -->
<div class="card">
    <div class="card-title">Пользователи системы</div>
    <?php if ($users): ?>
    <div class="table-wrap" style="margin-bottom:20px">
        <table>
            <thead>
                <tr><th>Email</th><th>Зарегистрирован</th><th>Действие</th></tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td>
                        <?= h($u['email']) ?>
                        <?php if ((int)$u['id'] === $adminUid): ?>
                            <span class="self-tag">вы</span>
                        <?php endif; ?>
                    </td>
                    <td style="color:#555"><?= h(substr($u['created_at'], 0, 10)) ?></td>
                    <td>
                        <?php if ((int)$u['id'] !== $adminUid): ?>
                        <form method="post" style="margin:0"
                              onsubmit="return confirm('Удалить пользователя <?= h(addslashes($u['email'])) ?>?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="act" value="user_delete">
                            <input type="hidden" name="uid" value="<?= $u['id'] ?>">
                            <button type="submit" class="btn btn-delete btn-sm">Удалить</button>
                        </form>
                        <?php else: ?>
                            <span style="color:#333">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <hr class="divider">
    <div style="font-size:15px;font-weight:600;color:#FFF;margin-bottom:14px">Добавить пользователя</div>
    <form method="post" style="max-width:500px">
        <?= csrf_field() ?>
        <input type="hidden" name="act" value="user_add">
        <div class="form-grid" style="grid-template-columns:1fr 1fr;margin-bottom:14px">
            <div class="form-group">
                <label>Email (только @akb-centr.com)</label>
                <input type="email" name="user_email" placeholder="user@akb-centr.com" required>
            </div>
            <div class="form-group">
                <label>Пароль (мин. 8 символов)</label>
                <input type="password" name="user_password" required minlength="8">
            </div>
        </div>
        <button type="submit" class="btn btn-primary">+ Добавить пользователя</button>
    </form>
</div>

<?php endif; ?>

</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Прокрутка к панели редактирования дилера
    const ep = document.querySelector('[id^="ep-"]');
    if (ep) ep.scrollIntoView({ behavior: 'smooth', block: 'center' });

    // Прокрутка к панели редактирования отзыва
    const er = document.querySelector('[id^="er-"]');
    if (er) er.scrollIntoView({ behavior: 'smooth', block: 'center' });

    // Клиентская проверка совпадения паролей (смена пароля)
    const pwForm = document.getElementById('pwForm');
    if (pwForm) {
        pwForm.addEventListener('submit', function (e) {
            const np = document.getElementById('np');
            const cp = document.getElementById('cp');
            if (np && cp && np.value !== cp.value) {
                e.preventDefault();
                cp.setCustomValidity('Пароли не совпадают');
                cp.reportValidity();
            } else if (cp) {
                cp.setCustomValidity('');
            }
        });
        document.getElementById('cp')?.addEventListener('input', function () {
            this.setCustomValidity('');
        });
    }
});
</script>

</body>
</html>
