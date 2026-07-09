<?php
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

try {
    kalamper_initialize_database();
    $pdo = kalamper_pdo();

    // Global contact settings
    $globalPhone = '';
    $globalEmail = '';
    try {
        $s = $pdo->query("SELECT `key`,`value` FROM kalamper_settings WHERE `key` IN ('contact_phone','contact_email')");
        if ($s) {
            foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if ($row['key'] === 'contact_phone') $globalPhone = (string)$row['value'];
                if ($row['key'] === 'contact_email') $globalEmail = (string)$row['value'];
            }
        }
    } catch (Throwable $e) {}

    $city = trim($_GET['city'] ?? '');

    if ($city !== '') {
        $stmt = $pdo->prepare('SELECT id,city,name,address,hours,website,lat,lng FROM kalamper_dealers WHERE is_active=1 AND city=? ORDER BY sort_order,name');
        $stmt->execute([$city]);
        $dealers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Inject global phone into every dealer card
        foreach ($dealers as &$d) {
            $d['phone'] = $globalPhone;
        }
        unset($d);
        echo json_encode(['ok'=>true,'dealers'=>$dealers], JSON_UNESCAPED_UNICODE);
    } else {
        $stmt = $pdo->query('SELECT DISTINCT city FROM kalamper_dealers WHERE is_active=1 ORDER BY sort_order,city');
        $cities = array_column($stmt->fetchAll(), 'city');
        echo json_encode(['ok'=>true,'cities'=>$cities,'phone'=>$globalPhone,'email'=>$globalEmail], JSON_UNESCAPED_UNICODE);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'DB error']);
}
