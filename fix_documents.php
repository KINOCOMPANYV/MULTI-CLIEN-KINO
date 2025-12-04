<?php
// fix_documents.php
// ESTE SCRIPT REPARA ESPECÍFICAMENTE LA TABLA DE DOCUMENTOS (Nombres y Fechas)

require_once __DIR__ . '/config.php';

// Configuración para evitar tiempos de espera y errores de memoria
ini_set('memory_limit', '1024M');
set_time_limit(0);

echo "<h1>📄 Reparación de Tabla 'documents'</h1><pre>";

try {
    // 1. RECREAR LA ESTRUCTURA DE LA TABLA
    // -----------------------------------------------------
    echo "1. Verificando estructura de tabla 'documents'...\n";

    // Borramos la tabla para asegurarnos de que se cree limpia y con la estructura correcta
    $db->exec("DROP TABLE IF EXISTS `documents`");

    // Estructura basada en tu sistema (id, name, date, path, codigos_extraidos)
    $sqlCreate = "CREATE TABLE `documents` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(255) NOT NULL,
      `date` date NOT NULL,
      `path` varchar(255) NOT NULL,
      `codigos_extraidos` text DEFAULT NULL,
      PRIMARY KEY (`id`),
      KEY `idx_date` (`date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $db->exec($sqlCreate);
    echo "✅ Tabla 'documents' creada correctamente.\n";

    // 2. EXTRAER E INSERTAR DATOS DEL SQL
    // -----------------------------------------------------
    $sqlFile = __DIR__ . '/if0_39064130_buscador (10).sql';

    if (!file_exists($sqlFile)) {
        throw new Exception("❌ No encuentro el archivo: $sqlFile");
    }

    echo "2. Buscando datos en el archivo SQL...\n";

    $handle = fopen($sqlFile, "r");
    $count = 0;

    if ($handle) {
        while (($line = fgets($handle)) !== false) {
            $line = trim($line);

            // Buscamos específicamente las líneas que insertan en 'documents'
            // Tu archivo usa comillas invertidas `documents`
            if (stripos($line, 'INSERT INTO `documents`') === 0 || stripos($line, 'INSERT INTO documents') === 0) {
                try {
                    $db->exec($line);
                    $count++;
                    echo "   -> Bloque de datos insertado.\n";
                } catch (PDOException $e) {
                    echo "⚠️ Error en un bloque (puede ser ignorado si son duplicados): " . substr($e->getMessage(), 0, 100) . "...\n";
                }
            }
        }
        fclose($handle);
    }

    // 3. VERIFICACIÓN FINAL
    // -----------------------------------------------------
    $totalDocs = $db->query("SELECT COUNT(*) FROM documents")->fetchColumn();

    echo "\n📊 RESULTADO FINAL:\n";
    echo "   Total de documentos recuperados: " . number_format($totalDocs) . "\n";

    if ($totalDocs > 0) {
        echo "\n🚀 ¡SOLUCIONADO! Ahora Kino debería ver los nombres y fechas de los PDFs.";
    } else {
        echo "\n❌ ERROR: Siguen sin aparecer. Verifica que el archivo SQL tenga instrucciones 'INSERT INTO `documents`'.";
    }

} catch (Exception $e) {
    echo "\n❌ ERROR CRÍTICO: " . $e->getMessage();
}
echo "</pre>";
?>