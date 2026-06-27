<?php
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$method = $_SERVER['REQUEST_METHOD'];

try {
    kalamper_initialize_database();
    $pdo = kalamper_pdo();

    if ($method === 'GET') {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 9;
        $offset = ($page-1) * $limit;
        $total = (int)$pdo->query('SELECT COUNT(*) FROM kalamper_reviews WHERE is_published=1')->fetchColumn();
        $stmt = $pdo->prepare('SELECT id,name,rating,review,created_at FROM kalamper_reviews WHERE is_published=1 ORDER BY created_at DESC LIMIT ? OFFSET ?');
        $stmt->execute([$limit, $offset]);
        $reviews = $stmt->fetchAll();
        echo json_encode(['ok'=>true,'reviews'=>$reviews,'total'=>$total,'page'=>$page], JSON_UNESCAPED_UNICODE);
    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $name = trim($data['name'] ?? '');
        $email = trim($data['email'] ?? '');
        $rating = (int)($data['rating'] ?? 0);
        $review = trim($data['review'] ?? '');
        if (!$name || !$email || !filter_var($email, FILTER_VALIDATE_EMAIL) || $rating < 1 || $rating > 5 || strlen($review) < 10) {
            http_response_code(400);
            echo json_encode(['ok'=>false,'error'=>'Заполните все поля корректно']);
            exit;
        }
        if (strlen($name)>120 || strlen($review)>2000) {
            http_response_code(400);
            echo json_encode(['ok'=>false,'error'=>'Текст слишком длинный']);
            exit;
        }
        $stmt = $pdo->prepare('INSERT INTO kalamper_reviews (name,email,rating,review,is_published) VALUES (?,?,?,?,1)');
        $stmt->execute([$name, $email, $rating, $review]);
        echo json_encode(['ok'=>true,'message'=>'Отзыв опубликован. Спасибо!']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'Ошибка сервера']);
}
