<?php
// fix_documents.php
// Script de emergencia para importar SOLAMENTE la tabla 'documents'

require_once __DIR__ . '/config.php';

// Ajustes para evitar que el script se detenga
ini_set('memory_limit', '1024M');
set_time_limit(0);

echo "<h1>📄 Reparando Tabla de Documentos</h1><pre>";

try {
    // 1. RECREAR LA ESTRUCTURA DE LA TABLA DOCUMENTS
    // -----------------------------------------------------
    echo "1. Preparando la tabla 'documents'...\n";

    // Eliminamos la tabla para recrearla limpia y evitar errores de estructura
    $db->exec("DROP TABLE IF EXISTS `documents`");

    // Creamos la tabla con la estructura exacta que espera tu sistema
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
    echo "✅ Tabla 'documents' creada y lista para recibir datos.\n";

    // 2. BUSCAR E INSERTAR DATOS DEL ARCHIVO SQL
    // -----------------------------------------------------
    $sqlFile = __DIR__ . '/if0_39064130_buscador (10).sql';

    if (!file_exists($sqlFile)) {
        throw new Exception("❌ No encuentro el archivo: $sqlFile");
    }

    echo "2. Escaneando archivo SQL en busca de documentos...\n";

    // Abrimos el archivo en modo lectura
    $handle = fopen($sqlFile, "r");
    $count = 0;

    if ($handle) {
        $buffer = '';
        while (($line = fgets($handle)) !== false) {
            $line = trim($line);

            // Buscamos líneas que empiecen con la instrucción de inserción para documents
            // Probamos con comillas y sin comillas por seguridad
            if (stripos($line, 'INSERT INTO `documents`') === 0 || stripos($line, 'INSERT INTO documents') === 0) {
                try {
                    // Ejecutamos la línea encontrada
                    $db->exec($line);
                    $count++;
                    echo "   -> [Insertado] Bloque de datos encontrado.\n";
                } catch (PDOException $e) {
                    echo "⚠️ Advertencia en bloque (posible duplicado): " . substr($e->getMessage(), 0, 50) . "...\n";
                }
            }
        }
        fclose($handle);
    }

    // 3. VERIFICACIÓN FINAL
    // -----------------------------------------------------
    $totalDocs = $db->query("SELECT COUNT(*) FROM documents")->fetchColumn();

    echo "\n------------------------------------------------\n";
    echo "📊 REPORTE FINAL:\n";
    echo "   Documentos encontrados e insertados: " . number_format($totalDocs) . "\n";
    echo "------------------------------------------------\n";

    if ($totalDocs > 0) {
        echo "\n🚀 ¡ÉXITO! Ahora Kino podrá ver los nombres y fechas.";
        echo "\n👉 Puedes borrar este archivo 'fix_documents.php' cuando verifiques que funciona.";
    } else {
        echo "\n❌ ALERTA: Siguen apareciendo 0 documentos.";
        echo "\n   Revisa que el archivo '$sqlFile' tenga líneas que empiecen con 'INSERT INTO `documents`'.";
    }

} catch (Exception $e) {
    echo "\n❌ ERROR CRÍTICO: " . $e->getMessage();
}
echo "</pre>";
?>