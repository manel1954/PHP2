<?php
require_once __DIR__ . '/auth.php';

$INI_PATH = '/home/pi/MMDVMHost/MMDVMNXDN.ini';

// Función postKey aquí arriba, antes de usarse
function postKey($sec, $key) { return str_replace(' ', '_', $sec) . '_' . $key; }

function readIniValues($path, $sections) {
    $values = [];
    if (!file_exists($path)) return $values;
    $lines = file($path);
    $currentSec = '';
    foreach ($lines as $line) {
        $stripped = trim($line);
        if (preg_match('/^\[(.+)\]/', $stripped, $m)) { $currentSec = $m[1]; continue; }
        if ($stripped === '' || $stripped[0] === '#' || $stripped[0] === ';') continue;
        if (strpos($stripped, '=') !== false) {
            [$k, $v] = explode('=', $stripped, 2);
            $k = trim($k); $v = trim($v);
            if (isset($sections[$currentSec])) {
                foreach ($sections[$currentSec] as $field) {
                    if (strtolower($field['key']) === strtolower($k)) {
                        $values[$currentSec][$field['key']] = $v;
                    }
                }
            }
        }
    }
    return $values;
}

function writeIniValues($path, $sections, $newValues) {
    if (!file_exists($path)) return ['ok' => false, 'msg' => 'Fichero no encontrado'];

    $content = file_get_contents($path);

    foreach ($newValues as $sec => $fields) {
        $secQ = preg_quote($sec, '/');
        foreach ($fields as $key => $val) {
            $keyQ = preg_quote($key, '/');
            // Find the section, then the key=value before the next section header
            $content = preg_replace_callback(
                '/(\[' . $secQ . '\][^\[]*?)(' . $keyQ . '\s*=\s*)([^\r\n]*)/is',
                function($m) use ($val) {
                    return $m[1] . $m[2] . $val;
                },
                $content
            );
        }
    }

    if (file_put_contents($path, $content) === false) {
        return ['ok' => false, 'msg' => 'Error al escribir el fichero'];
    }
    return ['ok' => true, 'msg' => 'Configuración guardada correctamente'];
}

