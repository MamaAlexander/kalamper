<?php
require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/_lib.php';

admin_session_start();
admin_setup_db();

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$error   = '';
$success = '';

// ─── Валидация токена ──────────────────────────────────────────────────────────
function find_valid_token(PDO $pdo, string $token): ?array {
    if (strlen($token) !== 64) return null;

    $stmt = $pdo->prepare("
        SELECT rt.id, rt.user_id, u.email
        FROM kalamper_reset_tokens rt
        JOIN kalamper_users u ON u.id = rt.user_id
        WHERE rt.token = ?
          AND rt.used = 0
          AND rt.expires_at > NOW()
        LIMIT 1
    ");
    $stmt->execute([$token]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$pdo      = kalamper_pdo();
$tokenRow = find_valid_token($pdo, $token);

// ─── POST: смена пароля ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    if (!$tokenRow) {
        $error = 'Ссылка недействительна или устарела. Запросите новую.';
    } else {
        $newPassword = $_POST['password'] ?? '';
        $confirm     = $_POST['password_confirm'] ?? '';

        if (strlen($newPassword) < 8) {
            $error = 'Пароль должен содержать не менее 8 символов.';
        } elseif ($newPassword !== $confirm) {
            $error = 'Пароли не совпадают.';
        } else {
            $hash = password_hash($newPassword, PASSWORD_BCRYPT);

            $pdo->prepare("UPDATE kalamper_users SET password_hash=? WHERE id=?")
                ->execute([$hash, $tokenRow['user_id']]);

            $pdo->prepare("UPDATE kalamper_reset_tokens SET used=1 WHERE id=?")
                ->execute([$tokenRow['id']]);

            // Инвалидируем сессию на случай если была открыта
            session_destroy();

            header('Location: login.php?reset=ok');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Сброс пароля — Kalamper Admin</title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    background: #0D0D0D;
    color: #E5E5E5;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.card {
    background: #1A1A1A;
    border: 1px solid #2A2A2A;
    border-radius: 12px;
    padding: 40px;
    width: 100%;
    max-width: 420px;
}

.logo { text-align: center; margin-bottom: 32px; }
.logo-text {
    font-size: 28px;
    font-weight: 800;
    color: #FFB800;
    letter-spacing: 3px;
}
.logo-sub {
    font-size: 11px;
    color: #555;
    margin-top: 4px;
    text-transform: uppercase;
    letter-spacing: 2px;
}

h2 { font-size: 18px; font-weight: 600; margin-bottom: 24px; color: #fff; }

.form-group { margin-bottom: 16px; }

label {
    display: block;
    font-size: 13px;
    color: #999;
    margin-bottom: 6px;
}

input[type="password"] {
    width: 100%;
    background: #111;
    border: 1px solid #333;
    border-radius: 8px;
    padding: 11px 14px;
    color: #E5E5E5;
    font-size: 14px;
    outline: none;
    transition: border-color .15s;
}
input:focus { border-color: #FFB800; }

.hint {
    font-size: 12px;
    color: #555;
    margin-top: 4px;
}

.btn {
    display: block;
    width: 100%;
    padding: 12px;
    background: #FFB800;
    color: #0D0D0D;
    border: none;
    border-radius: 8px;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    margin-top: 8px;
    transition: background .15s;
}
.btn:hover { background: #e0a500; }

.alert {
    padding: 12px 14px;
    border-radius: 8px;
    font-size: 14px;
    margin-bottom: 20px;
    line-height: 1.4;
}
.alert-error   { background: #2D0F0F; border: 1px solid #6B2020; color: #f99; }
.alert-success { background: #0F2018; border: 1px solid #206040; color: #6fa; }
.alert-info    { background: #1A1500; border: 1px solid #4A3800; color: #FFB800; }

.back-link {
    display: block;
    text-align: center;
    margin-top: 16px;
    font-size: 13px;
    color: #FFB800;
    text-decoration: none;
    opacity: .8;
    transition: opacity .15s;
}
.back-link:hover { opacity: 1; }
</style>
</head>
<body>
<div class="card">
    <div class="logo">
        <div class="logo-text">KALAMPER</div>
        <div class="logo-sub">Сброс пароля</div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= h($error) ?></div>
    <?php endif; ?>

    <?php if (!$tokenRow && !$error): ?>
    <!-- Токен не найден / устарел -->
    <div class="alert alert-info">
        Ссылка для сброса пароля недействительна или уже использована.<br>
        Пожалуйста, запросите новую ссылку.
    </div>
    <a href="login.php?view=forgot" class="btn" style="text-decoration:none;text-align:center">
        Запросить новую ссылку
    </a>

    <?php elseif ($tokenRow): ?>
    <!-- Форма нового пароля -->
    <h2>Новый пароль</h2>
    <form method="post" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= h($token) ?>">

        <div class="form-group">
            <label for="password">Новый пароль</label>
            <input type="password" id="password" name="password"
                   autocomplete="new-password" required minlength="8">
            <div class="hint">Минимум 8 символов</div>
        </div>
        <div class="form-group">
            <label for="password_confirm">Подтверждение пароля</label>
            <input type="password" id="password_confirm" name="password_confirm"
                   autocomplete="new-password" required minlength="8">
        </div>

        <button type="submit" class="btn">Сохранить новый пароль</button>
    </form>
    <?php endif; ?>

    <a href="login.php" class="back-link">← Вернуться ко входу</a>
</div>

<script>
// Клиентская валидация совпадения паролей
const form = document.querySelector('form');
if (form) {
    form.addEventListener('submit', function(e) {
        const p1 = document.getElementById('password');
        const p2 = document.getElementById('password_confirm');
        if (p1 && p2 && p1.value !== p2.value) {
            e.preventDefault();
            p2.setCustomValidity('Пароли не совпадают');
            p2.reportValidity();
        } else if (p2) {
            p2.setCustomValidity('');
        }
    });
}
</script>
</body>
</html>
