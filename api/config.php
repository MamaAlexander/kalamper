<?php
function kalamper_config(): array {
    $local = __DIR__ . '/config.local.php';
    if (file_exists($local)) { $cfg = require $local; if (is_array($cfg)) return $cfg; }
    return [
        'db' => [
            'host' => getenv('DB_HOST') ?: '127.0.0.1',
            'port' => (int)(getenv('DB_PORT') ?: 3306),
            'name' => getenv('DB_NAME') ?: 'blackbart_site',
            'user' => getenv('DB_USER') ?: 'root',
            'password' => getenv('DB_PASS') ?: '',
            'charset' => 'utf8mb4',
        ],
    ];
}
