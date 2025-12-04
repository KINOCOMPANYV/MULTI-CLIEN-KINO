<?php
/**
 * Script para importar datos de KINO desde el SQL de respaldo
 * Ejecutar manualmente: php import_kino_data.php
 * O acceder desde el navegador: /import_kino_data.php
 */

require_once __DIR__ . '/config.php';

echo "<pre>\n";
echo "🚀 Importando datos para cliente KINO...\n\n";

try {
    // 1. Verificar que el cliente KINO existe
    $stmt = $db->prepare('SELECT id FROM _control_clientes WHERE codigo = ?');
    $stmt->execute(['kino']);
    if (!$stmt->fetch()) {
        // Crear cliente KINO si no existe
        echo "📝 Creando cliente KINO...\n";
        $hash = password_hash('kino2024', PASSWORD_DEFAULT);
        $db->prepare('INSERT INTO _control_clientes (codigo, nombre, password_hash, activo) VALUES (?, ?, ?, 1)')
            ->execute(['kino', 'KINO Company', $hash]);
    }

    // 2. Crear tablas si no existen
    echo "📦 Verificando/creando tablas kino_documents y kino_codes...\n";

    $db->exec("CREATE TABLE IF NOT EXISTS `kino_documents` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        date DATE NOT NULL,
        path VARCHAR(255) NOT NULL,
        codigos_extraidos TEXT DEFAULT NULL,
        INDEX idx_date (date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS `kino_codes` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        document_id INT NOT NULL,
        code VARCHAR(100) NOT NULL,
        INDEX idx_code (code),
        FOREIGN KEY (document_id) REFERENCES `kino_documents` (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 3. Verificar si ya hay datos
    $count = $db->query("SELECT COUNT(*) FROM kino_documents")->fetchColumn();
    if ($count > 0) {
        echo "⚠️ Ya existen {$count} documentos en kino_documents.\n";
        echo "¿Desea continuar? Los datos existentes serán reemplazados.\n";
        echo "Para forzar la reimportación, agregue ?force=1 a la URL.\n\n";

        if (!isset($_GET['force']) && !isset($argv[1])) {
            echo "❌ Importación cancelada. Use ?force=1 para continuar.\n";
            exit;
        }

        echo "🗑️ Eliminando datos existentes...\n";
        $db->exec("DELETE FROM kino_codes");
        $db->exec("DELETE FROM kino_documents");
    }

    // 4. Leer el archivo SQL
    $sqlFile = __DIR__ . '/if0_39064130_buscador (10).sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("No se encuentra el archivo SQL: $sqlFile");
    }

    $content = file_get_contents($sqlFile);
    echo "📄 Archivo SQL leído (" . round(strlen($content) / 1024) . " KB)\n\n";

    // 5. Extraer e insertar DOCUMENTS
    echo "📚 Importando documents...\n";

    // Buscar el bloque INSERT INTO documents
    if (preg_match('/INSERT INTO `documents`.*?VALUES\s*(.*?);/s', $content, $matches)) {
        $valuesBlock = $matches[1];

        // Parsear cada fila: (id, 'name', 'date', 'path', NULL|'value')
        preg_match_all('/\((\d+),\s*\'([^\']*)\',\s*\'([^\']*)\',\s*\'([^\']*)\',\s*(NULL|\'[^\']*\')\)/', $valuesBlock, $rows, PREG_SET_ORDER);

        $insertDoc = $db->prepare("INSERT INTO kino_documents (id, name, date, path, codigos_extraidos) VALUES (?, ?, ?, ?, ?)");
        $docCount = 0;

        foreach ($rows as $row) {
            $id = $row[1];
            $name = $row[2];
            $date = $row[3];
            // Cambiar path para que apunte a kino/
            $path = 'kino/' . $row[4];
            $codigos = $row[5] === 'NULL' ? null : trim($row[5], "'");

            $insertDoc->execute([$id, $name, $date, $path, $codigos]);
            $docCount++;
        }

        echo "   ✅ {$docCount} documentos importados\n";
    } else {
        echo "   ⚠️ No se encontró INSERT INTO documents\n";
    }

    // 6. Extraer e insertar CODES
    echo "🔑 Importando codes...\n";

    // Buscar todos los bloques INSERT INTO codes (pueden ser múltiples)
    if (preg_match_all('/INSERT INTO `codes`.*?VALUES\s*(.*?);/s', $content, $codeBlocks)) {
        $insertCode = $db->prepare("INSERT INTO kino_codes (id, document_id, code) VALUES (?, ?, ?)");
        $codeCount = 0;

        foreach ($codeBlocks[1] as $valuesBlock) {
            preg_match_all('/\((\d+),\s*(\d+),\s*\'([^\']*)\'\)/', $valuesBlock, $rows, PREG_SET_ORDER);

            foreach ($rows as $row) {
                try {
                    $insertCode->execute([$row[1], $row[2], $row[3]]);
                    $codeCount++;
                } catch (PDOException $e) {
                    // Ignorar errores de FK (documentos que no existen)
                    if (strpos($e->getMessage(), 'foreign key') === false) {
                        throw $e;
                    }
                }
            }
        }

        echo "   ✅ {$codeCount} códigos importados\n";
    } else {
        echo "   ⚠️ No se encontró INSERT INTO codes\n";
    }

    // 7. Verificar uploads
    echo "\n📁 Verificando carpeta uploads/kino...\n";
    $uploadsDir = __DIR__ . '/uploads/kino';
    if (!is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0777, true);
        echo "   📂 Carpeta creada: uploads/kino\n";
    }

    // Verificar archivos existentes en uploads raíz
    $rootUploads = __DIR__ . '/uploads';
    $pdfsMoved = 0;
    if (is_dir($rootUploads)) {
        foreach (scandir($rootUploads) as $file) {
            if ($file === '.' || $file === '..' || is_dir($rootUploads . '/' . $file))
                continue;
            if (pathinfo($file, PATHINFO_EXTENSION) === 'pdf') {
                $src = $rootUploads . '/' . $file;
                $dst = $uploadsDir . '/' . $file;
                if (!file_exists($dst)) {
                    copy($src, $dst);
                    $pdfsMoved++;
                }
            }
        }
    }
    if ($pdfsMoved > 0) {
        echo "   📋 {$pdfsMoved} PDFs copiados a uploads/kino\n";
    }

    // 8. Resumen final
    $docsFinal = $db->query("SELECT COUNT(*) FROM kino_documents")->fetchColumn();
    $codesFinal = $db->query("SELECT COUNT(*) FROM kino_codes")->fetchColumn();

    echo "\n" . str_repeat("=", 50) . "\n";
    echo "✅ IMPORTACIÓN COMPLETADA\n";
    echo str_repeat("=", 50) . "\n";
    echo "📊 Documentos: {$docsFinal}\n";
    echo "🔑 Códigos: {$codesFinal}\n";
    echo "🔐 Cliente: kino / kino2024\n";
    echo "🌐 Acceso: /login.php → Seleccionar KINO\n";
    echo str_repeat("=", 50) . "\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "</pre>";
?>