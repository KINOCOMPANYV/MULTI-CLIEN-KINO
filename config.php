<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Intentar obtener variables de entorno (Railway) o usar valores por defecto (Local)
// Railway suele usar MYSQLHOST, MYSQLUSER, etc. o DB_HOST según tu configuración.
$DB_HOST = getenv('MYSQLHOST') ?: getenv('DB_HOST') ?: 'localhost';
$DB_USER = getenv('MYSQLUSER') ?: getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('MYSQLPASSWORD') ?: getenv('DB_PASS') ?: ''; // Tu contraseña local aquí si la tienes
$DB_NAME = getenv('MYSQLDATABASE') ?: getenv('DB_NAME') ?: 'kino_db'; // Asegúrate que este sea el nombre correcto
$DB_PORT = getenv('MYSQLPORT') ?: getenv('DB_PORT') ?: 3306;

// Debug: Log which host is being used
error_log("🔍 [CONFIG] Intentando conectar a: {$DB_HOST}:{$DB_PORT} / DB: {$DB_NAME}");

try {
    $dsn = "mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4";
    $db = new PDO($dsn, $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // Opcional: Establecer charset
    $db->exec("SET NAMES utf8mb4");

} catch (PDOException $e) {
    // Escribir el error en el log de errores del sistema (visible en Railway Logs)
    error_log("❌ [CONFIG] Error de conexión DB: " . $e->getMessage());

    // Terminar ejecución con mensaje genérico (o detallado si prefieres depurar)
    die("❌ La conexión a la base de datos falló. Revisa los logs de Railway.");
}
?>