<?php
/**
 * Simple IP-based rate limiter using the database.
 * Call kalamper_rate_limit('endpoint', max_requests, window_seconds) at the top of an API handler.
 * Returns true if allowed, exits with 429 JSON if blocked.
 */

function kalamper_rate_limit(string $endpoint, int $max, int $window): void {
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP']
        ?? $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['REMOTE_ADDR']
        ?? '0.0.0.0';
    // Take only first IP if comma-separated
    $ip = trim(explode(',', $ip)[0]);
    // Validate IP
    if (!filter_var($ip, FILTER_VALIDATE_IP)) $ip = '0.0.0.0';

    try {
        $pdo = kalamper_pdo();

        // Ensure table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS kalamper_rate_limits (
            ip VARCHAR(45) NOT NULL,
            endpoint VARCHAR(50) NOT NULL,
            hits INT UNSIGNED NOT NULL DEFAULT 1,
            window_start DATETIME NOT NULL,
            PRIMARY KEY (ip, endpoint)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $now = date('Y-m-d H:i:s');
        $cutoff = date('Y-m-d H:i:s', time() - $window);

        // Reset stale window or increment
        $check = $pdo->prepare(
            'SELECT hits, window_start FROM kalamper_rate_limits WHERE ip=? AND endpoint=?'
        );
        $check->execute([$ip, $endpoint]);
        $row = $check->fetch();

        if (!$row || $row['window_start'] < $cutoff) {
            // New window
            $pdo->prepare(
                'INSERT INTO kalamper_rate_limits (ip, endpoint, hits, window_start)
                 VALUES (?, ?, 1, ?)
                 ON DUPLICATE KEY UPDATE hits=1, window_start=?'
            )->execute([$ip, $endpoint, $now, $now]);
        } else {
            if ((int)$row['hits'] >= $max) {
                http_response_code(429);
                header('Content-Type: application/json; charset=utf-8');
                header('Retry-After: ' . $window);
                echo json_encode(['ok' => false, 'error' => 'Слишком много запросов. Попробуйте позже.']);
                exit;
            }
            $pdo->prepare(
                'UPDATE kalamper_rate_limits SET hits=hits+1 WHERE ip=? AND endpoint=?'
            )->execute([$ip, $endpoint]);
        }
    } catch (Throwable $e) {
        // On DB error, allow through (fail open) to not break the site
    }
}
