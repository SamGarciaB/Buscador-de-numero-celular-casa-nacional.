<?php

//MÓDULO DE SEGURIDAD

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();
$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

if (isset($_POST['logout'])) {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!is_string($submittedToken) || !hash_equals($_SESSION['csrf_token'], $submittedToken)) {
        http_response_code(403);
        exit('Solicitud inválida. Recarga la página e inténtalo de nuevo.');
    }

    $_SESSION = [];
    session_destroy();
    header("Location: login.php");
    exit;
}


//CONFIGURACIÓN Y CONEXIÓN

require_once 'config.php';

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    die("<div style='background:#050505; color:#ef4444; height:100vh; display:flex; align-items:center; justify-content:center; font-family:sans-serif; text-align:center;'>
            <div>
                <h1 style='font-size:2rem; font-weight:100;'>Error de Infraestructura</h1>
                <p style='color:#666; font-size:0.8rem; text-transform:uppercase; letter-spacing:2px;'>La base de datos no responde. Contacte a soporte técnico.</p>
            </div>
         </div>");
}


//FUNCIONES DE LIMPIEZA

function formatText($value) {
    if (empty(trim($value)) || is_numeric($value)) return null;
    
    $value = mb_strtolower(trim($value), 'UTF-8');
    $resultado = mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    
  
    $acronimos = ['AT&T', 'IFT', 'MVNO', 'CDMX', 'TELCEL', 'IZZI', 'BAIT'];
    foreach ($acronimos as $acronimo) {
        $resultado = str_ireplace($acronimo, $acronimo, $resultado);
    }
    

    $patron_legal = '/\b(?:S\.?\s*[Dd]e\s*R\.?[IlL]\.?\s*[Dd]e\s*C\.?V\.?|S\.?A\.?\s*[Dd]e\s*C\.?V\.?|S\.?A\.?B\.?\s*[Dd]e\s*C\.?V\.?|S\.?A\.?P\.?I\.?\s*[Dd]e\s*C\.?V\.?)\b/i';
    $resultado = preg_replace($patron_legal, '', $resultado);
    
    return preg_replace('/[,.\s]+$/', '', trim($resultado));
}


//LÓGICA DE BÚSQUEDA (SQL JOIN)

function identificarOrigen($numero, $pdo) {
    $numero = preg_replace('/[^0-9]/', '', $numero);
    if (strlen($numero) !== 10) return null;

    $ladas = [
        '55' => 'CDMX', '56' => 'CDMX', '33' => 'Guadalajara', '81' => 'Monterrey', 
        '222' => 'Puebla', '442' => 'Querétaro', '664' => 'Tijuana', '667' => 'Culiacán', 
        '998' => 'Cancún', '999' => 'Mérida'
    ];
    $pref3 = substr($numero, 0, 3);
    $pref2 = substr($numero, 0, 2);
    $ciudad_mapa = $ladas[$pref3] ?? ($ladas[$pref2] ?? 'México (Interior)');

    try {
        $sql = "SELECT p.*, o.nombre_comercial 
                FROM pnn_datos p 
                LEFT JOIN operadores o ON p.empresa = o.razon_social 
                WHERE :num BETWEEN p.numeracion_inicial AND p.numeracion_final 
                LIMIT 1";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['num' => $numero]);
        $res = $stmt->fetch();

        if ($res) {
            if (!empty($res['nombre_comercial'])) {
                $empresa = $res['nombre_comercial'];
            } else {
                $valEmpresa = $res['empresa'] ?? 'Operador Desconocido';
                $empresaUpper = mb_strtoupper($valEmpresa, 'UTF-8');
                
                // Condicionales aplanados para mayor velocidad de lectura
                if (strpos($empresaUpper, 'RADIOMOVIL') !== false) $empresa = 'Telcel';
                elseif (strpos($empresaUpper, 'PEGASO') !== false) $empresa = 'Movistar';
                elseif (strpos($empresaUpper, 'NEXTEL') !== false || strpos($empresaUpper, 'IUSACELL') !== false || strpos($empresaUpper, 'AT&T') !== false) $empresa = 'AT&T'; 
                elseif (strpos($empresaUpper, 'UNEFON') !== false) $empresa = 'AT&T (Unefon)';
                elseif (strpos($empresaUpper, 'TELEFONOS DE MEXICO') !== false) $empresa = 'Telmex';
                elseif (strpos($empresaUpper, 'BTV') !== false || strpos($empresaUpper, 'ALTAN') !== false || strpos($empresaUpper, 'ALTÁN') !== false) $empresa = 'Altán Redes / Bait';
                else $empresa = formatText($valEmpresa);
            }

            $ubicacion = formatText($res['poblacion']) ?? $ciudad_mapa;
            $mod = strtoupper(trim($res['modalidad'] ?? ''));
            $tipo = ($mod === 'MPP' || $mod === 'CPP') ? 'Celular' : (($mod === 'FIJO') ? 'Línea Fija' : '');
            
            return [
                'zona' => $empresa,
                'ciudad' => trim($ubicacion . ($tipo ? " ($tipo)" : "")),
                'estado' => formatText($res['estado'] ?? $res['entidad_federativa'] ?? '') ?? 'Estado no especificado',
                'fuente' => defined('OFFICIAL_SOURCE') ? OFFICIAL_SOURCE : 'Fuente local no verificada'
            ];
        }
    } catch (PDOException $e) { 
        return null; 
    }

    return ['zona' => 'Operador Nacional', 'ciudad' => $ciudad_mapa, 'estado' => 'Estado no especificado', 'fuente' => 'Mapa de Respaldo'];
}

