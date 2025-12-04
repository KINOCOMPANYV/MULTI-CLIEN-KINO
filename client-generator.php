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
        $pass = trim($_POST['edit_password'] ?? '');
        $colorP = $_POST['edit_color_primario'] ?? '#2563eb';
        $colorS = $_POST['edit_color_secundario'] ?? '#F87171';

        if ($nombre) {
            $sql = 'UPDATE _control_clientes SET nombre = ?, color_primario = ?, color_secundario = ?';
            $params = [$nombre, $colorP, $colorS];

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

// CLONAR DATOS
if ($action === 'clone' && !empty($_POST['clone_from']) && !empty($_POST['clone_to'])) {
    try {
        $from = sanitize_code($_POST['clone_from']);
        $to = sanitize_code($_POST['clone_to']);

        // Verificar que ambos clientes existen
        $check = $db->prepare('SELECT codigo FROM _control_clientes WHERE codigo IN (?, ?)');
        $check->execute([$from, $to]);
        if ($check->rowCount() != 2)
            throw new Exception('Ambos clientes deben existir.');

        // Clonar documentos
        $db->exec("INSERT INTO `{$to}_documents` (name, date, path, codigos_extraidos) 
                   SELECT name, date, REPLACE(path, '{$from}/', '{$to}/'), codigos_extraidos 
                   FROM `{$from}_documents`");

        // Mapear IDs y clonar códigos
        $docs = $db->query("SELECT d1.id AS old_id, d2.id AS new_id 
                            FROM `{$from}_documents` d1 
                            JOIN `{$to}_documents` d2 ON d1.name = d2.name AND d1.date = d2.date")->fetchAll();

        foreach ($docs as $d) {
            $db->prepare("INSERT INTO `{$to}_codes` (document_id, code) 
                          SELECT ?, code FROM `{$from}_codes` WHERE document_id = ?")
                ->execute([$d['new_id'], $d['old_id']]);
        }

        // Copiar archivos
        $srcDir = __DIR__ . '/uploads/' . $from;
        $dstDir = __DIR__ . '/uploads/' . $to;
        if (is_dir($srcDir)) {
            if (!is_dir($dstDir))
                mkdir($dstDir, 0777, true);
            foreach (scandir($srcDir) as $file) {
                if ($file !== '.' && $file !== '..') {
                    copy($srcDir . '/' . $file, $dstDir . '/' . $file);
                }
            }
        }

        $ok = "✅ Datos clonados de '{$from}' a '{$to}'.";
    } catch (Exception $e) {
        $err = '❌ ' . $e->getMessage();
    }
}

// CREAR NUEVO CLIENTE
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $codigo = sanitize_code($codigoInput);
        $nombre = trim($nombreInput);
        $pass = trim($_POST['password'] ?? '');

        if ($codigo === '' || $nombre === '' || $pass === '') {
            throw new Exception('Faltan campos requeridos.');
        }

        $st = $db->prepare('SELECT 1 FROM _control_clientes WHERE codigo = ?');
        $st->execute([$codigo]);
        if ($st->fetch())
            throw new Exception('El código ya existe.');

        $hash = password_hash($pass, PASSWORD_BCRYPT);
        $db->prepare('INSERT INTO _control_clientes (codigo, nombre, password_hash, color_primario, color_secundario, activo) VALUES (?, ?, ?, ?, ?, 1)')
            ->execute([$codigo, $nombre, $hash, $colorPrimario, $colorSecundario]);

        $db->exec("CREATE TABLE IF NOT EXISTS `{$codigo}_documents` (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            date DATE NOT NULL,
            path VARCHAR(255) NOT NULL,
            codigos_extraidos TEXT DEFAULT NULL,
            INDEX idx_date (date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE IF NOT EXISTS `{$codigo}_codes` (
            id INT AUTO_INCREMENT PRIMARY KEY,
            document_id INT NOT NULL,
            code VARCHAR(100) NOT NULL,
            INDEX idx_code (code),
            FOREIGN KEY (document_id) REFERENCES `{$codigo}_documents` (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $uploadsDir = __DIR__ . '/uploads/' . $codigo;
        if (!is_dir($uploadsDir))
            mkdir($uploadsDir, 0777, true);

        $ok = "✅ Cliente creado.<br><strong>Código:</strong> {$codigo}<br><strong>Contraseña:</strong> {$pass}<br><a href='login.php' style='color:#fff;text-decoration:underline;'>Ir al Login</a>";
    } catch (Exception $e) {
        $err = '❌ ' . $e->getMessage();
    }
}

// Obtener lista de clientes
$clients = [];
try {
    $clients = $db->query("SELECT codigo, nombre, color_primario, color_secundario, activo, fecha_creacion FROM _control_clientes ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
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
                <input type="hidden" name="action" value="create">
                <div class="row">
                    <div>
                        <label>Código (minúsculas)</label>
                        <input name="codigo" pattern="[a-z0-9_]+" required placeholder="cliente1">
                    </div>
                    <div>
                        <label>Nombre</label>
                        <input name="nombre" required placeholder="Empresa XYZ">
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
                <button type="submit">✅ Crear Cliente</button>
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
                                            onclick="openEdit('<?= $c['codigo'] ?>', '<?= htmlspecialchars($c['nombre']) ?>', '<?= $c['color_primario'] ?? '#2563eb' ?>', '<?= $c['color_secundario'] ?? '#F87171' ?>')"
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
        function openEdit(codigo, nombre, colorP, colorS) {
            document.getElementById('edit_codigo').value = codigo;
            document.getElementById('edit_nombre').value = nombre;
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