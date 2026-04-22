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

    if ($action === 'log') {
        header('Content-Type: text/plain');
        system("tail -n 50 " . escapeshellarg($logFile));
        exit;
    }

    if ($action === 'status') {
        header('Content-Type: application/json');
        echo json_encode([
            'running' => file_exists($pidFile),
            'auto'    => getAutoStatus(),
        ]);
        exit;
    }

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

    if ($action === 'clear') {
        header('Content-Type: application/json');
        file_put_contents($logFile, "");
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'enable_auto') {
        header('Content-Type: application/json');
        shell_exec("(crontab -l 2>/dev/null; echo \"$cronCmd\") | crontab -");
        logMsg($logFile, ">>> Autoarranque ACTIVADO");
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'disable_auto') {
        header('Content-Type: application/json');
        shell_exec("crontab -l 2>/dev/null | grep -v 'AMBEserver' | crontab -");
        logMsg($logFile, ">>> Autoarranque DESACTIVADO");
        echo json_encode(['ok' => true]);
        exit;
    }
}

/* =========================
   GUARDAR INI
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
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AMBE Server</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: #0d0d0d; }
        .log-box {
            background: #000;
            color: #0f0;
            font-family: monospace;
            font-size: 13px;
            height: 300px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-break: break-all;
            border-radius: 6px;
            padding: 12px;
        }
        .form-control, .form-control:focus {
            background: #111;
            color: #0f0;
            border-color: #333;
        }
        .form-control:focus { box-shadow: none; border-color: #00ffcc; }
        .form-label { color: #aaa; }
    </style>
</head>
<body class="bg-dark text-white">

<div class="container py-4">

    <!-- ESTADO -->
    <div class="card bg-secondary bg-opacity-25 border-secondary mb-3">
        <div class="card-body">
            <h4 class="card-title mb-3">
                <i class="bi bi-cpu-fill me-2 text-warning"></i>AMBE Server
            </h4>

            <div class="d-flex gap-4 mb-4">
                <div>
                    <span class="text-muted small">Estado</span><br>
                    <span id="statusLabel" class="fw-bold fs-5 <?= $running ? 'text-success' : 'text-danger' ?>">
                        <?= $running ? '● RUNNING' : '● STOPPED' ?>
                    </span>
                </div>
                <div>
                    <span class="text-muted small">Autoarranque</span><br>
                    <span id="autoLabel" class="fw-bold fs-5 <?= $autoEnabled ? 'text-info' : 'text-warning' ?>">
                        <?= $autoEnabled ? 'ON' : 'OFF' ?>
                    </span>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-success" onclick="doAction('start')">
                    <i class="bi bi-play-fill me-1"></i>Start
                </button>
                <button class="btn btn-warning text-dark" onclick="doAction('restart')">
                    <i class="bi bi-arrow-clockwise me-1"></i>Restart
                </button>
                <button class="btn btn-danger" onclick="doAction('stop')">
                    <i class="bi bi-stop-fill me-1"></i>Stop
                </button>
                <button class="btn btn-secondary" onclick="doAction('clear')">
                    <i class="bi bi-trash me-1"></i>Clear Log
                </button>
                <button id="btnAuto"
                    class="btn <?= $autoEnabled ? 'btn-outline-danger' : 'btn-outline-info' ?>"
                    onclick="toggleAuto()">
                    <?= $autoEnabled
                        ? '<i class="bi bi-x-circle me-1"></i>Auto OFF'
                        : '<i class="bi bi-lightning-charge me-1"></i>Auto ON' ?>
                </button>
            </div>
        </div>
    </div>

    <!-- CONFIGURACIÓN -->
    <div class="card bg-secondary bg-opacity-25 border-secondary mb-3">
        <div class="card-body">
            <h5 class="card-title mb-3">
                <i class="bi bi-sliders me-2 text-info"></i>Configuración (AMBEserver.ini)
            </h5>
            <form method="post">
                <div class="mb-3">
                    <label class="form-label">Velocidad</label>
                    <input class="form-control" name="velocidad" value="<?= htmlspecialchars($speed) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Puerto</label>
                    <input class="form-control" name="puerto" value="<?= htmlspecialchars($tty) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Puerto NET</label>
                    <input class="form-control" name="puertonet" value="<?= htmlspecialchars($net) ?>">
                </div>
                <button class="btn btn-primary" name="save">
                    <i class="bi bi-floppy me-1"></i>Guardar INI
                </button>
            </form>
        </div>
    </div>

    <!-- LOG -->
    <div class="card bg-secondary bg-opacity-25 border-secondary">
        <div class="card-body">
            <h5 class="card-title mb-3">
                <i class="bi bi-terminal me-2 text-success"></i>Log (últimas líneas)
            </h5>
            <div class="log-box" id="logbox"></div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function refreshLog() {
    fetch('?action=log')
        .then(r => r.text())
        .then(text => {
            const box = document.getElementById('logbox');
            box.textContent = text;
            box.scrollTop = box.scrollHeight;
        });
}

function refreshStatus() {
    fetch('?action=status')
        .then(r => r.json())
        .then(data => {
            const sl = document.getElementById('statusLabel');
            sl.textContent = data.running ? '● RUNNING' : '● STOPPED';
            sl.className   = 'fw-bold fs-5 ' + (data.running ? 'text-success' : 'text-danger');

            const al = document.getElementById('autoLabel');
            al.textContent = data.auto ? 'ON' : 'OFF';
            al.className   = 'fw-bold fs-5 ' + (data.auto ? 'text-info' : 'text-warning');

            const btn = document.getElementById('btnAuto');
            if (data.auto) {
                btn.className = 'btn btn-outline-danger';
                btn.innerHTML = '<i class="bi bi-x-circle me-1"></i>Auto OFF';
            } else {
                btn.className = 'btn btn-outline-info';
                btn.innerHTML = '<i class="bi bi-lightning-charge me-1"></i>Auto ON';
            }
        });
}

function toggleAuto() {
    fetch('?action=status')
        .then(r => r.json())
        .then(data => {
            doAction(data.auto ? 'disable_auto' : 'enable_auto');
        });
}

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

refreshLog();
refreshStatus();
setInterval(refreshLog,    2000);
setInterval(refreshStatus, 2000);
</script>
</body>
</html>
