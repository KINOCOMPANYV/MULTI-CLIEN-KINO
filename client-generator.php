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
        $titulo = trim($_POST['edit_titulo_app'] ?? 'KINO COMPANY SAS V1'); // <--- NUEVO
        $pass = trim($_POST['edit_password'] ?? '');
        $colorP = $_POST['edit_color_primario'] ?? '#2563eb';
        $colorS = $_POST['edit_color_secundario'] ?? '#F87171';

        if ($nombre) {
            // Agregamos titulo_app a la consulta
            $sql = 'UPDATE _control_clientes SET nombre = ?, titulo_app = ?, color_primario = ?, color_secundario = ?';
            $params = [$nombre, $titulo, $colorP, $colorS];

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
        $titulo = trim($_POST['titulo_app'] ?? 'KINO COMPANY SAS V1'); // <--- Capturar título
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
        $db->prepare('INSERT INTO _control_clientes (codigo, nombre, titulo_app, password_hash, color_primario, color_secundario, activo) VALUES (?, ?, ?, ?, ?, ?, 1)')
            ->execute([$codigo, $nombre, $titulo, $hash, $colorPrimario, $colorSecundario]);

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
        $titulo = trim($_POST['titulo_app'] ?? 'KINO COMPANY SAS V1'); // <--- Capturar título
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
        $db->prepare('INSERT INTO _control_clientes (codigo, nombre, titulo_app, password_hash, color_primario, color_secundario, activo) VALUES (?, ?, ?, ?, ?, ?, 1)')
            ->execute([$codigo, $nombre, $titulo, $hash, $colorPrimario, $colorSecundario]);

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
    <title>Gestión de Clientes</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: system-ui, sans-serif;
            background: linear-gradient(135deg, #0b1220 0%, #1a2332 100%);
            color: #e8eefc;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
        }

        .card {
            background: #111a2b;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
            margin-bottom: 20px;
        }

        h2 {
            text-align: center;
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-top: 12px;
            color: #9fb3ce;
            font-weight: 500;
        }

        input,
        select {
            width: 100%;
            padding: 10px;
            margin-top: 4px;
            border: 1px solid #2a3550;
            border-radius: 8px;
            background: #0e1626;
            color: #e8eefc;
            font-size: 1rem;
        }

        input[type="color"] {
            height: 40px;
            padding: 2px;
            cursor: pointer;
        }

        button {
            margin-top: 16px;
            width: 100%;
            padding: 12px;
            border: 0;
            border-radius: 8px;
            background: #2a6df6;
            color: #fff;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s;
        }

        button:hover {
            filter: brightness(1.15);
        }

        .btn-danger {
            background: #dc2626;
        }

        .btn-warning {
            background: #d97706;
        }

        .btn-success {
            background: #16a34a;
        }

        .ok {
            background: #0f5132;
            color: #d1f3e0;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 16px;
        }

        .err {
            background: #5c1a1a;
            color: #ffe3e3;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 16px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }

        th,
        td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #2a3550;
        }

        th {
            color: #9fb3ce;
        }

        .color-dot {
            display: inline-block;
            width: 20px;
            height: 20px;
            border-radius: 4px;
            vertical-align: middle;
            border: 1px solid #fff;
        }

        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .actions button {
            width: auto;
            padding: 6px 12px;
            font-size: 0.85rem;
            margin: 0;
        }

        a.link {
            color: #2a6df6;
            text-decoration: none;
        }

        .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
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
            background: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: #111a2b;
            padding: 24px;
            border-radius: 12px;
            max-width: 400px;
            width: 90%;
        }

        .modal h3 {
            margin-bottom: 16px;
        }

        .close-btn {
            background: #374151;
            margin-top: 8px;
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Crear Cliente -->
        <div class="card">
            <h2>🏢 Crear Nuevo Cliente</h2>
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
                        <label>Contraseña</label>
                        <input name="password" type="password" required minlength="4">
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
                                            onclick="openEdit('<?= $c['codigo'] ?>', '<?= htmlspecialchars($c['nombre']) ?>', '<?= htmlspecialchars($c['titulo_app'] ?? '') ?>', '<?= $c['color_primario'] ?? '#2563eb' ?>', '<?= $c['color_secundario'] ?? '#F87171' ?>')"
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
        function openEdit(codigo, nombre, titulo, colorP, colorS) {
            document.getElementById('edit_codigo').value = codigo;
            document.getElementById('edit_nombre').value = nombre;
            document.getElementById('edit_titulo_app').value = titulo;
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
    </script>
</body>

</html>