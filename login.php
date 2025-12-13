<?php
session_start();
error_log('✅ [INDEX] index.php cargado');
require __DIR__ . '/config.php';

function has_column(PDO $db, string $table, string $column): bool
{
    $stmt = $db->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $stmt->execute([$table, $column]);
    return (bool) $stmt->fetchColumn();
}

$hasRolColumn = false;
try {
    $hasRolColumn = has_column($db, '_control_clientes', 'rol');
} catch (PDOException $e) {
    $hasRolColumn = false;
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo = trim($_POST['cliente'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($codigo === '' || $password === '') {
        $error = 'Debe seleccionar un cliente y escribir la contraseña.';
    } else {
        try {
            $select = 'SELECT codigo, nombre, password_hash, activo' . ($hasRolColumn ? ', rol' : '') . ' FROM _control_clientes WHERE codigo = ? LIMIT 1';
            $stmt = $db->prepare($select);
            $stmt->execute([$codigo]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row || !(int) $row['activo'] || !password_verify($password, $row['password_hash'])) {
                $error = 'Credenciales inválidas.';
            } else {
                $_SESSION['cliente'] = $row['codigo'];
                $rol = $hasRolColumn && isset($row['rol']) && $row['rol'] ? $row['rol'] : 'cliente';
                $_SESSION['cliente_rol'] = $rol;

                if ($rol === 'admin') {
                    header('Location: client-generator.php');
                } else {
                    header('Location: index.html?c=' . urlencode($row['codigo']));
                }
                exit;
            }
        } catch (PDOException $e) {
            $error = 'Error en la autenticación: ' . $e->getMessage();
        }
    }
}

try {
    $clientesStmt = $db->query('SELECT codigo, nombre FROM _control_clientes WHERE activo = 1 ORDER BY nombre');
    $clientes = $clientesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $clientes = [];
    $error = $error ?: 'No se pudieron cargar los clientes: ' . $e->getMessage();
}
error_log('✅ [INDEX] Renderizando vista');
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <title>Acceso al Buscador</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            /* Neobrutalismo: Alto contraste, colores vibrantes */
            --primary: #facc15;
            --primary-glow: rgba(250, 204, 21, 0.4);
            --accent: #00f0ff;
            --accent-glow: rgba(0, 240, 255, 0.3);
            --success: #22c55e;
            --warning: #fb923c;
            --danger: #f43f5e;
            --bg-card: #0a0a0a;
            --border-hard: #333;
            --border-accent: var(--primary);
            --text: #ffffff;
            --text-muted: #a1a1aa;
            --dark-1: #000000;
            --dark-2: #0a0a0a;
            --dark-3: #171717;
            --font-display: 'Inter', -apple-system, sans-serif;
            --font-body: 'Inter', -apple-system, sans-serif;
            --ease-bounce: cubic-bezier(0.68, -0.55, 0.265, 1.55);
            --ease-smooth: cubic-bezier(0.4, 0, 0.2, 1);
            --shadow-brutal: 8px 8px 0 var(--primary);
            --shadow-brutal-sm: 4px 4px 0 var(--primary);
        }

        body {
            font-family: var(--font-body);
            background: var(--dark-1);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: var(--text);
            overflow-x: hidden;
            position: relative;
        }

        /* Ambient Background */
        .ambient {
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 0;
            background:
                radial-gradient(ellipse 80% 50% at 20% 40%, rgba(250, 204, 21, 0.1), transparent),
                radial-gradient(ellipse 60% 40% at 80% 60%, rgba(0, 240, 255, 0.1), transparent);
        }

        .orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(100px);
            pointer-events: none;
            z-index: 0;
            animation: float 20s ease-in-out infinite;
            opacity: 0.5;
        }

        .orb-1 {
            width: 500px;
            height: 500px;
            background: var(--primary);
            top: -200px;
            left: -200px;
        }

        .orb-2 {
            width: 400px;
            height: 400px;
            background: var(--accent);
            bottom: -100px;
            right: -100px;
            animation-delay: -5s;
        }

        @keyframes float {

            0%,
            100% {
                transform: translate(0, 0) scale(1);
            }

            50% {
                transform: translate(30px, -30px) scale(1.1);
            }
        }

        /* Neobrutalist Card */
        .card {
            position: relative;
            z-index: 1;
            background: var(--bg-card);
            border: 4px solid var(--border-hard);
            box-shadow: var(--shadow-brutal);
            padding: 3rem;
            width: 100%;
            max-width: 480px;
            margin: 1rem;
            animation: cardIn 0.6s var(--ease-bounce);
            transition: all 0.2s ease;
        }

        .card:hover {
            border-color: var(--primary);
            transform: translate(-4px, -4px);
            box-shadow: 12px 12px 0 var(--primary);
        }

        @keyframes cardIn {
            from {
                opacity: 0;
                transform: translateY(40px) scale(0.9);
            }

            to {
                opacity: 1;
                transform: none;
            }
        }

        .logo-container {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .logo-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            display: inline-block;
            animation: bounceIcon 2s infinite;
        }

        @keyframes bounceIcon {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-10px);
            }
        }

        h1 {
            font-family: var(--font-display);
            font-size: 3rem;
            font-weight: 900;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: 2px;
            text-shadow: 4px 4px 0 var(--dark-1);
            margin-bottom: 0.5rem;
            line-height: 1;
        }

        .subtitle {
            text-align: center;
            color: var(--text-muted);
            margin-bottom: 2rem;
            font-family: monospace;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        label {
            display: block;
            margin-top: 1.5rem;
            font-weight: 700;
            color: var(--text);
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        select,
        input {
            width: 100%;
            padding: 1rem 1.25rem;
            background: var(--dark-1);
            border: 3px solid var(--border-hard);
            border-radius: 0;
            color: var(--text);
            font-size: 1.1rem;
            font-family: monospace;
            outline: none;
            transition: all 0.15s ease;
            box-shadow: 4px 4px 0 rgba(255, 255, 255, 0.05);
        }

        select:focus,
        input:focus {
            border-color: var(--primary);
            background: #000;
            box-shadow: 6px 6px 0 var(--primary);
            transform: translate(-2px, -2px);
        }

        button {
            width: 100%;
            margin-top: 2.5rem;
            padding: 1.25rem;
            border: 3px solid var(--border-hard);
            border-radius: 0;
            background: var(--primary);
            color: var(--dark-1);
            font-size: 1.2rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 2px;
            cursor: pointer;
            transition: all 0.15s ease;
            box-shadow: var(--shadow-brutal-sm);
        }

        button:hover {
            background: #fde047;
            transform: translate(-2px, -2px);
            box-shadow: 6px 6px 0 #fff;
            border-color: #000;
        }

        button:active {
            transform: translate(2px, 2px);
            box-shadow: none;
        }

        .error {
            margin-top: 1.5rem;
            padding: 1rem;
            background: var(--danger);
            color: white;
            border: 3px solid #000;
            font-weight: 700;
            text-transform: uppercase;
            text-align: center;
            box-shadow: 4px 4px 0 #fff;
        }

        .logout {
            text-align: center;
            margin-top: 2rem;
        }

        .logout a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 2px solid transparent;
            transition: all 0.2s;
        }

        .logout a:hover {
            color: #fff;
            border-bottom-color: var(--accent);
        }

        .footer {
            position: fixed;
            bottom: 1.5rem;
            text-align: center;
            color: var(--text-muted);
            font-size: 0.8rem;
            font-family: monospace;
            text-transform: uppercase;
        }

        .footer strong {
            color: var(--primary);
        }
    </style>
</head>

<body>
    <!-- Ambient Background -->
    <div class="ambient"></div>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>

    <div class="card">
        <div class="logo-container">
            <div class="logo-icon">🔐</div>
            <h1>Bienvenido</h1>
            <p class="subtitle">Selecciona tu cliente para continuar</p>
        </div>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form method="post">
            <label for="cliente">Cliente</label>
            <select name="cliente" id="cliente" required>
                <option value="">Seleccione un cliente...</option>
                <?php foreach ($clientes as $cli): ?>
                    <option value="<?php echo htmlspecialchars($cli['codigo'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo (($_POST['cliente'] ?? '') === $cli['codigo']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cli['nombre'] . ' (' . $cli['codigo'] . ')', ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="password">Contraseña</label>
            <input type="password" name="password" id="password" required placeholder="Ingresa tu contraseña">

            <button type="submit">🚀 Ingresar</button>
        </form>

        <?php if (isset($_SESSION['cliente'])): ?>
            <div class="logout">
                <a href="?logout=1">Cerrar sesión de
                    <?php echo htmlspecialchars($_SESSION['cliente'], ENT_QUOTES, 'UTF-8'); ?></a>
            </div>
        <?php endif; ?>
    </div>

    <footer class="footer">
        <p>Elaborado por <strong>KINO GENIUS</strong></p>
    </footer>

    <script>
        // Code protection
        document.addEventListener('contextmenu', e => e.preventDefault());
        document.addEventListener('keydown', e => {
            if (e.key === 'F12' || (e.ctrlKey && e.shiftKey && ['I', 'J', 'C'].includes(e.key)) || (e.ctrlKey && e.key === 'u')) {
                e.preventDefault();
            }
        });
    </script>
</body>

</html>