$SECTIONS = [
    'General' => [
        ['key' => 'Callsign',    'label' => 'Indicativo',          'type' => 'str'],
        ['key' => 'Id',          'label' => 'DMR ID',               'type' => 'int'],
        ['key' => 'Timeout',     'label' => 'Timeout (s)',          'type' => 'int'],
        ['key' => 'Duplex',      'label' => 'Duplex (0=simplex)',   'type' => 'int'],
        ['key' => 'RFModeHang',  'label' => 'RF Mode Hang (s)',     'type' => 'int'],
        ['key' => 'NetModeHang', 'label' => 'Net Mode Hang (s)',    'type' => 'int'],
        ['key' => 'Daemon',      'label' => 'Daemon (0/1)',         'type' => 'int'],
    ],
    'Info' => [
        ['key' => 'RXFrequency', 'label' => 'Frecuencia RX (Hz)',  'type' => 'int'],
        ['key' => 'TXFrequency', 'label' => 'Frecuencia TX (Hz)',  'type' => 'int'],
        ['key' => 'Power',       'label' => 'Potencia (W)',         'type' => 'int'],
        ['key' => 'Latitude',    'label' => 'Latitud',              'type' => 'str'],
        ['key' => 'Longitude',   'label' => 'Longitud',             'type' => 'str'],
        ['key' => 'Height',      'label' => 'Altura (m)',           'type' => 'int'],
        ['key' => 'Location',    'label' => 'Localización',         'type' => 'str'],
        ['key' => 'Description', 'label' => 'Descripción',          'type' => 'str'],
        ['key' => 'URL',         'label' => 'URL',                  'type' => 'str'],
    ],
    'Modem' => [
        ['key' => 'Protocol',   'label' => 'Protocolo',            'type' => 'str'],
        ['key' => 'UARTPort',   'label' => 'Puerto UART',          'type' => 'select', 'options' => [
            ['label' => '/dev/ttyAMA0',  'value' => '/dev/ttyAMA0'],
            ['label' => '/dev/ttyACM0',  'value' => '/dev/ttyACM0'],
            ['label' => '/dev/ttyACM1',  'value' => '/dev/ttyACM1'],
            ['label' => '/dev/ttyACM2',  'value' => '/dev/ttyACM2'],
            ['label' => '/dev/ttyUSB0',  'value' => '/dev/ttyUSB0'],
            ['label' => '/dev/ttyUSB1',  'value' => '/dev/ttyUSB1'],
            ['label' => '/dev/ttyUSB2',  'value' => '/dev/ttyUSB2'],
        ]],
        ['key' => 'UARTSpeed',  'label' => 'Velocidad UART',       'type' => 'select', 'options' => [
            ['label' => '115200', 'value' => '115200'],
            ['label' => '460800', 'value' => '460800'],
        ]],
        ['key' => 'TXInvert',   'label' => 'TX Invertido (0/1)',   'type' => 'int'],
        ['key' => 'RXInvert',   'label' => 'RX Invertido (0/1)',   'type' => 'int'],
        ['key' => 'PTTInvert',  'label' => 'PTT Invertido (0/1)',  'type' => 'int'],
        ['key' => 'TXDelay',    'label' => 'TX Delay (ms)',        'type' => 'int'],
        ['key' => 'RXOffset',   'label' => 'RX Offset',            'type' => 'int', 'signed' => true],
        ['key' => 'TXOffset',   'label' => 'TX Offset',            'type' => 'int', 'signed' => true],
        ['key' => 'RXLevel',    'label' => 'Nivel RX (%)',         'type' => 'int'],
        ['key' => 'TXLevel',    'label' => 'Nivel TX (%)',         'type' => 'int'],
        ['key' => 'RFLevel',    'label' => 'Nivel RF (%)',         'type' => 'int'],
        ['key' => 'Trace',      'label' => 'Trace (0/1)',          'type' => 'int'],
        ['key' => 'Debug',      'label' => 'Debug (0/1)',          'type' => 'int'],
    ],
    'NXDN' => [
        ['key' => 'Enable',        'label' => 'Habilitado (0/1)',      'type' => 'int'],
        ['key' => 'RAN',           'label' => 'RAN (0-63)',            'type' => 'int'],
        ['key' => 'SelfOnly',      'label' => 'Solo propio (0/1)',     'type' => 'int'],
        ['key' => 'RemoteGateway', 'label' => 'Gateway Remoto (0/1)', 'type' => 'int'],
        ['key' => 'TXHang',        'label' => 'TX Hang (s)',           'type' => 'int'],
        ['key' => 'ModeHang',      'label' => 'Mode Hang (s)',         'type' => 'int'],
    ],
    'NXDN Network' => [
        ['key' => 'Enable',         'label' => 'Habilitado (0/1)',     'type' => 'int'],
        ['key' => 'Protocol',       'label' => 'Protocolo',            'type' => 'str'],
        ['key' => 'LocalAddress',   'label' => 'Dirección local',      'type' => 'str'],
        ['key' => 'LocalPort',      'label' => 'Puerto local',         'type' => 'int'],
        ['key' => 'GatewayAddress', 'label' => 'Dirección gateway',    'type' => 'str'],
        ['key' => 'GatewayPort',    'label' => 'Puerto gateway',       'type' => 'int'],
        ['key' => 'Debug',          'label' => 'Debug (0/1)',          'type' => 'int'],
    ],
];

$SEC_COLORS = [
    'General'      => '#a8b9cc',
    'Info'         => '#b57aff',
    'Modem'        => '#00d4ff',
    'NXDN'         => '#ffd700',
    'NXDN Network' => '#ffc400',
];

$message = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = []; $newValues = [];
    foreach ($SECTIONS as $sec => $fields) {
        foreach ($fields as $field) {
            $pk  = postKey($sec, $field['key']);
            $val = trim($_POST[$pk] ?? '');
            if ($field['type'] === 'int' && $val !== '' && !is_numeric($val)) {
                $errors[] = "[{$sec}] '{$field['label']}' debe ser un número entero.";
            } else {
                $newValues[$sec][$field['key']] = $val;
            }
        }
    }
    if ($errors) { $message = implode('<br>', $errors); $msgType = 'error'; }
    else {
        $res = writeIniValues($INI_PATH, $SECTIONS, $newValues);
        $message = $res['msg']; $msgType = $res['ok'] ? 'success' : 'error';
    }
}

