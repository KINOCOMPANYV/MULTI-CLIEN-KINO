<?php
// parche_db.php
require 'config.php';

echo "<h1>🛠️ Reparando Base de Datos...</h1>";

try {
    // 1. Intentar agregar titulo_app
    try {
        $db->exec("ALTER TABLE _control_clientes ADD COLUMN titulo_app VARCHAR(150) DEFAULT 'KINO COMPANY SAS V1'");
        echo "<p style='color:green'>✅ Columna <b>titulo_app</b> agregada correctamente.</p>";
    } catch (PDOException $e) {
        // Ignorar si ya existe (código de error 42S21 o mensaje "Duplicate column")
        if (strpos($e->getMessage(), 'Duplicate column') !== false || $e->getCode() == '42S21') {
            echo "<p style='color:blue'>ℹ️ La columna <b>titulo_app</b> ya existía.</p>";
        } else {
            echo "<p style='color:red'>⚠️ Alerta: " . $e->getMessage() . "</p>";
        }
    }

    // 2. Intentar agregar clave_borrado (La que te está fallando)
    try {
        $db->exec("ALTER TABLE _control_clientes ADD COLUMN clave_borrado VARCHAR(50) DEFAULT '0000'");
        echo "<p style='color:green'>✅ Columna <b>clave_borrado</b> agregada correctamente.</p>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false || $e->getCode() == '42S21') {
            echo "<p style='color:blue'>ℹ️ La columna <b>clave_borrado</b> ya existía.</p>";
        } else {
            echo "<p style='color:red'>⚠️ Alerta: " . $e->getMessage() . "</p>";
        }
    }

    echo "<h2>✨ ¡Listo! Vuelve a intentar crear/editar el cliente.</h2>";
    echo "<a href='client-generator.php'>Volver al Generador</a>";

} catch (Exception $e) {
    die("❌ Error Fatal de Conexión: " . $e->getMessage());
}
?>