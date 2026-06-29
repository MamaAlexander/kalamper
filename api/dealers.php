<?php
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

try {
    kalamper_initialize_database();
    $pdo = kalamper_pdo();

    $city = trim($_GET['city'] ?? '');

    if ($city !== '') {
        $stmt = $pdo->prepare('SELECT id,city,name,address,phone,hours,website,lat,lng FROM kalamper_dealers WHERE is_active=1 AND city=? ORDER BY sort_order,name');
        $stmt->execute([$city]);
        $dealers = $stmt->fetchAll();
        echo json_encode(['ok'=>true,'dealers'=>$dealers], JSON_UNESCAPED_UNICODE);
    } else {
        $stmt = $pdo->query('SELECT DISTINCT city FROM kalamper_dealers WHERE is_active=1 ORDER BY sort_order,city');
        $cities = array_column($stmt->fetchAll(), 'city');
        echo json_encode(['ok'=>true,'cities'=>$cities], JSON_UNESCAPED_UNICODE);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'DB error']);
}
