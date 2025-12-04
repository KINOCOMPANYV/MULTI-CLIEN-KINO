<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$DB_HOST = getenv('MYSQLHOST');
$DB_NAME = getenv('MYSQLDATABASE');
$DB_USER = getenv('MYSQLUSER');
$DB_PASS = getenv('MYSQLPASSWORD');
$DB_PORT = getenv('MYSQLPORT') ?: '3306';

// Validar que las variables de entorno estén configuradas
if (!$DB_HOST || !$DB_NAME || !$DB_USER || !$DB_PASS) {
    die("❌ Error: Variables de entorno de MySQL no configuradas. Verifica Railway.");
}

// Debug: Log which host is being used
error_log("🔍 [CONFIG] Intentando conectar a: {$DB_HOST}:{$DB_PORT} / DB: {$DB_NAME}");

try {
    $dsn = "mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4";
    $db = new PDO($dsn, $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    error_log("❌ [CONFIG] Error de conexión DB: " . $e->getMessage());
    die("❌ Error de conexión a la base de datos.");
}
?>