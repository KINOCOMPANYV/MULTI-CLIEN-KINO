<?php
// setup_master.php
// ESTE SCRIPT REPARA LA BASE DE DATOS VACÍA Y CREA AL CLIENTE MAESTRO

require_once __DIR__ . '/config.php';

// Aumentar límites para archivos grandes
ini_set('memory_limit', '512M');
set_time_limit(300);

echo "<h1>🛠️ Reparación de Base de Datos Maestra (Kino)</h1>";
echo "<pre>";

try {
    // 1. IMPORTAR ESTRUCTURA Y DATOS SQL
    // ---------------------------------------------------------
    $sqlFile = __DIR__ . '/if0_39064130_buscador (10).sql'; // Asegúrate que el nombre sea EXACTO

    if (!file_exists($sqlFile)) {
        throw new Exception("❌ No encuentro el archivo SQL: $sqlFile<br>Súbelo a la carpeta raíz.");
    }

    echo "📂 Leyendo archivo SQL... ";
    $sql = file_get_contents($sqlFile);

    // Limpieza básica de comentarios para evitar errores
    $lines = explode("\n", $sql);
    $cleanSql = "";
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line && substr($line, 0, 2) !== '--' && substr($line, 0, 1) !== '#') {
            $cleanSql .= $line . "\n";
        }
    }

    // Dividir por punto y coma para ejecutar sentencia por sentencia
    $statements = explode(";", $cleanSql);
    $count = 0;

    foreach ($statements as $stmt) {
        $stmt = trim($stmt);
        if (!empty($stmt)) {
            try {
                $db->exec($stmt);
                $count++;
            } catch (PDOException $e) {
                // Ignorar errores de "Tabla ya existe" para no detener el proceso
                if (strpos($e->getMessage(), 'already exists') === false) {
                    echo "⚠️ Error en sentencia SQL (ignorando): " . substr($stmt, 0, 50) . "... \n";
                }
            }
        }
    }
    echo "✅ Importación completada. Se ejecutaron $count sentencias.\n";


    // 2. ASEGURAR TABLA DE CONTROL DE CLIENTES
    // ---------------------------------------------------------
    echo "\n🔧 Verificando tabla de control de clientes...\n";
    $sqlControl = "CREATE TABLE IF NOT EXISTS `_control_clientes` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `codigo` varchar(50) NOT NULL,
      `nombre` varchar(100) NOT NULL,
      `password_hash` varchar(255) NOT NULL,
      `color_primario` varchar(20) DEFAULT '#2563eb',
      `color_secundario` varchar(20) DEFAULT '#F87171',
      `activo` tinyint(1) DEFAULT 1,
      `fecha_creacion` timestamp DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      UNIQUE KEY `codigo` (`codigo`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $db->exec($sqlControl);
    echo "✅ Tabla _control_clientes verificada.\n";


    // 3. CREAR/ACTUALIZAR USUARIO KINO
    // ---------------------------------------------------------
    echo "\n👤 Configurando usuario maestro 'kino'...\n";
    $codigo = 'kino';
    $password = 'kino2024';
    $hash = password_hash($password, PASSWORD_DEFAULT);

    // Insertar o actualizar si ya existe
    $stmt = $db->prepare("INSERT INTO _control_clientes (codigo, nombre, password_hash, activo) 
                          VALUES (?, 'KINO Company', ?, 1) 
                          ON DUPLICATE KEY UPDATE password_hash = ?, activo = 1");
    $stmt->execute([$codigo, $hash, $hash]);

    echo "✅ Usuario 'kino' configurado exitosamente.\n";


    // 4. VERIFICACIÓN FINAL
    // ---------------------------------------------------------
    $docs = $db->query("SELECT COUNT(*) FROM documents")->fetchColumn();
    $codes = $db->query("SELECT COUNT(*) FROM codes")->fetchColumn();

    echo "\n📊 ESTADO FINAL DE LA BASE DE DATOS:\n";
    echo "   - Documentos (Tabla Maestra): $docs\n";
    echo "   - Códigos (Tabla Maestra): $codes\n";

    if ($docs == 0) {
        echo "\n⚠️ ADVERTENCIA: Las tablas se crearon pero están vacías. \nRevisa que el archivo SQL tenga instrucciones 'INSERT INTO'.";
    } else {
        echo "\n🚀 ¡TODO LISTO! Ahora puedes hacer login como Kino.\n";
    }

} catch (Exception $e) {
    echo "\n❌ ERROR CRÍTICO: " . $e->getMessage() . "\n";
}
echo "</pre>";
?>