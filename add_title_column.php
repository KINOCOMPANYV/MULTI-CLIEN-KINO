<?php
require 'config.php';

try {
    echo "<h2>Diagnóstico de Base de Datos</h2>";

    // 1. Mostrar estructura actual
    echo "<h3>Estructura actual de _control_clientes:</h3>";
    $stmt = $db->query("DESCRIBE _control_clientes");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "<pre>" . print_r($columns, true) . "</pre>";

    // 2. Intentar agregar la columna
    echo "<h3>Intentando agregar columna 'titulo_app'...</h3>";
    $db->exec("ALTER TABLE _control_clientes ADD COLUMN titulo_app VARCHAR(150) DEFAULT 'KINO COMPANY SAS V1'");
    echo "✅ Columna 'titulo_app' agregada correctamente.<br>";

} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "ℹ️ La columna 'titulo_app' ya existe.<br>";
    } else {
        echo "❌ Error SQL: " . $e->getMessage() . "<br>";
    }
}

echo "<hr><a href='client-generator.php'>Volver al Generador de Clientes</a>";
?>