<?php
require_once 'config.php';

echo "🔧 Creando cliente de prueba...\n";

try {
    // Crear un cliente de prueba
    $codigo = 'TEST001';
    $nombre = 'Cliente de Prueba';
    $password = 'test123'; // Contraseña: test123
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $email = 'test@example.com';

    $stmt = $db->prepare("INSERT INTO _control_clientes (codigo, nombre, password_hash, email, activo) VALUES (?, ?, ?, ?, 1)");
    $stmt->execute([$codigo, $nombre, $password_hash, $email]);

    echo "✅ Cliente creado exitosamente:\n";
    echo "   Código: $codigo\n";
    echo "   Nombre: $nombre\n";
    echo "   Contraseña: $password\n";
    echo "\n";
    echo "Ahora puedes acceder a: https://tu-app.railway.app/index.php\n";
    echo "Y usar estas credenciales para hacer login.\n";

} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        echo "⚠️ El cliente TEST001 ya existe.\n";
        echo "Puedes usar:\n";
        echo "   Código: TEST001\n";
        echo "   Contraseña: test123\n";
    } else {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
}
?>