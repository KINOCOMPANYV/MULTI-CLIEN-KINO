<?php
// pdf-search.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/plain; charset=UTF-8'); // Cambiado a text/plain para llenar el textarea directo

// Recibimos parámetros
$code = $_GET['code'] ?? ''; // Mantener compatibilidad si se usa búsqueda simple
$useOCR = isset($_GET['use_ocr']) ? '--ocr' : '';
$strict = isset($_GET['strict_mode']) ? '--strict' : '';

// Construir comando
// NOTA: Asegúrate de que tu pdf_search.py acepte estos argumentos
$command = "python3 " . __DIR__ . "/pdf_search.py ";

if ($code !== '') {
    $command .= escapeshellarg($code) . " ";
}

// Agregar flags de reglas
if ($useOCR)
    $command .= "--ocr ";
if ($strict)
    $command .= "--strict ";

$command .= " 2>&1";

exec($command, $out, $ret);

if ($ret !== 0) {
    http_response_code(500);
    echo "Error ejecutando extracción:\n" . implode("\n", $out);
    exit;
}

// Devolver salida limpia
echo implode("\n", $out);
?>