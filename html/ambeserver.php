<?php
$baseDir = "/home/pi/AMBE_SERVER";

$binary  = $baseDir . "/AMBEserver";
$iniFile = $baseDir . "/AMBEserver.ini";
$logFile = $baseDir . "/ambe.log";
$pidFile = $baseDir . "/ambe.pid";

/* 🔥 COMANDO AUTOARRANQUE */
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

/* =========================
   AJAX LOG
   ========================= */
if (isset($_GET['action']) && $_GET['action'] === 'log') {
    header('Content-Type: text/plain');
    system("tail -n 50 " . escapeshellarg($logFile));
    exit;
}

/* =========================
   AUTOARRANQUE CONTROL
   ========================= */
if (isset($_POST['enable_auto'])) {
    shell_exec("(crontab -l 2>/dev/null; echo \"$cronCmd\") | crontab -");
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

if (isset($_POST['disable_auto'])) {
    shell_exec("crontab -l 2>/dev/null | grep -v 'AMBEserver' | crontab -");
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

/* =========================
   STREAM LOG
   ========================= */
if (isset($_GET['stream'])) {

    header('Content-Type: text/plain');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');

    $cmd = "tail -f " . escapeshellarg($logFile);
    $h = popen($cmd, "r");

    if ($h) {
        while (!feof($h)) {
            echo fread($h, 1024);
            @ob_flush();
            flush();
        }
        pclose($h);
    }
    exit;
}

/* =========================
   LEER CONFIG
   ========================= */
$config = loadConfig($iniFile);

$speed = $config['velocidad'] ?? 460800;
$tty   = $config['puerto'] ?? "/dev/ttyUSB0";
$net   = $config['puertonet'] ?? 3000;

/* =========================
   GUARDAR INI
   ========================= */
if (isset($_POST['save'])) {

    $speedPost = trim($_POST['velocidad']);
    $ttyPost   = trim($_POST['puerto']);
    $netPost   = trim($_POST['puertonet']);

    $ini =
        "velocidad=$speedPost\n" .
        "puerto=$ttyPost\n" .
        "puertonet=$netPost\n";

    file_put_contents($iniFile, $ini, LOCK_EX);

    clearstatcache(true, $iniFile);

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

/* =========================
   STOP
   ========================= */
if (isset($_POST['stop'])) {
    if (file_exists($pidFile)) {
        $pid = trim(file_get_contents($pidFile));
        shell_exec("kill $pid");
        unlink($pidFile);
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

/* =========================
   START / RESTART
   ========================= */
if (isset($_POST['start']) || isset($_POST['restart'])) {

    if (file_exists($pidFile)) {
        $pid = trim(file_get_contents($pidFile));
        shell_exec("kill $pid");
        unlink($pidFile);
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
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

/* =========================
   CLEAR LOG
   ========================= */
if (isset($_POST['clear'])) {
    file_put_contents($logFile, "");
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

/* =========================
   ESTADOS
   ========================= */
$running = file_exists($pidFile);
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
.status { font-weight:bold; color: <?= $running ? 'lime' : 'red' ?>; }
.auto { font-weight:bold; color: <?= $autoEnabled ? 'cyan' : 'orange' ?>; }
pre { background:black; padding:10px; height:300px; overflow:auto; }
a { color:#00ffcc; }
</style>
</head>

<body>

<div class="card">
    <h2>AMBE Server</h2>

    <p>Estado:
        <span class="status">
            <?= $running ? "RUNNING" : "STOPPED" ?>
        </span>
    </p>

    <p>Autoarranque:
        <span class="auto">
            <?= $autoEnabled ? "ON" : "OFF" ?>
        </span>
    </p>

    <form method="post">
        <button name="start">▶ Start</button>
        <button name="restart">🔄 Restart</button>
        <button name="stop">⏹ Stop</button>
        <button name="clear">🧹 Clear Log</button>

        <button name="enable_auto">⚡ Auto ON</button>
        <button name="disable_auto">❌ Auto OFF</button>
    </form>
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

    <p>
        🔴 <a href="?stream=1" target="_blank">
            Ver log en tiempo real (TAIL -F)
        </a>
    </p>
</div>

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

refreshLog();
setInterval(refreshLog, 2000);
</script>

</body>
</html>
