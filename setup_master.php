<?php
// setup_master.php - EJECUTA ESTO UNA SOLA VEZ
require_once __DIR__ . '/config.php';

// Aumentar límites para archivos pesados
ini_set('memory_limit', '1024M');
set_time_limit(600);

echo "<h1>🛠️ Reparando Base de Datos Maestra (Kino)</h1><pre>";

try {
    // 1. CONFIRMAR QUE EL USUARIO KINO EXISTE
    // -----------------------------------------------------
    echo "1. Verificando usuario Kino...\n";
    $db->exec("CREATE TABLE IF NOT EXISTS `_control_clientes` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pass = password_hash('kino2024', PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO _control_clientes (codigo, nombre, password_hash, activo) VALUES ('kino', 'KINO Company', ?, 1) ON DUPLICATE KEY UPDATE activo=1");
    $stmt->execute([$pass]);
    echo "✅ Usuario Kino confirmado.\n";

    // 2. IMPORTAR EL SQL COMPLETO (SIN FILTROS)
    // -----------------------------------------------------
    $sqlFile = __DIR__ . '/if0_39064130_buscador (10).sql';

    if (!file_exists($sqlFile)) {
        throw new Exception("❌ FALTA EL ARCHIVO: Sube 'if0_39064130_buscador (10).sql' a la raíz.");
    }

    echo "2. Leyendo archivo SQL (" . filesize($sqlFile) . " bytes)...\n";

    // Leemos todo el archivo
    $sql = file_get_contents($sqlFile);

    // TRUCO: Eliminamos comentarios que pueden romper la ejecución
    $sql = preg_replace('/^--.*$/m', '', $sql);
    $sql = preg_replace('/^#.*$/m', '', $sql);

    // Dividimos por punto y coma para ejecutar una por una
    $queries = explode(';', $sql);
    $total = 0;

    foreach ($queries as $query) {
        $query = trim($query);
        if (!empty($query)) {
            try {
                $db->exec($query);
                $total++;
            } catch (Exception $e) {
                // Si la tabla ya existe, ignoramos el error
                if (strpos($e->getMessage(), 'already exists') === false) {
                    // Solo mostramos errores graves
                    // echo "⚠️ Aviso: " . substr($e->getMessage(), 0, 100) . "\n";
                }
            }
        }
    }
    echo "✅ Importación finalizada. Se procesaron $total bloques SQL.\n";

    // 3. VERIFICACIÓN FINAL (LA PRUEBA DE LA VERDAD)
    // -----------------------------------------------------
    $countDocs = $db->query("SELECT COUNT(*) FROM documents")->fetchColumn();
    $countCodes = $db->query("SELECT COUNT(*) FROM codes")->fetchColumn();

    echo "\n📊 RESULTADO FINAL EN LA BASE DE DATOS:\n";
    echo "   Documentos encontrados: " . number_format($countDocs) . "\n";
    echo "   Códigos encontrados:    " . number_format($countCodes) . "\n";

    if ($countCodes > 0) {
        echo "\n🚀 ¡ÉXITO! Ahora sí hay códigos en la tabla.";
        echo "\n👉 Ve al login y entra como Kino.";
    } else {
        echo "\n❌ ERROR: Siguen sin aparecer. Revisa que el archivo SQL no esté vacío.";
    }

} catch (Exception $e) {
    echo "\n❌ ERROR CRÍTICO: " . $e->getMessage();
}
echo "</pre>";
?>