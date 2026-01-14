<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

$configFile = __DIR__ . '/config.php';
$helperFile = __DIR__ . '/helpers/tenant.php';

if (!file_exists($configFile))
    die('❌ Error: No se encuentra config.php');
if (!file_exists($helperFile))
    die('❌ Error: No se encuentra helpers/tenant.php');

require $configFile;
require $helperFile;

if (!isset($db) || !($db instanceof PDO))
    die('❌ Error: No hay conexión a la base de datos');

$err = $ok = null;
$codigoInput = $_POST['codigo'] ?? '';
$nombreInput = $_POST['nombre'] ?? '';
$colorPrimario = $_POST['color_primario'] ?? '#2563eb';
$colorSecundario = $_POST['color_secundario'] ?? '#F87171';

// Procesar acciones
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ELIMINAR CLIENTE
if ($action === 'delete' && !empty($_POST['delete_codigo'])) {
    try {
        $codigo = sanitize_code($_POST['delete_codigo']);

        // Eliminar tablas del cliente
        $db->exec("DROP TABLE IF EXISTS `{$codigo}_codes`");
        $db->exec("DROP TABLE IF EXISTS `{$codigo}_documents`");

        // Eliminar archivos de uploads
        $uploadsDir = __DIR__ . '/uploads/' . $codigo;
        if (is_dir($uploadsDir)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($uploadsDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($files as $file) {
                $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
            }
            rmdir($uploadsDir);
        }

        // Eliminar registro del cliente
        $db->prepare('DELETE FROM _control_clientes WHERE codigo = ?')->execute([$codigo]);

        $ok = "✅ Cliente '{$codigo}' eliminado completamente.";
    } catch (Exception $e) {
        $err = '❌ ' . $e->getMessage();
    }
}

// EDITAR CLIENTE
if ($action === 'edit' && !empty($_POST['edit_codigo'])) {
    try {
        $codigo = sanitize_code($_POST['edit_codigo']);
        $nombre = trim($_POST['edit_nombre'] ?? '');
        $titulo = trim($_POST['edit_titulo_app'] ?? 'KINO COMPANY SAS V1');
        $borrado = trim($_POST['edit_clave_borrado'] ?? '0000'); // <--- NUEVO
        $pass = trim($_POST['edit_password'] ?? '');
        $colorP = $_POST['edit_color_primario'] ?? '#2563eb';
        $colorS = $_POST['edit_color_secundario'] ?? '#F87171';

        if ($nombre) {
            // Se agrega clave_borrado al UPDATE
            $sql = 'UPDATE _control_clientes SET nombre = ?, titulo_app = ?, clave_borrado = ?, color_primario = ?, color_secundario = ?';
            $params = [$nombre, $titulo, $borrado, $colorP, $colorS];

            if ($pass) {
                $sql .= ', password_hash = ?';
                $params[] = password_hash($pass, PASSWORD_BCRYPT);
            }
            $sql .= ' WHERE codigo = ?';
            $params[] = $codigo;

            $db->prepare($sql)->execute($params);
            $ok = "✅ Cliente '{$codigo}' actualizado.";
        }
    } catch (Exception $e) {
        $err = '❌ ' . $e->getMessage();
    }
}

// CLONAR DATOS (ENTRE CLIENTES)
if ($action === 'clone' && !empty($_POST['clone_from']) && !empty($_POST['clone_to'])) {
    try {
        $from = sanitize_code($_POST['clone_from']);
        $to = sanitize_code($_POST['clone_to']);

        // Verificar que ambos clientes existen
        $check = $db->prepare('SELECT codigo FROM _control_clientes WHERE codigo IN (?, ?)');
        $check->execute([$from, $to]);
        if ($check->rowCount() != 2)
            throw new Exception('Ambos clientes deben existir.');

        // Determinar tablas origen (si es kino, usar raíz)
        $tableDocsFrom = ($from === 'kino') ? 'documents' : "{$from}_documents";
        $tableCodesFrom = ($from === 'kino') ? 'codes' : "{$from}_codes";

        // Tablas destino siempre tienen prefijo (kino no se puede sobrescribir así)
        if ($to === 'kino')
            throw new Exception('No se puede clonar HACIA el maestro Kino.');
        $tableDocsTo = "{$to}_documents";
        $tableCodesTo = "{$to}_codes";

        // Clonar documentos
        // Nota: Ajustamos el path. Si viene de kino (uploads/), el path es 'archivo.pdf'. 
        // Si va a cliente (uploads/cliente/), el path debería ser relativo o absoluto?
        // El sistema actual parece usar paths relativos al root de uploads o al cliente.
        // La lógica de api.php maneja esto.

        $db->exec("INSERT INTO `{$tableDocsTo}` (name, date, path, codigos_extraidos) 
                   SELECT name, date, path, codigos_extraidos 
                   FROM `{$tableDocsFrom}`");

        // Mapear IDs y clonar códigos
        // Esto es complejo si los nombres no son únicos. Asumimos nombres+fecha únicos por simplicidad o copiamos todo.
        // Mejor estrategia: Limpiar destino y copiar todo.
        // Desactivar validación de FK temporalmente para permitir TRUNCATE
        $db->exec("SET FOREIGN_KEY_CHECKS = 0");

        // Limpiar destino (orden inverso por seguridad, aunque con el check=0 no importa)
        $db->exec("TRUNCATE TABLE `{$tableCodesTo}`");
        $db->exec("TRUNCATE TABLE `{$tableDocsTo}`");

        // Reactivar validación
        $db->exec("SET FOREIGN_KEY_CHECKS = 1");

        $db->exec("INSERT INTO `{$tableDocsTo}` (name, date, path, codigos_extraidos) 
                   SELECT name, date, path, codigos_extraidos 
                   FROM `{$tableDocsFrom}`");

        // Insertar códigos mapeando IDs (esto requiere que los IDs se mantengan o se re-calculen)
        // Si truncamos, los IDs de auto_increment se reinician? No necesariamente.
        // Hagamos un INSERT SELECT directo asumiendo integridad o un loop.
        // Para ser robusto:
        $docs = $db->query("SELECT id, name, date FROM `{$tableDocsFrom}`")->fetchAll();
        foreach ($docs as $doc) {
            // Buscar el nuevo ID insertado
            $stmt = $db->prepare("SELECT id FROM `{$tableDocsTo}` WHERE name = ? AND date = ? LIMIT 1");
            $stmt->execute([$doc['name'], $doc['date']]);
            $newId = $stmt->fetchColumn();

            if ($newId) {
                $db->prepare("INSERT INTO `{$tableCodesTo}` (document_id, code) 
                              SELECT ?, code FROM `{$tableCodesFrom}` WHERE document_id = ?")
                    ->execute([$newId, $doc['id']]);
            }
        }

        // Copiar archivos
        $srcDir = ($from === 'kino') ? __DIR__ . '/uploads' : __DIR__ . '/uploads/' . $from;
        $dstDir = __DIR__ . '/uploads/' . $to;

        if (!is_dir($dstDir))
            mkdir($dstDir, 0777, true);

        // Usar la función de tenant.php
        copy_dir_files_only($srcDir, $dstDir);

        $ok = "✅ Datos clonados de '{$from}' a '{$to}'.";
    } catch (Exception $e) {
        $err = '❌ ' . $e->getMessage();
    }
}

// CREAR NUEVO CLIENTE (CLONADO DESDE KINO)
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $codigo = sanitize_code($codigoInput);
        $nombre = trim($nombreInput);
        $titulo = trim($_POST['titulo_app'] ?? 'KINO COMPANY SAS V1');
        $borrado = trim($_POST['clave_borrado'] ?? '0000'); // <--- NUEVO
        $pass = trim($_POST['password'] ?? '');

        if ($codigo === '' || $nombre === '' || $pass === '') {
            throw new Exception('Faltan campos requeridos.');
        }

        // 1. Validar si existe
        $st = $db->prepare('SELECT 1 FROM _control_clientes WHERE codigo = ?');
        $st->execute([$codigo]);
        if ($st->fetch())
            throw new Exception('El código ya existe.');

        // 2. Crear registro en _control_clientes
        $hash = password_hash($pass, PASSWORD_BCRYPT);
        $db->prepare('INSERT INTO _control_clientes (codigo, nombre, titulo_app, clave_borrado, password_hash, color_primario, color_secundario, activo) VALUES (?, ?, ?, ?, ?, ?, ?, 1)')
            ->execute([$codigo, $nombre, $titulo, $borrado, $hash, $colorPrimario, $colorSecundario]);

        // 3. Crear tablas clonando la estructura de KINO (documents y codes)
        $db->exec("CREATE TABLE IF NOT EXISTS `{$codigo}_documents` LIKE `documents`");
        $db->exec("CREATE TABLE IF NOT EXISTS `{$codigo}_codes` LIKE `codes`");

        // 4. Copiar DATOS de la base de datos de Kino al nuevo cliente
        $db->exec("INSERT INTO `{$codigo}_documents` SELECT * FROM `documents`");
        $db->exec("INSERT INTO `{$codigo}_codes` SELECT * FROM `codes`");

        // 5. Copiar ARCHIVOS FÍSICOS de Kino al nuevo cliente
        $uploadsRoot = __DIR__ . '/uploads';       // Origen (Kino)
        $uploadsClient = $uploadsRoot . '/' . $codigo; // Destino (Nuevo Cliente)

        if (!is_dir($uploadsClient)) {
            mkdir($uploadsClient, 0777, true);
        }

        // Usamos la función optimizada en tenant.php
        copy_dir_files_only($uploadsRoot, $uploadsClient);

        $ok = "✅ Cliente creado y clonado de Kino.<br><strong>Código:</strong> {$codigo}<br><strong>Contraseña:</strong> {$pass}<br><a href='login.php' style='color:#fff;text-decoration:underline;'>Ir al Login</a>";
    } catch (Exception $e) {
        $err = '❌ ' . $e->getMessage();
    }
}

// CREAR NUEVO CLIENTE (VACÍO - SOLO ESTRUCTURA)
if ($action === 'create_empty' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $codigo = sanitize_code($codigoInput);
        $nombre = trim($nombreInput);
        $titulo = trim($_POST['titulo_app'] ?? 'KINO COMPANY SAS V1');
        $borrado = trim($_POST['clave_borrado'] ?? '0000'); // <--- NUEVO
        $pass = trim($_POST['password'] ?? '');

        if ($codigo === '' || $nombre === '' || $pass === '') {
            throw new Exception('Faltan campos requeridos.');
        }

        // 1. Validar si existe
        $st = $db->prepare('SELECT 1 FROM _control_clientes WHERE codigo = ?');
        $st->execute([$codigo]);
        if ($st->fetch())
            throw new Exception('El código ya existe.');

        // 2. Crear registro en _control_clientes
        $hash = password_hash($pass, PASSWORD_BCRYPT);
        $db->prepare('INSERT INTO _control_clientes (codigo, nombre, titulo_app, clave_borrado, password_hash, color_primario, color_secundario, activo) VALUES (?, ?, ?, ?, ?, ?, ?, 1)')
            ->execute([$codigo, $nombre, $titulo, $borrado, $hash, $colorPrimario, $colorSecundario]);

        // 3. Crear tablas clonando la estructura (LIKE) pero SIN copiar datos
        $db->exec("CREATE TABLE IF NOT EXISTS `{$codigo}_documents` LIKE `documents`");
        $db->exec("CREATE TABLE IF NOT EXISTS `{$codigo}_codes` LIKE `codes`");

        // 4. Crear carpeta de uploads VACÍA
        $uploadsClient = __DIR__ . '/uploads/' . $codigo;
        if (!is_dir($uploadsClient)) {
            mkdir($uploadsClient, 0777, true);
        }

        // Crear un index.html vacío por seguridad para evitar listar directorios
        file_put_contents($uploadsClient . '/index.html', '');

        $ok = "✅ Cliente VACÍO creado.<br><strong>Código:</strong> {$codigo}<br><strong>Contraseña:</strong> {$pass}<br>Listo para subir archivos nuevos.";
    } catch (Exception $e) {
        $err = '❌ ' . $e->getMessage();
    }
}

// Obtener lista de clientes
$clients = [];
try {
    $clients = $db->query("SELECT codigo, nombre, titulo_app, color_primario, color_secundario, activo, fecha_creacion FROM _control_clientes ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("❌ Error cargando clientes (Posible falta de columna en DB): " . $e->getMessage() . "<br><a href='add_title_column.php'>Click aquí para intentar corregir BD</a>");
}
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gestor de clientes de APP KINO</title>
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <style>
        /* VARIABLES GLOBALES - SOFT INDIGO THEME */
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --primary-light: #e0e7ff;

            --bg-body: #f8fafc;
            --bg-card: #ffffff;

            --text-main: #334155;
            --text-muted: #64748b;

            --border-light: #e2e8f0;

            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.025);

            --radius-md: 12px;
            --radius-lg: 16px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--bg-body);
            color: var(--text-main);
            min-height: 100vh;
            padding: 2rem;
            background-image:
                radial-gradient(at 0% 0%, rgba(99, 102, 241, 0.05) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(139, 92, 246, 0.05) 0px, transparent 50%);
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
        }

        .card {
            background: var(--bg-card);
            padding: 2rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            margin-bottom: 2rem;
            border: 1px solid var(--border-light);
        }

        h2 {
            text-align: center;
            margin-bottom: 1.5rem;
            color: var(--text-main);
            font-weight: 700;
            letter-spacing: -0.025em;
        }

        label {
            display: block;
            margin-top: 1rem;
            color: var(--text-main);
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        input,
        select {
            width: 100%;
            padding: 0.75rem 1rem;
            margin-top: 0;
            border: 1px solid var(--border-light);
            border-radius: var(--radius-md);
            background: #fff;
            color: var(--text-main);
            font-size: 0.95rem;
            font-family: inherit;
            transition: all 0.2s;
        }

        input:focus,
        select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-light);
        }

        input[type="color"] {
            height: 44px;
            padding: 4px;
            cursor: pointer;
        }

        button {
            margin-top: 1.5rem;
            width: 100%;
            padding: 0.875rem;
            border: 0;
            border-radius: var(--radius-md);
            background: var(--primary);
            color: #fff;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 4px 6px rgba(99, 102, 241, 0.2);
            font-size: 0.95rem;
        }

        button:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 6px 10px rgba(99, 102, 241, 0.3);
        }

        button:active {
            transform: translateY(0);
        }

        .btn-danger {
            background: #fee2e2;
            color: #dc2626;
            box-shadow: none;
        }

        .btn-danger:hover {
            background: #fecaca;
            color: #b91c1c;
        }

        .btn-warning {
            background: #fef3c7;
            color: #d97706;
            box-shadow: none;
        }

        .btn-warning:hover {
            background: #fde68a;
            color: #b45309;
        }

        .btn-success {
            background: #dcfce7;
            color: #16a34a;
            box-shadow: none;
        }

        .btn-success:hover {
            background: #bbf7d0;
            color: #15803d;
        }

        .ok {
            background: #dcfce7;
            color: #166534;
            padding: 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 1rem;
            border: 1px solid #bbf7d0;
            font-weight: 500;
        }

        .err {
            background: #fee2e2;
            color: #991b1b;
            padding: 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 1rem;
            border: 1px solid #fecaca;
            font-weight: 500;
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 1rem;
        }

        th {
            background: var(--bg-body);
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border-light);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-light);
            background: white;
            color: var(--text-main);
        }

        tr:last-child td {
            border-bottom: none;
        }

        .color-dot {
            display: inline-block;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            vertical-align: middle;
            border: 2px solid #fff;
            box-shadow: 0 0 0 1px var(--border-light);
            margin-right: 4px;
        }

        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .actions button {
            width: auto;
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
            margin: 0;
            border-radius: 8px;
        }

        .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        @media (max-width: 600px) {
            .row {
                grid-template-columns: 1fr;
            }

            .actions {
                flex-direction: column;
            }
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(4px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.2s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: #fff;
            padding: 2rem;
            border-radius: var(--radius-lg);
            max-width: 450px;
            width: 90%;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-light);
            transform: translateY(0);
            animation: slideUp 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes slideUp {
            from {
                transform: translateY(20px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal h3 {
            margin-bottom: 1.5rem;
            font-size: 1.25rem;
            color: var(--text-main);
        }

        .close-btn {
            background: transparent;
            color: var(--text-muted);
            border: 1px solid var(--border-light);
            margin-top: 0.5rem;
            box-shadow: none;
        }

        .close-btn:hover {
            background: var(--bg-body);
            color: var(--text-main);
            transform: none;
            box-shadow: none;
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Crear Cliente -->
        <div class="card">
            <h2>Gestor de clientes de APP KINO</h2>
            <?php if ($ok): ?>
                <div class="ok"><?= $ok ?></div><?php endif; ?>
            <?php if ($err): ?>
                <div class="err"><?= htmlspecialchars($err) ?></div><?php endif; ?>
            <form method="post">
                <div class="row">
                    <div>
                        <label>Código (minúsculas)</label>
                        <input name="codigo" pattern="[a-z0-9_]+" required placeholder="cliente1">
                    </div>
                    <div>
                        <label>Nombre Interno</label>
                        <input name="nombre" required placeholder="Empresa XYZ">
                    </div>
                    <div>
                        <label>Título de la App (Encabezado)</label>
                        <input name="titulo_app" placeholder="Ej: EMPRESA XYZ CATALOGO" value="KINO COMPANY SAS V1">
                    </div>
                </div>
                <div class="row">
                    <div>
                        <label>Contraseña Acceso (Login)</label>
                        <input name="password" type="password" required minlength="4">
                    </div>
                    <div>
                        <label>Clave para Borrar Docs</label>
                        <input name="clave_borrado" required value="0000" placeholder="Ej: 1234">
                    </div>
                    <div class="row">
                        <div>
                            <label>Color Primario</label>
                            <input name="color_primario" type="color" value="#2563eb">
                        </div>
                        <div>
                            <label>Color Secundario</label>
                            <input name="color_secundario" type="color" value="#F87171">
                        </div>
                    </div>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 16px;">
                    <button type="submit" name="action" value="create" style="flex: 1;">
                        ✅ Clonar Todo (Datos Kino)
                    </button>

                    <button type="submit" name="action" value="create_empty"
                        style="flex: 1; background-color: #4b5563;">
                        ✨ Crear Cliente Vacío
                    </button>
                </div>
            </form>
        </div>

        <!-- Herramientas -->
        <div class="card">
            <h2>🛠️ Utiles Globales</h2>
            <button onclick="openExtractionModal()" style="background: #8b5cf6;">🚀 Abrir Extractor de Códigos
                PDF</button>
        </div>

        <!-- Lista de Clientes -->
        <div class="card">
            <h2>📋 Clientes Registrados</h2>
            <?php if (empty($clients)): ?>
                <p style="text-align:center;color:#9fb3ce;">No hay clientes.</p>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Código</th>
                                <th>Colores</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clients as $c): ?>
                                <tr>
                                    <td><?= htmlspecialchars($c['nombre']) ?></td>
                                    <td><code><?= htmlspecialchars($c['codigo']) ?></code></td>
                                    <td>
                                        <span class="color-dot"
                                            style="background:<?= $c['color_primario'] ?? '#2563eb' ?>"></span>
                                        <span class="color-dot"
                                            style="background:<?= $c['color_secundario'] ?? '#F87171' ?>"></span>
                                    </td>
                                    <td class="actions">
                                        <a href="index.html?c=<?= urlencode($c['codigo']) ?>" target="_blank"><button
                                                type="button" class="btn-success">🔗 App</button></a>
                                        <button type="button"
                                            onclick="openEdit('<?= $c['codigo'] ?>', '<?= htmlspecialchars($c['nombre']) ?>', '<?= htmlspecialchars($c['titulo_app'] ?? '') ?>', '<?= $c['clave_borrado'] ?? '0000' ?>', '<?= $c['color_primario'] ?? '#2563eb' ?>', '<?= $c['color_secundario'] ?? '#F87171' ?>')"
                                            class="btn-warning">✏️ Editar</button>
                                        <button type="button" onclick="openDelete('<?= $c['codigo'] ?>')" class="btn-danger">🗑️
                                            Eliminar</button>
                                        <button type="button" onclick="openClone('<?= $c['codigo'] ?>')">📋 Clonar</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Editar -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <h3>✏️ Editar Cliente</h3>
            <form method="post">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="edit_codigo" id="edit_codigo">
                <label>Nombre</label>
                <input name="edit_nombre" id="edit_nombre" required>
                <label>Título de la App</label>
                <input name="edit_titulo_app" id="edit_titulo_app" required>
                <label>Clave de Borrado</label>
                <input name="edit_clave_borrado" id="edit_clave_borrado" required>
                <label>Nueva Contraseña (dejar vacío para no cambiar)</label>
                <input name="edit_password" type="password">
                <div class="row">
                    <div><label>Color Primario</label><input name="edit_color_primario" id="edit_color_p" type="color">
                    </div>
                    <div><label>Color Secundario</label><input name="edit_color_secundario" id="edit_color_s"
                            type="color"></div>
                </div>
                <button type="submit">💾 Guardar</button>
                <button type="button" class="close-btn" onclick="closeModal('editModal')">Cancelar</button>
            </form>
        </div>
    </div>

    <!-- Modal Eliminar -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <h3>🗑️ Eliminar Cliente</h3>
            <p>¿Seguro que deseas eliminar este cliente? Se borrarán TODOS sus datos y archivos.</p>
            <form method="post">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="delete_codigo" id="delete_codigo">
                <button type="submit" class="btn-danger">Sí, Eliminar</button>
                <button type="button" class="close-btn" onclick="closeModal('deleteModal')">Cancelar</button>
            </form>
        </div>
    </div>

    <!-- Modal Clonar -->
    <div id="cloneModal" class="modal">
        <div class="modal-content">
            <h3>📋 Clonar Datos</h3>
            <form method="post">
                <input type="hidden" name="action" value="clone">
                <label>Desde (origen)</label>
                <input name="clone_from" id="clone_from" readonly>
                <label>Hacia (destino)</label>
                <select name="clone_to" id="clone_to" required>
                    <option value="">Seleccionar cliente...</option>
                    <?php foreach ($clients as $c): ?>
                        <option value="<?= $c['codigo'] ?>"><?= htmlspecialchars($c['nombre']) ?> (<?= $c['codigo'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit">📋 Clonar Datos</button>
                <button type="button" class="close-btn" onclick="closeModal('cloneModal')">Cancelar</button>
            </form>
        </div>
    </div>

    <script>
        function openEdit(codigo, nombre, titulo, borrado, colorP, colorS) {
            document.getElementById('edit_codigo').value = codigo;
            document.getElementById('edit_nombre').value = nombre;
            document.getElementById('edit_titulo_app').value = titulo;
            document.getElementById('edit_clave_borrado').value = borrado;
            document.getElementById('edit_color_p').value = colorP;
            document.getElementById('edit_color_s').value = colorS;
            document.getElementById('editModal').classList.add('active');
        }
        function openDelete(codigo) {
            document.getElementById('delete_codigo').value = codigo;
            document.getElementById('deleteModal').classList.add('active');
        }
        function openClone(codigo) {
            document.getElementById('clone_from').value = codigo;
            document.getElementById('cloneModal').classList.add('active');
        }
        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }

        // --- FUNCIONES DEL EXTRACTOR ---
        function openExtractionModal() {
            document.getElementById('extractionModal').style.display = 'flex';
        }

        function closeExtractionModal() {
            document.getElementById('extractionModal').style.display = 'none';
        }

        async function startExtraction() {
            const btn = document.querySelector('.btn-confirm');
            btn.innerHTML = '⏳ Procesando...';
            btn.disabled = true;

            const formData = new FormData(document.getElementById('extractionRulesForm'));
            const params = new URLSearchParams(formData);

            try {
                const response = await fetch('pdf-search.php?' + params.toString(), {
                    method: 'GET'
                });

                if (!response.ok) throw new Error("Error en la respuesta del servidor");

                const text = await response.text();

                closeExtractionModal();
                showVerificationArea(text);

            } catch (error) {
                alert("Error al extraer: " + error.message);
            } finally {
                btn.innerHTML = '🚀 Extraer Códigos';
                btn.disabled = false;
            }
        }

        function showVerificationArea(content) {
            const area = document.getElementById('verificationArea');
            const textarea = document.getElementById('extractedCodesArea');

            area.style.display = 'block';
            textarea.value = content.trim();

            updateCount();
            textarea.addEventListener('input', updateCount);
            area.scrollIntoView({ behavior: 'smooth' });
        }

        function updateCount() {
            const lines = document.getElementById('extractedCodesArea').value.split('\n').filter(l => l.trim() !== '');
            document.getElementById('codeCount').innerText = lines.length;
        }

        function processCodes() {
            const finalCodes = document.getElementById('extractedCodesArea').value;

            if (!finalCodes.trim()) {
                alert("⚠️ No hay códigos para procesar.");
                return;
            }

            if (confirm("¿Has verificado que los códigos están correctos?\nSe procederá a asignarlos.")) {
                console.log("Enviando códigos:", finalCodes);
                alert("Códigos listos para ser procesados (Lógica de guardado pendiente de tu sistema)");
            }
        }
        function copyToClipboard() {
            const copyText = document.getElementById("extractedCodesArea");
            copyText.select();
            copyText.setSelectionRange(0, 99999);
            navigator.clipboard.writeText(copyText.value);
            alert("Códigos copiados al portapapeles");
        }

    </script>

    <!-- MODAL DE EXTRACCIÓN -->
    <div id="extractionModal" class="modal-overlay" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>⚙️ Configuración de Extracción</h3>
                <button onclick="closeExtractionModal()" class="close-btn">&times;</button>
            </div>
            <p class="modal-desc">Define cómo quieres que el sistema busque los códigos en el PDF.</p>

            <form id="extractionRulesForm">
                <div class="rule-group">
                    <label class="switch-container">
                        <input type="checkbox" name="use_ocr" value="1">
                        <span class="checkmark"></span>
                        <div class="text-data">
                            <span class="title">Forzar OCR (Lento)</span>
                            <span class="subtitle">Úsalo si el PDF es una imagen o escaneado.</span>
                        </div>
                    </label>
                </div>

                <div class="rule-group">
                    <label class="switch-container">
                        <input type="checkbox" name="strict_mode" value="1" checked>
                        <span class="checkmark"></span>
                        <div class="text-data">
                            <span class="title">Modo Estricto</span>
                            <span class="subtitle">Solo códigos que cumplan formato exacto (Ej: 0320...).</span>
                        </div>
                    </label>
                </div>

                <div class="rule-group">
                    <label class="switch-container">
                        <input type="checkbox" name="clean_images" value="1" checked>
                        <span class="checkmark"></span>
                        <div class="text-data">
                            <span class="title">Ignorar Imágenes Pequeñas</span>
                            <span class="subtitle">Descarta logotipos o iconos basura.</span>
                        </div>
                    </label>
                </div>
            </form>

            <div class="modal-buttons">
                <button onclick="closeExtractionModal()" class="btn-cancel">Cancelar</button>
                <button onclick="startExtraction()" class="btn-confirm">
                    🚀 Extraer Códigos
                </button>
            </div>
        </div>
    </div>

    <!-- ÁREA DE VERIFICACIÓN -->
    <div id="verificationArea" style="display: none;" class="verification-container container card">
        <div class="warning-box">
            <div class="warning-icon">⚠️</div>
            <div class="warning-content">
                <h4>¡VERIFICACIÓN REQUERIDA!</h4>
                <p>Por favor, <strong>revisa los códigos extraídos</strong> en la lista de abajo. Puedes editarlos,
                    borrar basura o agregar faltantes manualmente antes de continuar.</p>
            </div>
        </div>

        <div class="input-group">
            <label>Códigos Detectados (Editables):</label>
            <textarea id="extractedCodesArea" rows="10" class="code-editor"></textarea>
            <div class="count-info">Total líneas: <span id="codeCount">0</span></div>
        </div>

        <div class="action-buttons">
            <button onclick="copyToClipboard()" class="btn-secondary">📋 Copiar</button>
            <button onclick="processCodes()" class="btn-success">✅ Confirmar y Asignar</button>
        </div>
    </div>

    <style>
        /* Estilos Integrados del Extractor */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 2000;
            display: flex;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(2px);
        }

        /* Conflict resolution: modal-content is already defined in existing CSS but we can override or reuse */
        #extractionModal .modal-content {
            background: white;
            padding: 25px;
            border-radius: 12px;
            width: 90%;
            max-width: 450px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            font-family: sans-serif;
            color: #333;
            /* Override dark theme text for this modal if it's white bg */
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }

        .rule-group {
            margin-bottom: 15px;
            border: 1px solid #eee;
            padding: 10px;
            border-radius: 8px;
        }

        .switch-container {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            cursor: pointer;
        }

        .text-data {
            display: flex;
            flex-direction: column;
        }

        .text-data .title {
            font-weight: bold;
            color: #333;
        }

        .text-data .subtitle {
            font-size: 0.85em;
            color: #666;
        }

        .modal-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        .btn-confirm {
            background: #2563eb;
            color: white;
            padding: 10px 20px;
            border-radius: 6px;
            border: none;
            font-weight: bold;
            cursor: pointer;
        }

        .btn-confirm:hover {
            background: #1d4ed8;
        }

        .btn-cancel {
            background: #e5e7eb;
            color: #374151;
            padding: 10px 20px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
        }

        /* Área de Verificación */
        .verification-container {
            margin-top: 30px;
            border-top: 2px dashed #ccc;
            padding-top: 20px;
            animation: slideDown 0.5s ease;
            background: #ffffff;
            /* Explicit white background if needed */
            color: #333;
        }

        /* Dark mode adjustment for verification container within the existing dark theme */
        body .verification-container {
            background: #111a2b;
            /* Match card bg */
            color: #e8eefc;
            border-top: 2px dashed #2a3550;
        }

        .warning-box {
            background-color: #fff3cd;
            border-left: 6px solid #ffc107;
            padding: 15px;
            display: flex;
            gap: 15px;
            align-items: center;
            border-radius: 4px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            color: #856404;
            /* Keep warning text readable */
        }

        .warning-icon {
            font-size: 2em;
        }

        .warning-content h4 {
            margin: 0 0 5px 0;
            color: #856404;
            font-weight: bold;
        }

        .warning-content p {
            color: #856404;
        }

        .code-editor {
            width: 100%;
            padding: 15px;
            font-family: monospace;
            font-size: 14px;
            border: 2px solid #ddd;
            border-radius: 8px;
            background: #f8fafc;
            color: #334155;
            resize: vertical;
        }

        .code-editor:focus {
            border-color: #2563eb;
            outline: none;
            background: white;
        }

        .btn-success {
            background: #16a34a;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            margin-top: 10px;
        }

        .btn-success:hover {
            background: #15803d;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .btn-secondary {
            background: #64748b;
            color: white;
            padding: 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</body>

</html>