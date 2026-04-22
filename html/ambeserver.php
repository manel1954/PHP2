<?php
$baseDir = "/home/pi/AMBE_SERVER";

$binary  = $baseDir . "/AMBEserver";
$iniFile = $baseDir . "/AMBEserver.ini";
$logFile = $baseDir . "/ambe.log";
$pidFile = $baseDir . "/ambe.pid";

$cronCmd = "@reboot cd $baseDir && $binary -s \$(grep velocidad $iniFile | cut -d= -f2) -i \$(grep puerto= $iniFile | cut -d= -f2) -p \$(grep puertonet $iniFile | cut -d= -f2) >> $logFile 2>&1";

/* =========================
   FUNCIONES
   ========================= */
function loadConfig($iniFile) {
    clearstatcache(true, $iniFile);
    return parse_ini_file($iniFile);
}

function getAutoStatus() {
    $cron = shell_exec("crontab -l 2>/dev/null");
    return strpos($cron, "AMBEserver") !== false;
}

function logMsg($logFile, $msg) {
    $line = "[" . date("Y-m-d H:i:s") . "] " . $msg . "\n";
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

/* =========================
   ACCIONES AJAX
   ========================= */
if (isset($_GET['action'])) {
    $action = $_GET['action'];

    /* --- LOG --- */
    if ($action === 'log') {
        header('Content-Type: text/plain');
        system("tail -n 50 " . escapeshellarg($logFile));
        exit;
    }

    /* --- STATUS --- */
    if ($action === 'status') {
        header('Content-Type: application/json');
        echo json_encode([
            'running' => file_exists($pidFile),
            'auto'    => getAutoStatus(),
        ]);
        exit;
    }

    /* --- START --- */
    if ($action === 'start') {
        header('Content-Type: application/json');

        if (file_exists($pidFile)) {
            echo json_encode(['ok' => false, 'msg' => 'Ya está en ejecución']);
            exit;
        }

        $config = loadConfig($iniFile);
        logMsg($logFile, ">>> START solicitado");

        $cmd = sprintf(
            "%s -s %s -i %s -p %s >> %s 2>&1 & echo $!",
            escapeshellcmd($binary),
            escapeshellarg($config['velocidad']),
            escapeshellarg($config['puerto']),
            escapeshellarg($config['puertonet']),
            escapeshellarg($logFile)
        );

        $pid = shell_exec($cmd);

        if ($pid) {
            file_put_contents($pidFile, trim($pid));
            logMsg($logFile, ">>> AMBEserver iniciado con PID " . trim($pid));
            echo json_encode(['ok' => true]);
        } else {
            logMsg($logFile, ">>> ERROR: no se pudo iniciar AMBEserver");
            echo json_encode(['ok' => false, 'msg' => 'Error al iniciar']);
        }
        exit;
    }

    /* --- STOP --- */
    if ($action === 'stop') {
        header('Content-Type: application/json');

        if (!file_exists($pidFile)) {
            logMsg($logFile, ">>> STOP: no hay proceso en ejecución");
            echo json_encode(['ok' => false, 'msg' => 'No está en ejecución']);
            exit;
        }

        $pid = trim(file_get_contents($pidFile));
        logMsg($logFile, ">>> STOP solicitado — matando PID $pid");
        shell_exec("kill $pid 2>&1");
        sleep(1);

        $still = trim(shell_exec("ps -p $pid -o pid= 2>/dev/null"));
        if ($still) {
            shell_exec("kill -9 $pid 2>&1");
            logMsg($logFile, ">>> SIGKILL enviado a PID $pid");
        }

        unlink($pidFile);
        logMsg($logFile, ">>> AMBEserver detenido correctamente");
        echo json_encode(['ok' => true]);
        exit;
    }

    /* --- RESTART --- */
    if ($action === 'restart') {
        header('Content-Type: application/json');

        logMsg($logFile, ">>> RESTART solicitado");

        if (file_exists($pidFile)) {
            $pid = trim(file_get_contents($pidFile));
            logMsg($logFile, ">>> Deteniendo PID $pid...");
            shell_exec("kill $pid 2>&1");
            sleep(1);

            $still = trim(shell_exec("ps -p $pid -o pid= 2>/dev/null"));
            if ($still) {
                shell_exec("kill -9 $pid 2>&1");
                logMsg($logFile, ">>> SIGKILL enviado a PID $pid");
            }

            unlink($pidFile);
            logMsg($logFile, ">>> Proceso anterior detenido");
        }

        $config = loadConfig($iniFile);

        $cmd = sprintf(
            "%s -s %s -i %s -p %s >> %s 2>&1 & echo $!",
            escapeshellcmd($binary),
            escapeshellarg($config['velocidad']),
            escapeshellarg($config['puerto']),
            escapeshellarg($config['puertonet']),
            escapeshellarg($logFile)
        );

        $pid = shell_exec($cmd);

        if ($pid) {
            file_put_contents($pidFile, trim($pid));
            logMsg($logFile, ">>> AMBEserver reiniciado con PID " . trim($pid));
            echo json_encode(['ok' => true]);
        } else {
            logMsg($logFile, ">>> ERROR: no se pudo reiniciar AMBEserver");
            echo json_encode(['ok' => false, 'msg' => 'Error al reiniciar']);
        }
        exit;
    }

    /* --- CLEAR LOG --- */
    if ($action === 'clear') {
        header('Content-Type: application/json');
        file_put_contents($logFile, "");
        echo json_encode(['ok' => true]);
        exit;
    }

    /* --- AUTO ON --- */
    if ($action === 'enable_auto') {
        header('Content-Type: application/json');
        shell_exec("(crontab -l 2>/dev/null; echo \"$cronCmd\") | crontab -");
        logMsg($logFile, ">>> Autoarranque ACTIVADO");
        echo json_encode(['ok' => true]);
        exit;
    }

    /* --- AUTO OFF --- */
    if ($action === 'disable_auto') {
        header('Content-Type: application/json');
        shell_exec("crontab -l 2>/dev/null | grep -v 'AMBEserver' | crontab -");
        logMsg($logFile, ">>> Autoarranque DESACTIVADO");
        echo json_encode(['ok' => true]);
        exit;
    }
}

/* =========================
   GUARDAR INI (formulario normal)
   ========================= */
if (isset($_POST['save'])) {
    $ini =
        "velocidad=" . trim($_POST['velocidad']) . "\n" .
        "puerto="    . trim($_POST['puerto'])    . "\n" .
        "puertonet=" . trim($_POST['puertonet']) . "\n";

    file_put_contents($iniFile, $ini, LOCK_EX);
    clearstatcache(true, $iniFile);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

/* =========================
   LEER CONFIG PARA HTML
   ========================= */
$config      = loadConfig($iniFile);
$speed       = $config['velocidad'] ?? 460800;
$tty         = $config['puerto']    ?? "/dev/ttyUSB0";
$net         = $config['puertonet'] ?? 3000;
$running     = file_exists($pidFile);
$autoEnabled = getAutoStatus();
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>AMBE Server</title>

<link rel="icon" type="image/png" href="https://cdn-icons-png.flaticon.com/512/2885/2885417.png">

<style>
body { background:#0d0d0d; color:#ddd; font-family:Arial; }
.card { background:#1a1a1a; padding:15px; margin:10px; border-radius:10px; }
input { width:100%; padding:8px; margin:5px 0; background:#000; color:#0f0; border:1px solid #333; }
button { padding:10px; margin:5px; cursor:pointer; }
button:disabled { opacity:0.4; cursor:not-allowed; }
.status { font-weight:bold; }
.running  { color: lime; }
.stopped  { color: red; }
.auto-on  { color: cyan; }
.auto-off { color: orange; }
#btnAuto { transition: background 0.2s, border-color 0.2s; }
#btnAuto.auto-is-on  { border: 2px solid red;  color: #ff4444; }
#btnAuto.auto-is-off { border: 2px solid cyan; color: #00ffcc; }
pre#logbox { background:black; color:#0f0; padding:10px; height:300px; overflow:auto;
             font-family: monospace; font-size:13px; white-space:pre-wrap; word-break:break-all; }
a { color:#00ffcc; }
</style>
</head>

<body>

<div class="card">
    <h2>AMBE Server</h2>

    <p>Estado:
        <span id="statusLabel" class="status <?= $running ? 'running' : 'stopped' ?>">
            <?= $running ? "RUNNING" : "STOPPED" ?>
        </span>
    </p>

    <p>Autoarranque:
        <span id="autoLabel" class="status <?= $autoEnabled ? 'auto-on' : 'auto-off' ?>">
            <?= $autoEnabled ? "ON" : "OFF" ?>
        </span>
    </p>

    <div>
        <button onclick="doAction('start')">▶ Start</button>
        <button onclick="doAction('restart')">🔄 Restart</button>
        <button onclick="doAction('stop')">⏹ Stop</button>
        <button onclick="doAction('clear')">🧹 Clear Log</button>
        <button id="btnAuto" class="<?= $autoEnabled ? 'auto-is-on' : 'auto-is-off' ?>" onclick="toggleAuto()">
            <?= $autoEnabled ? '❌ Auto OFF' : '⚡ Auto ON' ?>
        </button>
    </div>
</div>

<div class="card">
    <h3>Configuración (AMBEserver.ini)</h3>

    <form method="post">
        <label>Velocidad</label>
        <input name="velocidad" value="<?= htmlspecialchars($speed) ?>">

        <label>Puerto</label>
        <input name="puerto" value="<?= htmlspecialchars($tty) ?>">

        <label>Puerto NET</label>
        <input name="puertonet" value="<?= htmlspecialchars($net) ?>">

        <button name="save">💾 Guardar INI</button>
    </form>
</div>

<div class="card">
    <h3>Log (últimas líneas)</h3>
    <pre id="logbox"></pre>
</div>

<script>
/* ---- Refresco del log ---- */
function refreshLog() {
    fetch('?action=log')
        .then(r => r.text())
        .then(text => {
            const box = document.getElementById('logbox');
            box.textContent = text;
            box.scrollTop = box.scrollHeight;
        });
}

/* ---- Refresco del estado ---- */
function refreshStatus() {
    fetch('?action=status')
        .then(r => r.json())
        .then(data => {
            const sl = document.getElementById('statusLabel');
            sl.textContent = data.running ? 'RUNNING' : 'STOPPED';
            sl.className   = 'status ' + (data.running ? 'running' : 'stopped');

            const al = document.getElementById('autoLabel');
            al.textContent = data.auto ? 'ON' : 'OFF';
            al.className   = 'status ' + (data.auto ? 'auto-on' : 'auto-off');

            const btn = document.getElementById('btnAuto');
            btn.textContent = data.auto ? '❌ Auto OFF' : '⚡ Auto ON';
            btn.className   = data.auto ? 'auto-is-on' : 'auto-is-off';
        });
}

/* ---- Toggle autoarranque ---- */
function toggleAuto() {
    fetch('?action=status')
        .then(r => r.json())
        .then(data => {
            doAction(data.auto ? 'disable_auto' : 'enable_auto');
        });
}

/* ---- Ejecutar acción via AJAX ---- */
function doAction(action) {
    document.querySelectorAll('button[onclick]').forEach(b => b.disabled = true);

    fetch('?action=' + action)
        .then(r => r.json())
        .then(() => {
            refreshLog();
            refreshStatus();
        })
        .catch(() => {})
        .finally(() => {
            document.querySelectorAll('button[onclick]').forEach(b => b.disabled = false);
        });
}

/* ---- Arranque ---- */
refreshLog();
refreshStatus();
setInterval(refreshLog,    2000);
setInterval(refreshStatus, 2000);
</script>

</body>
</html>