$resultado = null;
$errorBusqueda = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['logout'])) {
    $submittedToken = $_POST['csrf_token'] ?? '';
    $numero = $_POST['numero'] ?? '';

    if (!is_string($submittedToken) || !hash_equals($_SESSION['csrf_token'], $submittedToken)) {
        $errorBusqueda = 'La sesión expiró. Recarga la página antes de consultar.';
    } elseif (!is_string($numero) || !preg_match('/^\\d{10}$/D', $numero)) {
        $errorBusqueda = 'Escribe exactamente 10 dígitos.';
    } else {
        $resultado = identificarOrigen($numero, $pdo);
        if ($resultado === null) {
            $errorBusqueda = 'No se pudo completar la consulta. Revisa la conexión de datos e inténtalo de nuevo.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(defined('APP_NAME') ? APP_NAME : 'Consulta de numeración — prototipo', ENT_QUOTES, 'UTF-8') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@100;200;400;600&display=swap');
        :root { 
            --bg-main: #050505; 
            --card-bg: rgba(17, 17, 17, 0.6); 
            --border-color: #222; 
        }
        body { 
            background-color: var(--bg-main); 
            color: #f5f5f5; 
            font-family: 'Inter', sans-serif; 
            min-height: 100vh; 
            display: flex; 
            flex-direction: column; 
            overflow-x: hidden; 
        }
        .identity-card { 
            background: var(--card-bg); 
            backdrop-filter: blur(12px); 
            border: 1px solid var(--border-color); 
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); 
        }
        .search-input:focus { 
            outline: none; 
            border-color: #444 !important; 
            background-color: #0a0a0a !important; 
        }
        @keyframes fadeIn { 
            from { opacity: 0; transform: translateY(10px); } 
            to { opacity: 1; transform: translateY(0); } 
        }
        .animate-fade-in { 
            animation: fadeIn 0.5s ease-out forwards; 
        }
    </style>
</head>
<body class="p-8">

    <aside class="mb-6 rounded-xl border border-amber-700/50 bg-amber-950/40 px-4 py-3 text-center text-xs leading-relaxed text-amber-100">
        <strong>Prototipo independiente para portafolio.</strong> No es una herramienta del IFT ni está afiliada a ese organismo. Consulta operador y zona de asignación; no identifica al titular. Usa números ficticios.
    </aside>

    <nav class="flex justify-between items-start w-full">
        <div class="flex flex-col">
            <span class="text-[10px] tracking-[0.5em] text-white font-bold uppercase">Prototipo de consulta de numeración</span>
            <span class="text-[8px] text-neutral-600 tracking-widest mt-1 uppercase">Consulta de rangos telefónicos</span>
        </div>
        <div class="flex items-center gap-4">
            <span class="text-[9px] text-neutral-500 tracking-widest uppercase">PROTOTIPO INDEPENDIENTE</span>
            <span class="text-[9px] text-green-500 font-bold tracking-widest flex items-center gap-1">
                <span class="h-1 w-1 bg-green-500 rounded-full shadow-[0_0_8px_#22c55e] animate-pulse"></span> • DEMOSTRACIÓN
            </span>
            
            <form method="POST" class="ml-4">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" name="logout" value="1" class="text-[9px] text-red-500 font-bold tracking-widest hover:text-red-400 transition-colors border border-red-500/30 px-3 py-1.5 rounded-full bg-red-500/10">
                    SALIR [ <?= htmlspecialchars($_SESSION['usuario'], ENT_QUOTES, 'UTF-8') ?> ]
                </button>
            </form>
        </div>
    </nav>

    <main class="flex-grow flex flex-col items-center justify-center">
        <div class="w-full max-w-xl text-center">
            <header class="mb-10">
                <h1 class="text-4xl font-extralight mb-2 tracking-tighter">Consulta la asignación del número</h1>
                <p class="text-neutral-500 text-xs uppercase tracking-[0.3em]">Prueba con un número ficticio de 10 dígitos; no se consulta quién es su titular.</p>
            </header>

            <form method="POST" class="relative group mb-12">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                <label for="numero" class="mb-2 block text-left text-xs text-neutral-400">Número de prueba (10 dígitos)</label>
                <input id="numero" type="text" inputmode="numeric" name="numero" placeholder="0000000000 (solo datos ficticios)"
                    aria-describedby="numero-ayuda" class="search-input w-full p-5 bg-neutral-900 border border-neutral-800 rounded-2xl text-white text-lg px-6 transition-all"
                    value="<?= htmlspecialchars(is_string($_POST['numero'] ?? null) ? $_POST['numero'] : '', ENT_QUOTES, 'UTF-8') ?>" maxlength="10" required autocomplete="off"
                    pattern="\d{10}" title="Por favor ingrese exactamente 10 dígitos">
                <p id="numero-ayuda" class="mt-2 text-left text-xs text-neutral-500">La consulta muestra operador y zona asignada; no revela quién es el titular.</p>
                <button type="submit" class="absolute right-3 top-3 bg-white text-black px-6 py-2.5 rounded-xl font-bold hover:bg-neutral-200 transition-colors">
                    Consultar
                </button>
            </form>

            <?php if ($errorBusqueda): ?>
                <p role="alert" class="mb-6 rounded-xl border border-red-700/50 bg-red-950/40 p-4 text-sm text-red-200"><?= htmlspecialchars($errorBusqueda, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>

            <?php if ($resultado): ?>
                <div class="identity-card p-8 rounded-[2rem] text-left animate-fade-in">
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                        <div>
                            <p class="text-[10px] text-neutral-500 uppercase tracking-widest mb-2">Compañía telefónica</p>
                            <p class="text-2xl font-light text-white leading-tight"><?= htmlspecialchars($resultado['zona'], ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                        <div>
                            <p class="text-[10px] text-neutral-500 uppercase tracking-widest mb-2">Localidad / modalidad</p>
                            <p class="text-2xl font-light text-white leading-tight"><?= htmlspecialchars($resultado['ciudad'], ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                        <div>
                            <p class="text-[10px] text-neutral-500 uppercase tracking-widest mb-2">Estado</p>
                            <p class="text-xl font-light text-white leading-tight"><?= htmlspecialchars($resultado['estado'], ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                    </div>
                    <div class="mt-8 pt-6 border-t border-neutral-800 flex justify-between items-center">
                        <p class="text-[9px] text-neutral-600 uppercase tracking-widest">Fuente configurada: <?= htmlspecialchars($resultado['fuente'], ENT_QUOTES, 'UTF-8') ?></p>
                        <div class="h-1.5 w-1.5 rounded-full bg-green-500 shadow-[0_0_10px_#22c55e] animate-pulse"></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <footer class="flex justify-between items-center w-full pt-4">
        <span class="text-[8px] text-neutral-700 tracking-[0.3em] uppercase">PROTOTIPO INDEPENDIENTE | SIN AFILIACIÓN AL IFT</span>
        <span class="text-[8px] text-neutral-700 tracking-[0.3em] uppercase">Versión <?= htmlspecialchars(defined('APP_VERSION') ? APP_VERSION : 'demo', ENT_QUOTES, 'UTF-8') ?> | No identifica titulares</span>
    </footer>

</body>
</html>