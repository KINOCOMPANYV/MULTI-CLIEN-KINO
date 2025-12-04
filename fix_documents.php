<?php
// fix_documents.php
// ESTE SCRIPT FUERZA LA IMPORTACIÓN DE LA TABLA 'documents' (Nombres de PDF y Fechas)

require_once __DIR__ . '/config.php';

// Configuración para evitar tiempos de espera
ini_set('memory_limit', '1024M');
set_time_limit(0);

echo "<h1>📄 Reparación de Tabla de Documentos</h1><pre>";

try {
    // 1. CREAR LA TABLA DOCUMENTS MANUALMENTE
    // -----------------------------------------------------
    // Esto asegura que la tabla exista con las columnas correctas
    // Coincide con la estructura vista en tu archivo SQL: (id, name, date, path, codigos_extraidos)

    echo "1. Recreando estructura de la tabla 'documents'...\n";

    $sqlCreate = "CREATE TABLE IF NOT EXISTS `documents` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(255) NOT NULL,
      `date` date NOT NULL,
      `path` varchar(255) NOT NULL,
      `codigos_extraidos` text DEFAULT NULL,
      PRIMARY KEY (`id`),
      KEY `idx_date` (`date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $db->exec($sqlCreate);
    echo "✅ Tabla 'documents' lista.\n";

    // 2. EXTRAER LOS DATOS DEL SQL
    // -----------------------------------------------------
    $sqlFile = __DIR__ . '/if0_39064130_buscador (10).sql';

    if (!file_exists($sqlFile)) {
        throw new Exception("❌ No encuentro el archivo: $sqlFile");
    }

    echo "2. Buscando datos de documentos en el archivo SQL...\n";

    // Leemos el archivo línea por línea para no saturar la memoria
    $handle = fopen($sqlFile, "r");
    $found = false;
    $count = 0;

    if ($handle) {
        // Vaciamos la tabla para evitar duplicados antes de insertar
        $db->exec("TRUNCATE TABLE `documents`");

        while (($line = fgets($handle)) !== false) {
            $line = trim($line);

            // Buscamos líneas que empiecen con INSERT INTO `documents` o INSERT INTO documents
            if (stripos($line, 'INSERT INTO `documents`') === 0 || stripos($line, 'INSERT INTO documents') === 0) {
                try {
                    // Ejecutar la inserción
                    $db->exec($line);
                    $found = true;
                    $count++;
                    echo "   -> Bloque de datos insertado correctamente.\n";
                } catch (PDOException $e) {
                    echo "⚠️ Error insertando bloque: " . substr($e->getMessage(), 0, 100) . "...\n";
                }
            }
        }
        fclose($handle);
    }

    // 3. RESULTADO FINAL
    // -----------------------------------------------------
    $totalDocs = $db->query("SELECT COUNT(*) FROM documents")->fetchColumn();

    echo "\n📊 TOTAL DE DOCUMENTOS EN BASE DE DATOS: $totalDocs\n";

    if ($totalDocs > 0) {
        echo "🚀 ¡SOLUCIONADO! Los nombres y fechas ya deberían aparecer en el sistema.";
    } else {
        echo "❌ ERROR: No se encontraron instrucciones 'INSERT INTO documents' en el archivo SQL.\n";
        echo "   Por favor verifica que el archivo SQL tenga datos.";
    }

} catch (Exception $e) {
    echo "\n❌ ERROR CRÍTICO: " . $e->getMessage();
}
echo "</pre>";
?>