<?php
// verify_system.php
// SCRIPT DE VERIFICACIÓN COMPLETA DEL SISTEMA KINO

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Verificación del Sistema Kino</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1200px; margin: 20px auto; padding: 20px; background: #f5f5f5; }
        h1 { color: #333; border-bottom: 3px solid #4CAF50; padding-bottom: 10px; }
        h2 { color: #555; margin-top: 30px; border-left: 4px solid #2196F3; padding-left: 10px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; margin: 10px 0; border-radius: 5px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; background: white; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #4CAF50; color: white; }
        tr:hover { background-color: #f5f5f5; }
        .status-ok { color: #28a745; font-weight: bold; }
        .status-error { color: #dc3545; font-weight: bold; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>";

echo "<h1>🔍 Verificación del Sistema Kino</h1>";
echo "<p><strong>Fecha:</strong> " . date('Y-m-d H:i:s') . "</p>";

$errors = 0;
$warnings = 0;

// ============================================
// 1. VERIFICAR VARIABLES DE ENTORNO
// ============================================
echo "<h2>1️⃣ Variables de Entorno</h2>";

$env_vars = [
    'MYSQLHOST' => getenv('MYSQLHOST'),
    'MYSQLUSER' => getenv('MYSQLUSER'),
    'MYSQLPASSWORD' => getenv('MYSQLPASSWORD') ? '****** (configurada)' : null,
    'MYSQLDATABASE' => getenv('MYSQLDATABASE'),
    'MYSQLPORT' => getenv('MYSQLPORT'),
    'PORT' => getenv('PORT') ?: 'No configurada (usará 80)',
];

echo "<table>";
echo "<tr><th>Variable</th><th>Valor</th><th>Estado</th></tr>";
foreach ($env_vars as $var => $value) {
    $status = $value ? "<span class='status-ok'>✅ OK</span>" : "<span class='status-error'>❌ Falta</span>";
    if (!$value && $var !== 'PORT')
        $errors++;
    echo "<tr><td><code>$var</code></td><td>" . ($value ?: 'No configurada') . "</td><td>$status</td></tr>";
}
echo "</table>";

// ============================================
// 2. VERIFICAR CONEXIÓN A BASE DE DATOS
// ============================================
echo "<h2>2️⃣ Conexión a Base de Datos</h2>";

try {
    require_once __DIR__ . '/config.php';
    echo "<div class='success'>✅ <strong>Conexión exitosa a la base de datos</strong></div>";

    // Obtener información del servidor
    $version = $db->query("SELECT VERSION()")->fetchColumn();
    echo "<div class='info'>📊 <strong>MySQL Version:</strong> $version</div>";

} catch (Exception $e) {
    echo "<div class='error'>❌ <strong>Error de conexión:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
    $errors++;
}

// ============================================
// 3. VERIFICAR TABLAS
// ============================================
echo "<h2>3️⃣ Estructura de Base de Datos</h2>";

if (isset($db)) {
    $required_tables = ['documents', 'codes'];

    echo "<table>";
    echo "<tr><th>Tabla</th><th>Registros</th><th>Estado</th></tr>";

    foreach ($required_tables as $table) {
        try {
            $count = $db->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
            $status = $count > 0 ? "<span class='status-ok'>✅ OK ($count registros)</span>" : "<span class='status-error'>⚠️ Vacía</span>";
            if ($count == 0)
                $warnings++;
            echo "<tr><td><code>$table</code></td><td>" . number_format($count) . "</td><td>$status</td></tr>";
        } catch (PDOException $e) {
            echo "<tr><td><code>$table</code></td><td>-</td><td><span class='status-error'>❌ No existe</span></td></tr>";
            $errors++;
        }
    }
    echo "</table>";

    // Verificar estructura de tablas
    echo "<h3>📋 Estructura de Tablas</h3>";

    foreach ($required_tables as $table) {
        try {
            $columns = $db->query("DESCRIBE `$table`")->fetchAll();
            echo "<details><summary><strong>Tabla: $table</strong> (" . count($columns) . " columnas)</summary>";
            echo "<table>";
            echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th></tr>";
            foreach ($columns as $col) {
                echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td><td>{$col['Null']}</td><td>{$col['Key']}</td></tr>";
            }
            echo "</table></details>";
        } catch (PDOException $e) {
            // Tabla no existe, ya reportado arriba
        }
    }
}

// ============================================
// 4. VERIFICAR ARCHIVOS CRÍTICOS
// ============================================
echo "<h2>4️⃣ Archivos del Sistema</h2>";

$critical_files = [
    'config.php' => 'Configuración de base de datos',
    'api.php' => 'API principal',
    'index.php' => 'Página principal',
    'pdf_search.py' => 'Script de búsqueda Python',
    'import_force.php' => 'Script de importación forzada',
    'fix_documents.php' => 'Script de reparación de documentos',
];

echo "<table>";
echo "<tr><th>Archivo</th><th>Descripción</th><th>Estado</th></tr>";
foreach ($critical_files as $file => $desc) {
    $exists = file_exists(__DIR__ . '/' . $file);
    $status = $exists ? "<span class='status-ok'>✅ Existe</span>" : "<span class='status-error'>❌ Falta</span>";
    if (!$exists)
        $warnings++;
    echo "<tr><td><code>$file</code></td><td>$desc</td><td>$status</td></tr>";
}
echo "</table>";

// ============================================
// 5. VERIFICAR PERMISOS DE CARPETAS
// ============================================
echo "<h2>5️⃣ Permisos de Carpetas</h2>";

$folders = ['uploads', 'pdfs'];

echo "<table>";
echo "<tr><th>Carpeta</th><th>Existe</th><th>Escribible</th><th>Estado</th></tr>";
foreach ($folders as $folder) {
    $path = __DIR__ . '/' . $folder;
    $exists = is_dir($path);
    $writable = $exists && is_writable($path);

    $status = ($exists && $writable) ? "<span class='status-ok'>✅ OK</span>" : "<span class='status-error'>❌ Problema</span>";
    if (!$exists || !$writable)
        $warnings++;

    echo "<tr><td><code>$folder/</code></td><td>" . ($exists ? 'Sí' : 'No') . "</td><td>" . ($writable ? 'Sí' : 'No') . "</td><td>$status</td></tr>";
}
echo "</table>";

// ============================================
// 6. VERIFICAR EXTENSIONES PHP
// ============================================
echo "<h2>6️⃣ Extensiones PHP</h2>";

$required_extensions = ['pdo', 'pdo_mysql', 'mysqli', 'gd', 'json'];

echo "<table>";
echo "<tr><th>Extensión</th><th>Estado</th></tr>";
foreach ($required_extensions as $ext) {
    $loaded = extension_loaded($ext);
    $status = $loaded ? "<span class='status-ok'>✅ Cargada</span>" : "<span class='status-error'>❌ Falta</span>";
    if (!$loaded)
        $errors++;
    echo "<tr><td><code>$ext</code></td><td>$status</td></tr>";
}
echo "</table>";

// ============================================
// 7. VERIFICAR PYTHON
// ============================================
echo "<h2>7️⃣ Python y Dependencias</h2>";

$python_check = shell_exec("python3 --version 2>&1");
if ($python_check) {
    echo "<div class='success'>✅ <strong>Python instalado:</strong> " . htmlspecialchars(trim($python_check)) . "</div>";

    // Verificar PyPDF2
    $pypdf2_check = shell_exec("python3 -c 'import PyPDF2; print(PyPDF2.__version__)' 2>&1");
    if ($pypdf2_check && !strpos($pypdf2_check, 'Error')) {
        echo "<div class='success'>✅ <strong>PyPDF2 instalado:</strong> Version " . htmlspecialchars(trim($pypdf2_check)) . "</div>";
    } else {
        echo "<div class='error'>❌ <strong>PyPDF2 no encontrado</strong></div>";
        $errors++;
    }
} else {
    echo "<div class='error'>❌ <strong>Python no encontrado</strong></div>";
    $errors++;
}

// ============================================
// 8. INFORMACIÓN DEL SISTEMA
// ============================================
echo "<h2>8️⃣ Información del Sistema</h2>";

echo "<table>";
echo "<tr><th>Parámetro</th><th>Valor</th></tr>";
echo "<tr><td>PHP Version</td><td>" . phpversion() . "</td></tr>";
echo "<tr><td>Sistema Operativo</td><td>" . php_uname() . "</td></tr>";
echo "<tr><td>Servidor Web</td><td>" . ($_SERVER['SERVER_SOFTWARE'] ?? 'Desconocido') . "</td></tr>";
echo "<tr><td>Document Root</td><td>" . ($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') . "</td></tr>";
echo "<tr><td>Script Path</td><td>" . __DIR__ . "</td></tr>";
echo "</table>";

// ============================================
// RESUMEN FINAL
// ============================================
echo "<h2>📊 Resumen Final</h2>";

if ($errors == 0 && $warnings == 0) {
    echo "<div class='success'>";
    echo "<h3>🎉 ¡Sistema completamente funcional!</h3>";
    echo "<p>Todos los componentes están correctamente configurados.</p>";
    echo "<p><strong>Próximos pasos:</strong></p>";
    echo "<ul>";
    echo "<li>Si las tablas están vacías, ejecuta <code>import_force.php</code></li>";
    echo "<li>Prueba la búsqueda de códigos en la aplicación</li>";
    echo "</ul>";
    echo "</div>";
} else {
    echo "<div class='error'>";
    echo "<h3>⚠️ Se encontraron problemas</h3>";
    echo "<p><strong>Errores críticos:</strong> $errors</p>";
    echo "<p><strong>Advertencias:</strong> $warnings</p>";
    echo "<p>Revisa los detalles arriba y corrige los problemas antes de continuar.</p>";
    echo "</div>";
}

echo "<hr>";
echo "<p style='text-align: center; color: #666;'><small>Generado por verify_system.php | Kino Multi-Client System</small></p>";

echo "</body></html>";
?>