<?php
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();
require_once 'config.php';
$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));

// Si ya tiene sesión, lo mandamos al buscador
if (isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!is_string($submittedToken) || !hash_equals($_SESSION['csrf_token'], $submittedToken)) {
        $error = 'La sesión expiró. Recarga la página e inténtalo de nuevo.';
    } else {
        $usuario = is_string($_POST['usuario'] ?? null) ? trim($_POST['usuario']) : '';
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE usuario = :user LIMIT 1");
            $stmt->execute(['user' => $usuario]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['usuario'] = $user['usuario'];
                $_SESSION['rol'] = $user['rol'];
                header("Location: index.php");
                exit;
            }

            // Añade una pausa ante credenciales inválidas.
            sleep(2);
            $error = "Credenciales incorrectas. Acceso denegado.";
        } catch (PDOException $e) {
            $error = "Error de conexión al servidor.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | <?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@100;200;400;600&display=swap');
        body { background-color: #050505; color: #f5f5f5; font-family: 'Inter', sans-serif; height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-card { background: rgba(17, 17, 17, 0.6); backdrop-filter: blur(12px); border: 1px solid #222; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); }
        input:focus { outline: none; border-color: #444 !important; background-color: #0a0a0a !important; }
    </style>
</head>
<body>

    <div class="w-full max-w-sm">
        <div class="text-center mb-8">
            <h1 class="text-[10px] tracking-[0.5em] text-white font-bold uppercase"><?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="text-[8px] text-neutral-600 tracking-widest mt-2 uppercase">Acceso de demostración</p>
            <p class="mt-3 text-xs leading-relaxed text-amber-200">Prototipo independiente de portafolio. No afiliado al IFT. No permite identificar titulares de números.</p>
        </div>

        <div class="login-card p-8 rounded-[2rem]">
            <?php if ($error): ?>
                <div role="alert" aria-live="polite" class="bg-red-900/50 border border-red-500 text-red-200 text-xs p-3 rounded-lg mb-6 text-center">
                    <?= $error ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                <div class="mb-5">
                    <label class="block text-[10px] text-neutral-500 uppercase tracking-widest mb-2">Usuario Operador</label>
                    <input type="text" name="usuario" maxlength="80" required autocomplete="username"
                        class="w-full p-3 bg-neutral-900 border border-neutral-800 rounded-xl text-white text-sm transition-all">
                </div>
                
                <div class="mb-8">
                    <label class="block text-[10px] text-neutral-500 uppercase tracking-widest mb-2">Contraseña</label>
                    <input type="password" name="password" required autocomplete="current-password" 
                        class="w-full p-3 bg-neutral-900 border border-neutral-800 rounded-xl text-white text-sm transition-all">
                </div>

                <button type="submit" class="w-full bg-white text-black py-3 rounded-xl font-bold hover:bg-neutral-200 transition-colors text-sm">
                    Autenticar
                </button>
            </form>
        </div>
    </div>

</body>
</html>