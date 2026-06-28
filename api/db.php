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

    // Seed demo dealers if empty
    $count = (int)$pdo->query('SELECT COUNT(*) FROM kalamper_dealers')->fetchColumn();
    if ($count === 0) {
        $stmt = $pdo->prepare('INSERT INTO kalamper_dealers (city,name,address,phone,hours,sort_order,lat,lng) VALUES (?,?,?,?,?,?,?,?)');
        foreach ([
            // Казахстан
            ['Алматы','АКБ-Центр Алматы','пр. Достык, 5/1','+ 7 (727) 300-10-01','Ежедневно 09:00-20:00',10, 43.2565, 76.9286],
            ['Алматы','АвтоАккумуляторы','ул. Розыбакиева, 247а','+7 (727) 300-20-02','Пн-Сб 09:00-19:00',11, 43.2310, 76.8870],
            ['Астана','АКБ Плюс','пр. Туран, 21','+7 (7172) 28-10-01','Ежедневно 09:00-20:00',20, 51.1280, 71.4305],
            ['Астана','АвтоЭнерго','ул. Кенесары, 40','+7 (7172) 50-20-05','Пн-Пт 09:00-18:00',21, 51.1801, 71.4460],
            ['Шымкент','АКБ-Маркет','пр. Республики, 33','+7 (7252) 53-10-01','Ежедневно 08:00-19:00',30, 42.3417, 69.5901],
            ['Атырау','АвтоБатарея','ул. Азаттык, 55','+7 (7122) 35-10-02','Пн-Сб 09:00-18:00',40, 47.1167, 51.8833],
            // Россия
            ['Москва','АвтоМаг на Варшавке','Варшавское ш., 87','8 (800) 222-07-70','Ежедневно 08:00-22:00',50, 55.6602, 37.6247],
            ['Москва','АКБ Сервис МКАД','МКАД 39-й км, 1с2','8 (495) 777-44-55','Пн-Вс 09:00-21:00',51, 55.6171, 37.4640],
            ['Санкт-Петербург','Северная АКБ','Московский пр., 100','8 (812) 600-77-01','Пн-Сб 09:00-20:00',60, 59.8764, 30.3242],
            ['Санкт-Петербург','АккумуляторСПб','пр. Энгельса, 150','8 (812) 600-77-02','Ежедневно 09:00-21:00',61, 60.0264, 30.3417],
            ['Краснодар','АвтоАккум Юг','ул. Ставропольская, 78','8 (861) 201-92-01','Пн-Пт 09:00-19:00',70, 45.0355, 38.9753],
            ['Екатеринбург','УралАКБ','ул. Малышева, 51','8 (343) 300-10-01','Пн-Сб 09:00-20:00',80, 56.8379, 60.5975],
            ['Новосибирск','СибАКБ','Красный пр., 220','8 (383) 200-10-01','Ежедневно 09:00-19:00',90, 55.0302, 82.9265],
        ] as $r) $stmt->execute($r);
    }
}
