<?php
require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/_lib.php';

admin_session_start();
admin_setup_db();

if (admin_is_logged_in()) {
    header('Location: ./');
    exit;
}

$error   = '';
$success = '';
// Определяем текущий вид: login | forgot
$view = isset($_GET['view']) && $_GET['view'] === 'forgot' ? 'forgot' : 'login';

// ─── POST ─────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'login') {
        require_csrf();

        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $parts  = explode('@', $email);
        $domain = strtolower($parts[1] ?? '');

        if ($domain !== ALLOWED_DOMAIN) {
            $error = 'Доступ разрешён только для домена @' . ALLOWED_DOMAIN . '.';
        } else {
            $pdo  = kalamper_pdo();
            $stmt = $pdo->prepare("SELECT id, password_hash FROM kalamper_users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['kalamper_uid']   = $user['id'];
                $_SESSION['kalamper_email'] = $email;
                header('Location: ./');
                exit;
            } else {
                sleep(1);
                $error = 'Неверный email или пароль.';
            }
        }

    } elseif ($action === 'forgot') {
        require_csrf();
        $view = 'forgot';

        $email  = trim($_POST['email'] ?? '');
        $parts  = explode('@', $email);
        $domain = strtolower($parts[1] ?? '');

        if ($domain === ALLOWED_DOMAIN) {
            $pdo  = kalamper_pdo();
            $stmt = $pdo->prepare("SELECT id FROM kalamper_users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $pdo->prepare("UPDATE kalamper_reset_tokens SET used=1 WHERE user_id=? AND used=0")
                    ->execute([$user['id']]);

                $token   = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', time() + 3600);

                $pdo->prepare("INSERT INTO kalamper_reset_tokens (user_id, token, expires_at) VALUES (?,?,?)")
                    ->execute([$user['id'], $token, $expires]);

                send_reset_email($email, $token);
            }
        }

        $success = 'Если указанный адрес зарегистрирован, на него будет отправлено письмо со ссылкой для сброса пароля.';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Вход — Kalamper Admin</title>
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

input[type="email"],
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

.btn-outline {
    background: transparent;
    border: 1px solid #333;
    color: #999;
    margin-top: 10px;
    font-weight: 500;
}
.btn-outline:hover { border-color: #FFB800; color: #FFB800; background: transparent; }

.alert {
    padding: 12px 14px;
    border-radius: 8px;
    font-size: 14px;
    margin-bottom: 20px;
    line-height: 1.4;
}
.alert-error   { background: #2D0F0F; border: 1px solid #6B2020; color: #f99; }
.alert-success { background: #0F2018; border: 1px solid #206040; color: #6fa; }

.forgot-link {
    display: block;
    text-align: center;
    margin-top: 16px;
    font-size: 13px;
    color: #FFB800;
    text-decoration: none;
    opacity: .8;
    transition: opacity .15s;
}
.forgot-link:hover { opacity: 1; }
</style>
</head>
<body>
<div class="card">
    <div class="logo">
        <div class="logo-text">KALAMPER</div>
        <div class="logo-sub">Панель управления</div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= h($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= h($success) ?></div>
    <?php endif; ?>

    <?php if ($view === 'login'): ?>
    <!-- ═══ Форма входа ═══ -->
    <h2>Вход</h2>
    <form method="post" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="login">

        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email"
                   value="<?= h($_POST['email'] ?? '') ?>"
                   placeholder="you@akb-centr.com"
                   autocomplete="username" required>
        </div>
        <div class="form-group">
            <label for="password">Пароль</label>
            <input type="password" id="password" name="password"
                   autocomplete="current-password" required>
        </div>

        <button type="submit" class="btn">Войти</button>
    </form>

    <a href="login.php?view=forgot" class="forgot-link">Забыли пароль?</a>

    <?php else: ?>
    <!-- ═══ Форма восстановления пароля ═══ -->
    <h2>Восстановление пароля</h2>

    <?php if (!$success): ?>
    <form method="post" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="forgot">

        <div class="form-group">
            <label for="forgot_email">Ваш email</label>
            <input type="email" id="forgot_email" name="email"
                   value="<?= h($_POST['email'] ?? '') ?>"
                   placeholder="you@akb-centr.com"
                   autocomplete="username" required>
        </div>

        <button type="submit" class="btn">Отправить ссылку</button>
    </form>
    <?php endif; ?>

    <a href="login.php" class="forgot-link">← Вернуться ко входу</a>
    <?php endif; ?>
</div>
</body>
</html>
