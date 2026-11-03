<?php
declare(strict_types=1);
require_once __DIR__ . '/../vendor/autoload.php';
header('Content-Type: application/json');
try {
    \App\Database::connection()->selectOne('SELECT 1');
    echo json_encode(['status' => 'ok']);
} catch (\Throwable $e) {
    http_response_code(503);
    echo json_encode(['status' => 'error']);
}
