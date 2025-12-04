<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$DB_HOST = getenv('MYSQLHOST');
$DB_NAME = getenv('MYSQLDATABASE');
$DB_USER = getenv('MYSQLUSER');
$DB_PASS = getenv('MYSQLPASSWORD');
$DB_PORT = getenv('MYSQLPORT') ?: '3306';

echo "🔍 Intentando conectar con <b>{$DB_NAME}</b> en host <b>{$DB_HOST}</b><br>";

try {
    $dsn = "mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4";
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    echo "✅ Conexión exitosa con la base de datos: <b>{$DB_NAME}</b>";
} catch (PDOException $e) {
    echo "❌ Error de conexión: " . $e->getMessage();
}
?>