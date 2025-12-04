<?php
require_once 'config.php';

echo "🚀 Iniciando migración de base de datos...\n";

try {
    $sql = file_get_contents(__DIR__ . '/database/init.sql');

    if (!$sql) {
        die("❌ Error: No se pudo leer database/init.sql\n");
    }
<?php
require_once 'config.php';

echo "🚀 Iniciando migración de base de datos...\n";

try {
    $sql = file_get_contents(__DIR__ . '/database/init.sql');

    if (!$sql) {
        die("❌ Error: No se pudo leer database/init.sql\n");
    }

    // Dividir por sentencias si es necesario, pero PDO::exec a veces maneja múltiples.
    // Sin embargo, para mayor seguridad, a veces es mejor ejecutar una por una si hay delimitadores complejos.
    // Aquí asumimos que el SQL es simple y PDO puede manejarlo o lo dividimos por punto y coma.
    // Nota: PDO::exec puede ejecutar múltiples queries en MySQL si la configuración lo permite (emulate prepares).

    $db->exec($sql);
    
    echo "✅ Migración completada exitosamente.\n";
    
    // Crear cliente KINO inicial si no existe
    echo "🔍 Verificando cliente KINO...\n";
    $stmt = $db->prepare('SELECT id FROM _control_clientes WHERE codigo = ?');
    $stmt->execute(['kino']);
    
    if (!$stmt->fetch()) {
        echo "📝 Creando cliente inicial KINO...\n";
        $password_hash = password_hash('kino2024', PASSWORD_DEFAULT);
        $stmt = $db->prepare('INSERT INTO _control_clientes (codigo, nombre, password_hash, activo) VALUES (?, ?, ?, 1)');
        $stmt->execute(['kino', 'KINO Company', $password_hash]);
        echo "✅ Cliente KINO creado (usuario: kino, contraseña: kino2024)\n";
    } else {
        echo "✅ Cliente KINO ya existe\n";
    }
    
    exit(0); // Exit cleanly to allow server to start
} catch (PDOException $e) {
    echo "❌ Error en la migración: " . $e->getMessage() . "\n";
    echo "⚠️ Continuando con el inicio del servidor...\n";
    exit(0); // Exit anyway to allow server to start
}
?>