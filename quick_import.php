<?php
// quick_import.php
// SCRIPT RÁPIDO PARA IMPORTAR DATOS - EJECUTAR DESDE RAILWAY

require_once __DIR__ . '/config.php';

ini_set('memory_limit', '1024M');
set_time_limit(0);

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Importación Rápida</title>";
echo "<style>body{font-family:Arial;max-width:800px;margin:50px auto;padding:20px;background:#f5f5f5;}";
echo "h1{color:#333;border-bottom:3px solid #4CAF50;padding-bottom:10px;}";
echo ".success{background:#d4edda;border:1px solid #c3e6cb;color:#155724;padding:15px;margin:10px 0;border-radius:5px;}";
echo ".error{background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;padding:15px;margin:10px 0;border-radius:5px;}";
echo ".info{background:#d1ecf1;border:1px solid #bee5eb;color:#0c5460;padding:15px;margin:10px 0;border-radius:5px;}";
echo "pre{background:#f8f9fa;padding:15px;border-radius:5px;overflow-x:auto;}</style></head><body>";

echo "<h1>🚀 Importación Rápida de Datos Kino</h1>";

try {
    $sqlFile = __DIR__ . '/if0_39064130_buscador (10).sql';

    if (!file_exists($sqlFile)) {
        throw new Exception("❌ Archivo SQL no encontrado: $sqlFile");
    }

    echo "<div class='info'>📁 Archivo SQL encontrado: " . basename($sqlFile) . "</div>";
    echo "<div class='info'>📊 Tamaño: " . number_format(filesize($sqlFile) / 1024, 2) . " KB</div>";

    echo "<h2>Paso 1: Desactivando restricciones</h2>";
    $db->exec("SET FOREIGN_KEY_CHECKS = 0");
    $db->exec("SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO'");
    echo "<div class='success'>✅ Restricciones desactivadas</div>";

    echo "<h2>Paso 2: Limpiando tablas existentes</h2>";
    $db->exec("DROP TABLE IF EXISTS `codes`");
    $db->exec("DROP TABLE IF EXISTS `documents`");
    echo "<div class='success'>✅ Tablas eliminadas</div>";

    echo "<h2>Paso 3: Importando desde SQL</h2>";
    echo "<pre>";

    // Leer y ejecutar el archivo SQL
    $sql = file_get_contents($sqlFile);

    // Eliminar comentarios
    $sql = preg_replace('/^--.*$/m', '', $sql);
    $sql = preg_replace('/^#.*$/m', '', $sql);

    // Dividir en consultas
    $queries = explode(';', $sql);
    $executed = 0;
    $errors = 0;

    foreach ($queries as $query) {
        $query = trim($query);
        if (!empty($query) && strlen($query) > 10) {
            try {
                $db->exec($query);
                $executed++;

                // Mostrar progreso cada 10 consultas
                if ($executed % 10 == 0) {
                    echo "✓ $executed consultas ejecutadas...\n";
                    flush();
                }
            } catch (PDOException $e) {
                // Ignorar errores de "tabla ya existe"
                if (strpos($e->getMessage(), 'already exists') === false) {
                    $errors++;
                    if ($errors < 5) { // Mostrar solo los primeros 5 errores
                        echo "⚠️ Error: " . substr($e->getMessage(), 0, 100) . "...\n";
                    }
                }
            }
        }
    }

    echo "\n✅ Total de consultas ejecutadas: $executed\n";
    if ($errors > 0) {
        echo "⚠️ Errores encontrados: $errors (pueden ser normales)\n";
    }
    echo "</pre>";

    echo "<h2>Paso 4: Reactivando restricciones</h2>";
    $db->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "<div class='success'>✅ Restricciones reactivadas</div>";

    echo "<h2>Paso 5: Verificación Final</h2>";

    $countDocs = $db->query("SELECT COUNT(*) FROM documents")->fetchColumn();
    $countCodes = $db->query("SELECT COUNT(*) FROM codes")->fetchColumn();

    echo "<div class='info'>";
    echo "<strong>📄 Documentos importados:</strong> " . number_format($countDocs) . "<br>";
    echo "<strong>🔍 Códigos importados:</strong> " . number_format($countCodes) . "<br>";
    echo "</div>";

    if ($countDocs > 0 && $countCodes > 0) {
        echo "<div class='success'>";
        echo "<h3>🎉 ¡IMPORTACIÓN EXITOSA!</h3>";
        echo "<p>Los datos han sido importados correctamente.</p>";
        echo "<p><strong>Próximos pasos:</strong></p>";
        echo "<ul>";
        echo "<li>Verifica que los nombres y fechas aparezcan en la aplicación</li>";
        echo "<li>Prueba la búsqueda de códigos</li>";
        echo "<li>Puedes eliminar este archivo (quick_import.php) si todo funciona</li>";
        echo "</ul>";
        echo "</div>";
    } else {
        echo "<div class='error'>";
        echo "<h3>⚠️ Advertencia</h3>";
        echo "<p>La importación se completó pero las tablas siguen vacías.</p>";
        echo "<p>Verifica que el archivo SQL contenga datos válidos.</p>";
        echo "</div>";
    }

    // Mostrar muestra de datos
    if ($countDocs > 0) {
        echo "<h2>Muestra de Documentos Importados</h2>";
        $sample = $db->query("SELECT id, name, date FROM documents LIMIT 5")->fetchAll();
        echo "<table border='1' cellpadding='10' style='width:100%;background:white;border-collapse:collapse;'>";
        echo "<tr style='background:#4CAF50;color:white;'><th>ID</th><th>Nombre</th><th>Fecha</th></tr>";
        foreach ($sample as $row) {
            echo "<tr><td>{$row['id']}</td><td>{$row['name']}</td><td>{$row['date']}</td></tr>";
        }
        echo "</table>";
    }

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h3>❌ ERROR CRÍTICO</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}

echo "<hr><p style='text-align:center;color:#666;'><small>quick_import.php | Kino System</small></p>";
echo "</body></html>";
?>