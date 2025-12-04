<?php
require_once __DIR__ . '/config.php';

echo "🚀 Creando cliente inicial KINO con datos de la base de datos...\n";

try {
    // 1. Crear cliente KINO en _control_clientes
    echo "📝 Creando cliente KINO...\n";

    $codigo = 'kino';
    $nombre = 'KINO Company';
    $password = 'kino2024'; // Contraseña por defecto
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    // Verificar si ya existe
    $stmt = $db->prepare('SELECT id FROM _control_clientes WHERE codigo = ?');
    $stmt->execute([$codigo]);

    if ($stmt->fetch()) {
        echo "⚠️ Cliente KINO ya existe, saltando creación...\n";
    } else {
        $stmt = $db->prepare('INSERT INTO _control_clientes (codigo, nombre, password_hash, activo) VALUES (?, ?, ?, 1)');
        $stmt->execute([$codigo, $nombre, $password_hash]);
        echo "✅ Cliente KINO creado exitosamente\n";
        echo "   Código: kino\n";
        echo "   Contraseña: kino2024\n\n";
    }

    // 2. Importar datos de documents y codes desde el SQL file
    echo "📦 Importando datos de documents y codes...\n";

    $sqlFile = __DIR__ . '/if0_39064130_buscador (10).sql';

    if (!file_exists($sqlFile)) {
        throw new Exception("No se encuentra el archivo SQL: $sqlFile");
    }

    $sql = file_get_contents($sqlFile);

    // Extraer solo los INSERT statements para documents y codes
    // Esto es una simplificación - en producción usarías un parser SQL más robusto

    // Verificar si las tablas ya tienen datos
    $docsCount = $db->query("SELECT COUNT(*) FROM documents")->fetchColumn();
    $codesCount = $db->query("SELECT COUNT(*) FROM codes")->fetchColumn();

    if ($docsCount > 0 || $codesCount > 0) {
        echo "⚠️ Las tablas documents/codes ya tienen datos, saltando importación...\n";
    } else {
        // Ejecutar el SQL completo (esto importará los datos)
        // Nota: PDO::exec no soporta múltiples queries, así que usamos un enfoque diferente

        // Dividir por punto y coma y ejecutar cada statement
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            function ($stmt) {
                return !empty($stmt) &&
                    (stripos($stmt, 'INSERT INTO') === 0 ||
                        stripos($stmt, 'INSERT INTO `documents`') !== false ||
                        stripos($stmt, 'INSERT INTO `codes`') !== false);
            }
        );

        $db->beginTransaction();

        foreach ($statements as $statement) {
            if (stripos($statement, 'INSERT INTO') !== false) {
                try {
                    $db->exec($statement);
                } catch (PDOException $e) {
                    // Ignorar errores de duplicados
                    if ($e->getCode() != 23000) {
                        throw $e;
                    }
                }
            }
        }

        $db->commit();

        echo "✅ Datos importados exitosamente\n";
    }

    // 3. Mostrar resumen
    $docsCount = $db->query("SELECT COUNT(*) FROM documents")->fetchColumn();
    $codesCount = $db->query("SELECT COUNT(*) FROM codes")->fetchColumn();

    echo "\n📊 Resumen:\n";
    echo "   Documentos: $docsCount\n";
    echo "   Códigos: $codesCount\n";
    echo "\n✅ Cliente KINO configurado correctamente\n";
    echo "   Accede con: kino / kino2024\n";

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>