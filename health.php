<?php
header('Content-Type: application/json');
try {
    require __DIR__ . '/config/db.php';
    echo json_encode(['status' => 'ok', 'service' => 'JIGPOLY Polytechnic GPS Attendance']);
} catch (Throwable $e) {
    http_response_code(503);
    echo json_encode(['status' => 'error']);
}
