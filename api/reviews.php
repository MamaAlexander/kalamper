<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/rate_limit.php';

header('Content-Type: application/json; charset=utf-8');
// Restrict CORS to own domain only
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (preg_match('#^https?://(www\.)?kalamper\.ru$#i', $origin)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    // Localhost for development
    header('Access-Control-Allow-Origin: http://localhost');
}

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
        // Rate limit: max 3 review submissions per IP per hour
        kalamper_rate_limit('reviews_post', 3, 3600);

        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        // Honeypot check: 'website' field must be empty (bots fill it)
        if (!empty($data['website'])) {
            // Silently accept to not reveal honeypot
            echo json_encode(['ok'=>true,'message'=>'Отзыв опубликован. Спасибо!']);
            exit;
        }

        // Timing check: form submitted too fast = bot (under 3 seconds)
        $submitted_at = (int)($data['_t'] ?? 0);
        if ($submitted_at && (time() - $submitted_at) < 3) {
            echo json_encode(['ok'=>true,'message'=>'Отзыв опубликован. Спасибо!']);
            exit;
        }

        $name   = trim($data['name'] ?? '');
        $email  = trim($data['email'] ?? '');
        $rating = (int)($data['rating'] ?? 0);
        $review = trim($data['review'] ?? '');

        if (!$name || !$email || !filter_var($email, FILTER_VALIDATE_EMAIL) || $rating < 1 || $rating > 5 || strlen($review) < 10) {
            http_response_code(400);
            echo json_encode(['ok'=>false,'error'=>'Заполните все поля корректно']);
            exit;
        }
        if (strlen($name) > 120 || strlen($review) > 2000) {
            http_response_code(400);
            echo json_encode(['ok'=>false,'error'=>'Текст слишком длинный']);
            exit;
        }
        $stmt = $pdo->prepare('INSERT INTO kalamper_reviews (name,email,rating,review,is_published) VALUES (?,?,?,?,0)');
        $stmt->execute([$name, $email, $rating, $review]);
        echo json_encode(['ok'=>true,'message'=>'Спасибо! Отзыв появится после проверки модератором.']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'Ошибка сервера']);
}
