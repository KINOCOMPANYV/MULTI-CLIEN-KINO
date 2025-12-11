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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <title>Acceso al Buscador</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --color-primary: #6366f1;
            --color-primary-dark: #4f46e5;
            --color-accent: #06b6d4;
            --glass-bg: rgba(255, 255, 255, 0.08);
            --glass-border: rgba(255, 255, 255, 0.12);
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --gradient-1: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-3: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --gradient-dark: linear-gradient(180deg, #0f172a 0%, #1e1b4b 50%, #0f172a 100%);
            --shadow-glow: 0 0 40px rgba(99, 102, 241, 0.3);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--gradient-dark);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: var(--text-primary);
            overflow: hidden;
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background:
                radial-gradient(circle at 20% 80%, rgba(99, 102, 241, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(6, 182, 212, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(139, 92, 246, 0.1) 0%, transparent 40%);
            pointer-events: none;
            z-index: 0;
        }

        .card {
            position: relative;
            z-index: 1;
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            padding: 3rem;
            border-radius: 32px;
            box-shadow: var(--shadow-glow), 0 25px 50px rgba(0, 0, 0, 0.5);
            width: 100%;
            max-width: 450px;
            margin: 1rem;
            animation: cardEntrance 0.6s ease;
        }

        @keyframes cardEntrance {
            from {
                transform: scale(0.9) translateY(20px);
                opacity: 0;
            }

            to {
                transform: scale(1) translateY(0);
                opacity: 1;
            }
        }

        .logo-container {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo-icon {
            width: 80px;
            height: 80px;
            background: var(--gradient-1);
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 10px 30px rgba(99, 102, 241, 0.4);
        }

        h1 {
            font-size: 1.75rem;
            font-weight: 800;
            background: linear-gradient(135deg, #fff 0%, var(--color-accent) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-align: center;
            margin-bottom: 0.5rem;
        }

        .subtitle {
            text-align: center;
            color: var(--text-secondary);
            margin-bottom: 2rem;
        }

        label {
            display: block;
            margin-top: 1.5rem;
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        select,
        input {
            width: 100%;
            padding: 1rem 1.25rem;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            color: var(--text-primary);
            font-size: 1rem;
            font-family: inherit;
            transition: var(--transition);
            outline: none;
        }

        select {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2394a3b8'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 1.25rem;
            padding-right: 3rem;
        }

        select option {
            background: #1e293b;
            color: var(--text-primary);
        }

        input:focus,
        select:focus {
            border-color: var(--color-accent);
            box-shadow: 0 0 0 3px rgba(6, 182, 212, 0.2);
        }

        input::placeholder {
            color: var(--text-secondary);
        }

        button {
            width: 100%;
            margin-top: 2rem;
            padding: 1rem;
            border: none;
            border-radius: 12px;
            background: var(--gradient-1);
            color: #fff;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
        }

        button::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transform: translateX(-100%);
            transition: transform 0.5s;
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(99, 102, 241, 0.5);
        }

        button:hover::before {
            transform: translateX(100%);
        }

        button:active {
            transform: translateY(0);
        }

        .error {
            margin-top: 1.5rem;
            padding: 1rem;
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 12px;
            color: #fca5a5;
            text-align: center;
            animation: shake 0.5s ease;
        }

        @keyframes shake {

            0%,
            100% {
                transform: translateX(0);
            }

            25% {
                transform: translateX(-10px);
            }

            75% {
                transform: translateX(10px);
            }
        }

        .logout {
            text-align: center;
            margin-top: 1.5rem;
        }

        .logout a {
            color: var(--color-accent);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }

        .logout a:hover {
            color: #22d3ee;
            text-decoration: underline;
        }

        .footer {
            position: fixed;
            bottom: 1.5rem;
            text-align: center;
            color: var(--text-secondary);
            font-size: 0.875rem;
            z-index: 1;
        }

        .footer strong {
            background: var(--gradient-3);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 700;
        }

        /* Particles */
        .particle {
            position: fixed;
            width: 4px;
            height: 4px;
            background: var(--color-accent);
            border-radius: 50%;
            opacity: 0.3;
            pointer-events: none;
            animation: float 15s infinite;
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(100vh) rotate(0deg);
                opacity: 0;
            }

            10% {
                opacity: 0.3;
            }

            90% {
                opacity: 0.3;
            }

            100% {
                transform: translateY(-100vh) rotate(720deg);
                opacity: 0;
            }
        }

        /* Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.2);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--color-primary);
            border-radius: 4px;
        }
    </style>
</head>

<body>
    <!-- Particles -->
    <div id="particles"></div>

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

        // Create particles
        (function () {
            const container = document.getElementById('particles');
            for (let i = 0; i < 12; i++) {
                const p = document.createElement('div');
                p.className = 'particle';
                p.style.left = Math.random() * 100 + '%';
                p.style.animationDelay = Math.random() * 15 + 's';
                p.style.animationDuration = (15 + Math.random() * 10) + 's';
                container.appendChild(p);
            }
        })();
    </script>
</body>

</html>