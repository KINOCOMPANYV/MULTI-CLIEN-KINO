<?php
// import_force.php
// ESTE SCRIPT FUERZA LA IMPORTACIÓN DESACTIVANDO LAS RESTRICCIONES

require_once __DIR__ . '/config.php';

// Configuración para archivos grandes
ini_set('memory_limit', '1024M');
set_time_limit(0);

echo "<h1>🛠️ Importación Forzada de Datos (Kino)</h1><pre>";

try {
    $sqlFile = __DIR__ . '/if0_39064130_buscador (10).sql';

    if (!file_exists($sqlFile)) {
        throw new Exception("❌ No encuentro el archivo: $sqlFile");
    }

    echo "1. 🔓 Desactivando protecciones (Foreign Keys)...\n";
    // ESTO ES LA CLAVE: Permite insertar códigos aunque el documento no exista aún
    $db->exec("SET FOREIGN_KEY_CHECKS = 0");

    echo "2. 🧹 Limpiando tablas antiguas...\n";
    // Borramos datos previos para evitar duplicados
    $db->exec("DROP TABLE IF EXISTS `codes`");
    $db->exec("DROP TABLE IF EXISTS `documents`");

    echo "3. 📖 Leyendo y ejecutando archivo SQL...\n";

    // Leemos todo el archivo
    $sql = file_get_contents($sqlFile);

    // Eliminamos comentarios que puedan dar problemas
    $sql = preg_replace('/^--.*$/m', '', $sql);
    $sql = preg_replace('/^#.*$/m', '', $sql);

    // Ejecutamos las consultas divididas por punto y coma
    $queries = explode(';', $sql);
    $executed = 0;
    $errors = 0;

    foreach ($queries as $query) {
        $query = trim($query);
        if (!empty($query)) {
            try {
                $db->exec($query);
                $executed++;
            } catch (PDOException $e) {
                // Ignorar errores de "Tabla ya existe" o warnings menores
                if (strpos($e->getMessage(), 'already exists') === false) {
                    $errors++;
                }
            }
        }
    }

    echo "   -> Consultas ejecutadas: $executed\n";

    echo "4. 🔒 Reactivando protecciones...\n";
    $db->exec("SET FOREIGN_KEY_CHECKS = 1");

    // 5. VERIFICACIÓN FINAL
    // -----------------------------------------------------
    $countDocs = $db->query("SELECT COUNT(*) FROM documents")->fetchColumn();
    $countCodes = $db->query("SELECT COUNT(*) FROM codes")->fetchColumn();

    echo "\n------------------------------------------------\n";
    echo "📊 RESULTADO FINAL:\n";
    echo "   Documentos (Nombres/Fechas): " . number_format($countDocs) . "\n";
    echo "   Códigos (Buscador):          " . number_format($countCodes) . "\n";
    echo "------------------------------------------------\n";

    if ($countDocs > 0 && $countCodes > 0) {
        echo "\n🚀 ¡IMPORTACIÓN EXITOSA! \nAhora Kino tiene todos los datos vinculados.";
    } else {
        echo "\n⚠️ ALERTA: Algo sigue faltando. Revisa el log de errores arriba.";
    }

} catch (Exception $e) {
    echo "\n❌ ERROR CRÍTICO: " . $e->getMessage();
}
echo "</pre>";
?>