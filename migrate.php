<?php
require_once 'config.php';

echo "🚀 Iniciando migración de base de datos...\n";

try {
    // Ejecutar init.sql
    $sql = file_get_contents(__DIR__ . '/database/init.sql');
    if (!$sql) {
        die("❌ Error: No se pudo leer database/init.sql\n");
    }
    $db->exec($sql);
    echo "✅ Esquema base creado.\n";

    // Agregar columnas de color si no existen
    $checkCol = $db->query("SHOW COLUMNS FROM _control_clientes LIKE 'color_primario'");
    if ($checkCol->rowCount() == 0) {
        $db->exec("ALTER TABLE _control_clientes ADD COLUMN color_primario VARCHAR(7) DEFAULT '#2563eb'");
        $db->exec("ALTER TABLE _control_clientes ADD COLUMN color_secundario VARCHAR(7) DEFAULT '#F87171'");
        echo "✅ Columnas de color agregadas.\n";
    }

    // Crear cliente KINO inicial si no existe
    echo "🔍 Verificando cliente KINO...\n";
    $stmt = $db->prepare('SELECT id FROM _control_clientes WHERE codigo = ?');
    $stmt->execute(['kino']);

    if (!$stmt->fetch()) {
        echo "📝 Creando cliente inicial KINO...\n";
        $password_hash = password_hash('kino2024', PASSWORD_DEFAULT);
        $stmt = $db->prepare('INSERT INTO _control_clientes (codigo, nombre, password_hash, activo) VALUES (?, ?, ?, 1)');
        $stmt->execute(['kino', 'KINO Company', $password_hash]);

        // Crear tablas para KINO
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

        echo "✅ Cliente KINO creado (usuario: kino, contraseña: kino2024)\n";
    } else {
        echo "✅ Cliente KINO ya existe\n";
    }

    exit(0);
} catch (PDOException $e) {
    echo "❌ Error en la migración: " . $e->getMessage() . "\n";
    echo "⚠️ Continuando con el inicio del servidor...\n";
    exit(0);
}
?>