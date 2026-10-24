<?php
declare(strict_types=1);

$host = getenv('DB_HOST') ?: 'mysql';
$db   = getenv('DB_NAME') ?: 'attendance_db';
$user = getenv('DB_USER') ?: 'attendance_user';
$pass = getenv('DB_PASSWORD') ?: 'attendance_pass';

$dsn = "mysql:host={$host};dbname={$db};charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    http_response_code(500);
    exit('Khong ket noi duoc database. Hay kiem tra container MySQL va file .env.');
}