$values = readIniValues($INI_PATH, $SECTIONS);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>MMDVMNXDN Config Editor · EA3EIZ</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Rajdhani:wght@500;700&family=Orbitron:wght@700;900&display=swap" rel="stylesheet">
<style>
  :root {
    --bg:#0a0e14; --surface:#111720; --border:#1e2d3d;
    --green:#00ff9f; --red:#ff4560; --amber:#ffb300; --cyan:#00d4ff;
    --nxdn:#ffd700; --text:#a8b9cc; --text-dim:#4a5568;
    --font-mono:'Share Tech Mono',monospace; --font-ui:'Rajdhani',sans-serif;
  }
  * { box-sizing: border-box; }
  body { background: var(--bg); color: var(--text); font-family: var(--font-ui); margin: 0; min-height: 100vh; }
  .ctrl-header { background: var(--surface); border-bottom: 1px solid var(--border); padding: 1rem 2rem; display: flex; align-items: center; gap: 1rem; }
  .ctrl-header img { height: 40px; width: auto; }
  .ctrl-header h1 { font-family: var(--font-ui); font-weight: 700; font-size: 1.4rem; letter-spacing: .08em; color: #e2eaf5; margin: 0; text-transform: uppercase; }
  .ctrl-header h1 span { color: var(--nxdn); }
  .page-body { padding: 2rem; max-width: 700px; margin: 0 auto; }
  .sec-card { background: var(--surface); border: 1px solid var(--border); border-radius: 8px; margin-bottom: 1.5rem; overflow: hidden; }
  .sec-card-header { background: #0d1e2a; border-bottom: 1px solid var(--border); padding: .6rem 1.2rem; font-family: var(--font-mono); font-size: .8rem; letter-spacing: .12em; text-transform: uppercase; }
  .sec-card-body { padding: 1.2rem 1.5rem; }
  .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
  .field-row:last-child { margin-bottom: 0; }
  label { font-family: var(--font-ui); font-size: .95rem; color: var(--text); display: block; margin-bottom: .3rem; }
  input[type=text], input[type=number] {
    width: 100%; background: #0d1e2a; border: 1px solid var(--border);
    border-radius: 4px; color: var(--nxdn); font-family: var(--font-mono);
    font-size: .95rem; padding: .5rem .8rem; outline: none; transition: border-color .2s;
  }
  input[type=text]:focus, input[type=number]:focus { border-color: var(--nxdn); }
  select.field-select {
    width: 100%; background: #0d1e2a; border: 1px solid var(--border);
    border-radius: 4px; color: var(--nxdn); font-family: var(--font-mono);
    font-size: .95rem; padding: .5rem .8rem; outline: none; transition: border-color .2s; cursor: pointer;
  }
  select.field-select:focus { border-color: var(--nxdn); }
  select.field-select option { background: var(--surface); color: var(--nxdn); }
  .field-hint { font-family: var(--font-mono); font-size: .68rem; color: var(--text-dim); margin-top: .2rem; }
  .alert-custom { border-radius: 6px; padding: .8rem 1.2rem; margin-bottom: 1.5rem; font-family: var(--font-mono); font-size: .85rem; border: 1px solid; }
  .alert-success { background: rgba(255,215,0,.08); border-color: var(--nxdn); color: var(--nxdn); }
  .alert-error   { background: rgba(255,69,96,.08);  border-color: var(--red);   color: var(--red); }
  .ini-path { font-family: var(--font-mono); font-size: .75rem; color: var(--text-dim); background: #0d1e2a; border: 1px solid var(--border); border-radius: 4px; padding: .4rem .8rem; margin-bottom: 1.5rem; word-break: break-all; }
  .ini-path span { color: var(--nxdn); }
  .btn-row { display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; }
  .btn-save { background: #28a745; color: #fff; border: none; border-radius: 6px; font-family: var(--font-ui); font-weight: 700; font-size: 1rem; letter-spacing: .1em; text-transform: uppercase; padding: .75rem 2.5rem; cursor: pointer; transition: background .2s; }
  .btn-save:hover { background: #218838; }
  .btn-reload { background: var(--surface); color: var(--text-dim); border: 1px solid var(--border); border-radius: 6px; font-family: var(--font-ui); font-size: 1rem; letter-spacing: .1em; text-transform: uppercase; padding: .75rem 2rem; cursor: pointer; transition: background .2s, color .2s; text-decoration: none; display: inline-block; }
  .btn-reload:hover { background: #1e2d3d; color: var(--text); }
  .btn-back { margin-left: auto; background: #28a745; color: #fff; border: none; border-radius: 6px; font-family: var(--font-mono); font-size: .8rem; letter-spacing: .08em; text-transform: uppercase; padding: .4rem 1.2rem; text-decoration: none; transition: background .2s; }
  .btn-back:hover { background: #218838; color: #fff; }
  .note { font-family: var(--font-mono); font-size: .72rem; color: var(--text-dim); margin-left: auto; }
</style>
</head>
<body>
<header class="ctrl-header">
  <img src="Logo_ea3eiz.png" alt="EA3EIZ">
  <h1>MMDVM<span>NXDN</span> Config Editor</h1>
  <a href="mmdvm.php" class="btn-back">← Volver a MMDVM Control</a>
</header>

<div class="page-body">
  <div class="ini-path">📄 Fichero: <span><?= htmlspecialchars($INI_PATH) ?></span></div>

  <?php if ($message): ?>
  <div class="alert-custom alert-<?= $msgType ?>">
    <?= $msgType === 'success' ? '✔ ' : '✖ ' ?><?= $message ?>
    <?php if ($msgType === 'success'): ?><br><small>Reinicia MMDVMHost NXDN para aplicar los cambios.</small><?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if (!file_exists($INI_PATH)): ?>
  <div class="alert-custom alert-error">✖ Fichero no encontrado: <?= htmlspecialchars($INI_PATH) ?></div>
  <?php else: ?>

  <form method="POST">
    <?php foreach ($SECTIONS as $sec => $fields):
      $color = $SEC_COLORS[$sec] ?? '#ffd700';
    ?>
    <div class="sec-card" style="border-top: 3px solid <?= $color ?>;">
      <div class="sec-card-header" style="color:<?= $color ?>;">[<?= htmlspecialchars($sec) ?>]</div>
      <div class="sec-card-body">
        <?php $chunks = array_chunk($fields, 2);
        foreach ($chunks as $pair): ?>
        <div class="field-row">
          <?php foreach ($pair as $field):
            $pk     = postKey($sec, $field['key']);
            $val    = $values[$sec][$field['key']] ?? '';
            $signed = !empty($field['signed']);
          ?>
          <div>
            <label for="<?= $pk ?>"><?= htmlspecialchars($field['label']) ?></label>
            <?php if ($field['type'] === 'select'): ?>
              <select id="<?= $pk ?>" name="<?= $pk ?>" class="field-select">
                <?php foreach ($field['options'] as $opt): ?>
                <option value="<?= htmlspecialchars($opt['value']) ?>" <?= $val == $opt['value'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($opt['label']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            <?php else: ?>
              <input
                type="<?= $field['type'] === 'int' ? 'number' : 'text' ?>"
                id="<?= $pk ?>"
                name="<?= $pk ?>"
                value="<?= htmlspecialchars($val) ?>"
                <?= $field['type'] === 'int' ? ($signed ? 'min="-9999"' : 'min="0"') : '' ?>
              >
            <?php endif; ?>
            <div class="field-hint"><?= htmlspecialchars($field['key']) ?></div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>

    <div class="btn-row">
      <button type="submit" class="btn-save">💾 Guardar</button>
      <a href="mmdvmnxdn_config.php" class="btn-reload">🔄 Recargar</a>
      <span class="note">Los cambios requieren reiniciar MMDVMHost NXDN</span>
    </div>
  </form>
  <?php endif; ?>
</div>
</body>
</html>
