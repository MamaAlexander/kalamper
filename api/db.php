<?php
require_once __DIR__ . '/config.php';

function kalamper_pdo(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $c = kalamper_config()['db'];
    $dsn = "mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset={$c['charset']}";
    $pdo = new PDO($dsn, $c['user'], $c['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function kalamper_json(array $payload, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function kalamper_initialize_database(): void {
    $pdo = kalamper_pdo();
    $pdo->exec("CREATE TABLE IF NOT EXISTS kalamper_dealers (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        city VARCHAR(120) NOT NULL,
        name VARCHAR(160) NOT NULL,
        address VARCHAR(255) NOT NULL,
        phone VARCHAR(80) NOT NULL,
        hours VARCHAR(160) NOT NULL DEFAULT '',
        website VARCHAR(255) NOT NULL DEFAULT '',
        email VARCHAR(160) NOT NULL DEFAULT '',
        lat DECIMAL(10,7) NULL DEFAULT NULL,
        lng DECIMAL(10,7) NULL DEFAULT NULL,
        sort_order INT NOT NULL DEFAULT 100,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS kalamper_reviews (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(120) NOT NULL,
        email VARCHAR(160) NOT NULL,
        rating TINYINT UNSIGNED NOT NULL,
        review TEXT NOT NULL,
        is_published TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS kalamper_admin_sessions (
        id VARCHAR(128) NOT NULL PRIMARY KEY,
        data MEDIUMTEXT NOT NULL,
        expires_at DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Add lat/lng columns if missing
    $cols = array_column($pdo->query("SHOW COLUMNS FROM kalamper_dealers")->fetchAll(), 'Field');
    if (!in_array('lat', $cols)) $pdo->exec("ALTER TABLE kalamper_dealers ADD COLUMN lat DECIMAL(10,7) NULL DEFAULT NULL");
    if (!in_array('lng', $cols)) $pdo->exec("ALTER TABLE kalamper_dealers ADD COLUMN lng DECIMAL(10,7) NULL DEFAULT NULL");

    // No auto-seed: dealer data is managed via admin panel only
}
