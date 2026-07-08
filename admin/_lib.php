<?php
/**
 * Kalamper Admin — вспомогательные функции
 */

define('ALLOWED_DOMAIN', 'akb-centr.com');

// ─── Сессия ───────────────────────────────────────────────────────────────────

function admin_session_start(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;

    $secure   = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $lifetime = 60 * 60 * 8; // 8 часов

    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('kalamper_admin');
    session_start();

    // Регенерируем ID раз в 30 минут
    if (!isset($_SESSION['_last_regen'])) {
        $_SESSION['_last_regen'] = time();
    } elseif (time() - $_SESSION['_last_regen'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['_last_regen'] = time();
    }
}

function admin_require_auth(): void {
    admin_session_start();
    if (empty($_SESSION['kalamper_uid'])) {
        header('Location: login.php');
        exit;
    }
}

function admin_is_logged_in(): bool {
    admin_session_start();
    return !empty($_SESSION['kalamper_uid']);
}

// ─── HTML-экранирование ───────────────────────────────────────────────────────

function h(mixed $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ─── CSRF ─────────────────────────────────────────────────────────────────────

function csrf_token(): string {
    admin_session_start();
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">';
}

function require_csrf(): void {
    $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        exit('Ошибка CSRF-проверки. Пожалуйста, обновите страницу и попробуйте снова.');
    }
}

// ─── БД: инициализация расширенных таблиц ────────────────────────────────────

function admin_setup_db(): void {
    $pdo = kalamper_pdo();

    // Базовые таблицы из api/db.php
    kalamper_initialize_database();

    // Пользователи
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `kalamper_users` (
            `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `email`         VARCHAR(255) NOT NULL UNIQUE,
            `password_hash` VARCHAR(255) NOT NULL,
            `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Токены сброса пароля
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `kalamper_reset_tokens` (
            `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `user_id`    INT UNSIGNED NOT NULL,
            `token`      CHAR(64) NOT NULL,
            `expires_at` DATETIME NOT NULL,
            `used`       TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX (`token`),
            INDEX (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Настройки сайта (key-value)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `kalamper_settings` (
            `key`   VARCHAR(64) NOT NULL PRIMARY KEY,
            `value` TEXT NOT NULL DEFAULT ''
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Сид-пользователь
    $seedEmail    = 'reco1@akb-centr.com';
    $seedPassword = 'z928wwM4!';

    $stmt = $pdo->prepare("SELECT id FROM kalamper_users WHERE email = ? LIMIT 1");
    $stmt->execute([$seedEmail]);
    if (!$stmt->fetch()) {
        $hash = password_hash($seedPassword, PASSWORD_BCRYPT);
        $ins  = $pdo->prepare("INSERT INTO kalamper_users (email, password_hash) VALUES (?, ?)");
        $ins->execute([$seedEmail, $hash]);
    }
}

// ─── Email: сброс пароля ──────────────────────────────────────────────────────

function send_reset_email(string $email, string $token): bool {
    $baseUrl = 'https://www.kalamper.ru/admin/';
    $link    = $baseUrl . 'reset.php?token=' . urlencode($token);

    $subject = 'Сброс пароля — Kalamper Admin';
    $body    = "Здравствуйте!\r\n\r\n"
             . "Для сброса пароля перейдите по ссылке:\r\n"
             . $link . "\r\n\r\n"
             . "Ссылка действительна 1 час.\r\n\r\n"
             . "Если вы не запрашивали сброс пароля — проигнорируйте это письмо.\r\n\r\n"
             . "— Kalamper Admin";

    $headers  = "From: no-reply@kalamper.ru\r\n";
    $headers .= "Reply-To: no-reply@kalamper.ru\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    return mail($email, $subject, $body, $headers);
}

// ─── Разбор CSV ───────────────────────────────────────────────────────────────

function parse_csv_file(string $path): array {
    $content = file_get_contents($path);
    if ($content === false) return [];

    // Убираем UTF-8 BOM
    if (str_starts_with($content, "\xEF\xBB\xBF")) {
        $content = substr($content, 3);
    }

    // Определяем разделитель
    $firstLine = strtok($content, "\n");
    $delimiter = (substr_count($firstLine, ';') >= substr_count($firstLine, ',')) ? ';' : ',';

    $rows   = [];
    $lines  = str_getcsv($content, "\n");
    $header = null;

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;

        $fields = str_getcsv($line, $delimiter);

        if ($header === null) {
            $header = array_map('trim', $fields);
            continue;
        }

        $row = [];
        foreach ($header as $i => $col) {
            $row[$col] = isset($fields[$i]) ? trim($fields[$i]) : '';
        }
        $rows[] = $row;
    }

    return $rows;
}

// ─── Разбор XLSX ──────────────────────────────────────────────────────────────

function parse_xlsx_file(string $path): array {
    if (!class_exists('ZipArchive')) return [];

    $zip = new ZipArchive();
    if ($zip->open($path) !== true) return [];

    // Shared strings
    $sharedStrings = [];
    $ssXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssXml !== false) {
        $ss = simplexml_load_string($ssXml);
        if ($ss) {
            foreach ($ss->si as $si) {
                // Собираем все <t> внутри <si>
                $text = '';
                foreach ($si->xpath('.//t') as $t) {
                    $text .= (string)$t;
                }
                $sharedStrings[] = $text;
            }
        }
    }

    // Первый лист
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();

    if ($sheetXml === false) return [];

    $sheet = simplexml_load_string($sheetXml);
    if (!$sheet) return [];

    $rows   = [];
    $header = null;

    foreach ($sheet->sheetData->row as $row) {
        $rowData = [];
        foreach ($row->c as $cell) {
            $colRef = preg_replace('/[0-9]/', '', (string)$cell['r']);
            $colIdx = col_ref_to_index($colRef);

            $type  = (string)($cell['t'] ?? '');
            $value = (string)($cell->v ?? '');

            if ($type === 's') {
                // Shared string
                $value = $sharedStrings[(int)$value] ?? '';
            } elseif ($type === 'inlineStr') {
                $value = (string)($cell->is->t ?? '');
            }

            // Заполняем пропуски
            while (count($rowData) < $colIdx) $rowData[] = '';
            $rowData[$colIdx] = $value;
        }

        if ($header === null) {
            $header = array_map('trim', $rowData);
            continue;
        }

        if (empty(array_filter($rowData))) continue;

        $assoc = [];
        foreach ($header as $i => $col) {
            $assoc[$col] = isset($rowData[$i]) ? trim($rowData[$i]) : '';
        }
        $rows[] = $assoc;
    }

    return $rows;
}

function col_ref_to_index(string $ref): int {
    $ref = strtoupper($ref);
    $idx = 0;
    for ($i = 0; $i < strlen($ref); $i++) {
        $idx = $idx * 26 + (ord($ref[$i]) - ord('A') + 1);
    }
    return $idx - 1;
}
