<?php
// ============================================================
//  EDITOR GENERAL CONFIG – Panel ADER
//  Lee y escribe los campos comunes en los 4 ficheros INI
// ============================================================
session_start();

// --- Rutas a los ficheros INI ---
$INI_FILES = [
    'MMDVMHost'  => '/home/pi/MMDVMHost/MMDVMHost.ini',
    'MMDVMYSF'   => '/home/pi/MMDVMHost/MMDVMYSF.ini',
    'MMDVMDSTAR' => '/home/pi/MMDVMHost/MMDVMDSTAR.ini',
    'MMDVMNXDN'  => '/home/pi/MMDVMHost/MMDVMNXDN.ini',
];

// --- Mapa de campos: campo => [ fichero => [sección, clave] ] ---
$FIELD_MAP = [
    'Callsign'    => [
        'MMDVMHost'  => ['General', 'Callsign'],
        'MMDVMYSF'   => ['General', 'Callsign'],
        'MMDVMDSTAR' => ['General', 'Callsign'],
        'MMDVMNXDN'  => ['General', 'Callsign'],
    ],
    'Id'          => [
        'MMDVMHost'  => ['General', 'Id'],
    ],
    'RXFrequency' => [
        'MMDVMHost'  => ['Info', 'RXFrequency'],
    ],
    'TXFrequency' => [
        'MMDVMHost'  => ['Info', 'TXFrequency'],
    ],
    'Latitude'    => [
        'MMDVMHost'  => ['Info', 'Latitude'],
    ],
    'Longitude'   => [
        'MMDVMHost'  => ['Info', 'Longitude'],
    ],
    'Location'    => [
        'MMDVMHost'  => ['Info', 'Location'],
    ],
    'URL'         => [
        'MMDVMHost'  => ['Info', 'URL'],
    ],
];

// ============================================================
//  Funciones INI (preservan comentarios y orden)
// ============================================================

/**
 * Lee un valor de un fichero INI (sección + clave).
 * Devuelve string o null si no existe.
 */
function ini_read_value(string $filepath, string $section, string $key): ?string {
    if (!file_exists($filepath)) return null;
    $lines = file($filepath, FILE_IGNORE_NEW_LINES);
    $in_section = false;
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if (preg_match('/^\[(.+)\]$/', $trimmed, $m)) {
            $in_section = (strcasecmp($m[1], $section) === 0);
            continue;
        }
        if ($in_section && preg_match('/^' . preg_quote($key, '/') . '\s*=\s*(.*)$/i', $trimmed, $m)) {
            return trim($m[1]);
        }
    }
    return null;
}

/**
 * Escribe (o crea) un valor en un fichero INI preservando todo lo demás.
 * Si la sección no existe, la crea al final del fichero.
 * Devuelve true en éxito, string de error en fallo.
 */
function ini_write_value(string $filepath, string $section, string $key, string $value): bool|string {
    if (!file_exists($filepath)) {
        return "Fichero no encontrado: $filepath";
    }
    if (!is_writable($filepath)) {
        return "Sin permiso de escritura: $filepath";
    }

    $lines = file($filepath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $output        = [];
    $in_section    = false;
    $found_section = false;
    $found_key     = false;

    foreach ($lines as $raw) {
        $trimmed = trim($raw);

        // Detectar cabecera de sección
        if (preg_match('/^\[(.+)\]$/', $trimmed, $m)) {
            // Si salimos de la sección buscada sin haber encontrado la clave, la añadimos
            if ($in_section && !$found_key) {
                $output[] = "$key=$value";
                $found_key = true;
            }
            $in_section = (strcasecmp($m[1], $section) === 0);
            if ($in_section) $found_section = true;
            $output[] = $raw;
            continue;
        }

        // Reemplazar clave dentro de la sección
        if ($in_section && !$found_key &&
            preg_match('/^' . preg_quote($key, '/') . '\s*=/i', $trimmed)) {
            $output[] = "$key=$value";
            $found_key = true;
            continue;
        }

        $output[] = $raw;
    }

    // Si la clave no se encontró dentro de una sección que sí existía
    if ($in_section && !$found_key) {
        $output[] = "$key=$value";
    }

    // Si la sección no existía en absoluto, la creamos al final
    if (!$found_section) {
        $output[] = '';
        $output[] = "[$section]";
        $output[] = "$key=$value";
    }

    $result = file_put_contents($filepath, implode(PHP_EOL, $output) . PHP_EOL);
    return $result !== false ? true : "Error al escribir en $filepath";
}

// ============================================================
//  Leer valores actuales de todos los ficheros
// ============================================================
function read_all_values(array $field_map, array $ini_files): array {
    $values = [];
    foreach ($field_map as $field => $targets) {
        // Prioridad: leer del primer fichero que tenga el campo definido
        $val = null;
        foreach ($targets as $file_key => [$section, $key]) {
            $path = $ini_files[$file_key] ?? null;
            if (!$path) continue;
            $v = ini_read_value($path, $section, $key);
            if ($v !== null) { $val = $v; break; }
        }
        $values[$field] = $val ?? '';
    }
    return $values;
}

// ============================================================
//  Procesar POST – guardar valores
// ============================================================
$messages   = [];   // ['type'=>'success'|'danger', 'text'=>'...']
$form_values = read_all_values($FIELD_MAP, $INI_FILES);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config'])) {
    $post_fields = [
        'Callsign', 'Id', 'RXFrequency', 'TXFrequency',
        'Latitude', 'Longitude', 'Location', 'URL'
    ];

    $errors  = [];
    $written = [];

    foreach ($post_fields as $field) {
        $new_val = trim($_POST[$field] ?? '');
        $targets = $FIELD_MAP[$field] ?? [];

        foreach ($targets as $file_key => [$section, $key]) {
            $path = $INI_FILES[$file_key] ?? null;
            if (!$path) continue;
            $result = ini_write_value($path, $section, $key, $new_val);
            if ($result === true) {
                $written[] = "$file_key → [$section] $key";
            } else {
                $errors[] = $result;
            }
        }
        // Actualizar valores del formulario con los nuevos
        $form_values[$field] = $new_val;
    }

    if (empty($errors)) {
        $messages[] = [
            'type' => 'success',
            'text' => '✅ Configuración guardada correctamente en ' . count(array_unique(array_map(fn($w) => explode(' →', $w)[0], $written))) . ' fichero(s).'
        ];
    } else {
        foreach ($errors as $e) {
            $messages[] = ['type' => 'danger', 'text' => '❌ ' . htmlspecialchars($e)];
        }
        if (!empty($written)) {
            $messages[] = ['type' => 'warning', 'text' => '⚠️ Escrito parcialmente en: ' . implode(', ', array_unique(array_map(fn($w) => explode(' →', $w)[0], $written)))];
        }
    }
}

// ============================================================
//  Estado (existencia + permisos) de cada fichero
// ============================================================
$file_status = [];
foreach ($INI_FILES as $key => $path) {
    $exists   = file_exists($path);
    $writable = $exists && is_writable($path);
    $file_status[$key] = [
        'path'     => $path,
        'exists'   => $exists,
        'writable' => $writable,
    ];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Editor General · Panel ADER</title>

<!-- Bootstrap 5.3 -->
<link rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<!-- Bootstrap Icons -->
<link rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;600&family=Share+Tech+Mono&display=swap"
      rel="stylesheet">

<style>
/* ─── CSS Variables (Panel ADER) ──────────────────────── */
:root {
    --bg-base:    #0d1117;
    --bg-card:    #161b22;
    --bg-input:   #1c2330;
    --cyan:       #00e5ff;
    --amber:      #ffb300;
    --violet:     #bf00ff;
    --green:      #00e676;
    --red:        #ff1744;
    --text-main:  #e0e6f0;
    --text-muted: #8892a4;
    --border:     #2a3447;
    --font-hud:   'Orbitron', sans-serif;
    --font-ui:    'Rajdhani', sans-serif;
    --font-mono:  'Share Tech Mono', monospace;
}

* { box-sizing: border-box; }

body {
    background: var(--bg-base);
    color:      var(--text-main);
    font-family: var(--font-ui);
    font-size:  1rem;
    min-height: 100vh;
}

/* ─── Header ─────────────────────────────────────────── */
.page-header {
    background: linear-gradient(135deg, #0d1117 0%, #161b22 100%);
    border-bottom: 1px solid var(--cyan);
    padding: 1.25rem 2rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}
.page-header h1 {
    font-family: var(--font-hud);
    font-size: 1.4rem;
    color: var(--cyan);
    margin: 0;
    letter-spacing: .1em;
    text-shadow: 0 0 12px rgba(0,229,255,.45);
}
.page-header .badge-subtitle {
    font-family: var(--font-mono);
    font-size: .7rem;
    color: var(--text-muted);
    background: rgba(0,229,255,.08);
    border: 1px solid rgba(0,229,255,.2);
    border-radius: 4px;
    padding: 2px 8px;
}
.btn-back {
    font-family: var(--font-hud);
    font-size: .7rem;
    letter-spacing: .05em;
    color: var(--text-muted);
    border: 1px solid var(--border);
    background: transparent;
    border-radius: 6px;
    padding: 5px 12px;
    text-decoration: none;
    transition: all .2s;
}
.btn-back:hover { color: var(--cyan); border-color: var(--cyan); }

/* ─── Cards ──────────────────────────────────────────── */
.ader-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}
.ader-card-title {
    font-family: var(--font-hud);
    font-size: .85rem;
    letter-spacing: .12em;
    color: var(--cyan);
    margin-bottom: 1.25rem;
    display: flex;
    align-items: center;
    gap: .5rem;
}
.ader-card-title::after {
    content: '';
    flex: 1;
    height: 1px;
    background: linear-gradient(90deg, var(--cyan) 0%, transparent 100%);
    opacity: .35;
    margin-left: .5rem;
}

/* ─── Form controls ──────────────────────────────────── */
.form-label {
    font-family: var(--font-mono);
    font-size: .78rem;
    color: var(--text-muted);
    margin-bottom: .3rem;
    letter-spacing: .04em;
}
.form-label .badge-ini {
    font-size: .62rem;
    background: rgba(0,229,255,.1);
    color: var(--cyan);
    border: 1px solid rgba(0,229,255,.25);
    border-radius: 3px;
    padding: 1px 5px;
    margin-left: 4px;
    vertical-align: middle;
}
.form-control {
    background: var(--bg-input) !important;
    border: 1px solid var(--border) !important;
    color: var(--text-main) !important;
    font-family: var(--font-mono);
    font-size: .9rem;
    border-radius: 6px;
    transition: border-color .2s, box-shadow .2s;
}
.form-control:focus {
    border-color: var(--cyan) !important;
    box-shadow: 0 0 0 3px rgba(0,229,255,.15) !important;
    outline: none;
}
.form-control::placeholder { color: #4a5568; }

.form-hint {
    font-size: .72rem;
    color: var(--text-muted);
    font-family: var(--font-mono);
    margin-top: .25rem;
}

/* ─── Botón guardar ───────────────────────────────────── */
.btn-save {
    font-family: var(--font-hud);
    font-size: .8rem;
    letter-spacing: .1em;
    background: var(--cyan);
    color: #000;
    border: none;
    border-radius: 8px;
    padding: .6rem 2rem;
    cursor: pointer;
    transition: all .2s;
    box-shadow: 0 0 16px rgba(0,229,255,.3);
}
.btn-save:hover {
    background: #fff;
    box-shadow: 0 0 24px rgba(0,229,255,.6);
}

/* ─── File status table ───────────────────────────────── */
.file-status-table {
    width: 100%;
    border-collapse: collapse;
    font-family: var(--font-mono);
    font-size: .78rem;
}
.file-status-table th {
    color: var(--text-muted);
    border-bottom: 1px solid var(--border);
    padding: .4rem .6rem;
    text-align: left;
    font-weight: normal;
}
.file-status-table td {
    padding: .45rem .6rem;
    border-bottom: 1px solid rgba(255,255,255,.04);
    vertical-align: middle;
}
.file-status-table tr:last-child td { border-bottom: none; }
.pill {
    display: inline-block;
    border-radius: 4px;
    padding: 1px 8px;
    font-size: .65rem;
    font-weight: 600;
    letter-spacing: .05em;
}
.pill-ok      { background: rgba(0,230,118,.15); color: var(--green); border: 1px solid rgba(0,230,118,.3); }
.pill-warn    { background: rgba(255,179,0,.12); color: var(--amber); border: 1px solid rgba(255,179,0,.3); }
.pill-danger  { background: rgba(255,23,68,.12);  color: var(--red);   border: 1px solid rgba(255,23,68,.3); }

/* ─── Alerts ─────────────────────────────────────────── */
.ader-alert {
    border-radius: 7px;
    padding: .75rem 1rem;
    margin-bottom: .75rem;
    font-family: var(--font-mono);
    font-size: .82rem;
    border-left: 3px solid transparent;
}
.ader-alert-success { background: rgba(0,230,118,.1);  border-color: var(--green); color: var(--green); }
.ader-alert-danger  { background: rgba(255,23,68,.1);   border-color: var(--red);   color: var(--red); }
.ader-alert-warning { background: rgba(255,179,0,.1);   border-color: var(--amber); color: var(--amber); }

/* ─── Section divider ─────────────────────────────────── */
.section-label {
    font-family: var(--font-hud);
    font-size: .65rem;
    letter-spacing: .15em;
    color: var(--text-muted);
    text-transform: uppercase;
    margin: 1.5rem 0 .75rem;
    display: flex;
    align-items: center;
    gap: .5rem;
}
.section-label::before {
    content: '';
    display: inline-block;
    width: 3px;
    height: 12px;
    background: var(--cyan);
    border-radius: 2px;
}

/* ─── Responsive ──────────────────────────────────────── */
@media (max-width: 768px) {
    .page-header { flex-wrap: wrap; }
    .col-freq { flex: 0 0 100%; }
}
</style>
</head>
<body>

<!-- ── Header ──────────────────────────────────────── -->
<div class="page-header">
    <a href="mmdvm.php" class="btn-back">
        <i class="bi bi-arrow-left"></i> Panel ADER
    </a>
    <div>
        <h1><i class="bi bi-sliders"></i> &nbsp;EDITOR GENERAL</h1>
        <span class="badge-subtitle">MMDVMHost · YSF · D-STAR · NXDN</span>
    </div>
</div>

<div class="container-fluid py-4 px-4">
<div class="row g-4">

    <!-- ── Columna izquierda: formulario ─────────────── -->
    <div class="col-lg-8">

        <!-- Mensajes de resultado -->
        <?php foreach ($messages as $msg): ?>
        <div class="ader-alert ader-alert-<?= $msg['type'] ?>">
            <?= $msg['text'] ?>
        </div>
        <?php endforeach; ?>

        <div class="ader-card">
            <div class="ader-card-title">
                <i class="bi bi-pencil-square"></i>
                CONFIGURACIÓN GENERAL
            </div>

            <form method="POST" autocomplete="off">

                <!-- Callsign + Id -->
                <div class="section-label">Identificación de estación</div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">
                            Callsign
                            <span class="badge-ini">MMDVMHost · YSF · DSTAR · NXDN</span>
                        </label>
                        <input type="text" name="Callsign" class="form-control"
                               value="<?= htmlspecialchars($form_values['Callsign']) ?>"
                               placeholder="Ej: EA3EIZ"
                               maxlength="20">
                        <div class="form-hint">[General] Callsign= en los 4 ficheros</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">
                            DMR Id
                            <span class="badge-ini">MMDVMHost</span>
                        </label>
                        <input type="text" name="Id" class="form-control"
                               value="<?= htmlspecialchars($form_values['Id']) ?>"
                               placeholder="Ej: 214317526"
                               pattern="[0-9]*">
                        <div class="form-hint">[General] Id= en MMDVMHost.ini</div>
                    </div>
                </div>

                <!-- RX / TX Frecuencia -->
                <div class="section-label">Frecuencias</div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6 col-freq">
                        <label class="form-label">
                            RX Frecuencia (Hz)
                            <span class="badge-ini">MMDVMHost</span>
                        </label>
                        <input type="text" name="RXFrequency" class="form-control"
                               value="<?= htmlspecialchars($form_values['RXFrequency']) ?>"
                               placeholder="Ej: 430500000"
                               pattern="[0-9]*">
                        <div class="form-hint">[Info] RXFrequency=</div>
                    </div>
                    <div class="col-md-6 col-freq">
                        <label class="form-label">
                            TX Frecuencia (Hz)
                            <span class="badge-ini">MMDVMHost</span>
                        </label>
                        <input type="text" name="TXFrequency" class="form-control"
                               value="<?= htmlspecialchars($form_values['TXFrequency']) ?>"
                               placeholder="Ej: 430500000"
                               pattern="[0-9]*">
                        <div class="form-hint">[Info] TXFrequency=</div>
                    </div>
                </div>

                <!-- Latitud / Longitud -->
                <div class="section-label">Posición geográfica</div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">
                            Latitud
                            <span class="badge-ini">MMDVMHost</span>
                        </label>
                        <input type="text" name="Latitude" class="form-control"
                               value="<?= htmlspecialchars($form_values['Latitude']) ?>"
                               placeholder="Ej: 41.3851">
                        <div class="form-hint">[Info] Latitude=</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">
                            Longitud
                            <span class="badge-ini">MMDVMHost</span>
                        </label>
                        <input type="text" name="Longitude" class="form-control"
                               value="<?= htmlspecialchars($form_values['Longitude']) ?>"
                               placeholder="Ej: 2.1734">
                        <div class="form-hint">[Info] Longitude=</div>
                    </div>
                </div>

                <!-- Location + URL -->
                <div class="section-label">Información pública</div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">
                            Location
                            <span class="badge-ini">MMDVMHost</span>
                        </label>
                        <input type="text" name="Location" class="form-control"
                               value="<?= htmlspecialchars($form_values['Location']) ?>"
                               placeholder="Ej: Barcelona, Spain">
                        <div class="form-hint">[Info] Location=</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">
                            URL
                            <span class="badge-ini">MMDVMHost</span>
                        </label>
                        <input type="text" name="URL" class="form-control"
                               value="<?= htmlspecialchars($form_values['URL']) ?>"
                               placeholder="Ej: www.associacioader.com">
                        <div class="form-hint">[Info] URL=</div>
                    </div>
                </div>

                <hr style="border-color:var(--border); margin:1.5rem 0;">

                <div class="d-flex align-items-center gap-3">
                    <button type="submit" name="save_config" class="btn-save">
                        <i class="bi bi-floppy"></i> &nbsp;GUARDAR CONFIGURACIÓN
                    </button>
                    <span style="font-family:var(--font-mono);font-size:.72rem;color:var(--text-muted);">
                        Los ficheros se actualizan al instante
                    </span>
                </div>

            </form>
        </div><!-- /ader-card -->
    </div><!-- /col-lg-8 -->

    <!-- ── Columna derecha: estado de ficheros ─────────── -->
    <div class="col-lg-4">

        <div class="ader-card">
            <div class="ader-card-title">
                <i class="bi bi-file-earmark-code"></i>
                ESTADO DE FICHEROS
            </div>

            <table class="file-status-table">
                <thead>
                    <tr>
                        <th>Fichero</th>
                        <th>Existe</th>
                        <th>Escritura</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($file_status as $key => $s): ?>
                    <tr>
                        <td style="color:var(--text-main);"><?= $key ?>.ini</td>
                        <td>
                            <?php if ($s['exists']): ?>
                                <span class="pill pill-ok">OK</span>
                            <?php else: ?>
                                <span class="pill pill-danger">NO</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!$s['exists']): ?>
                                <span class="pill pill-danger">N/A</span>
                            <?php elseif ($s['writable']): ?>
                                <span class="pill pill-ok">OK</span>
                            <?php else: ?>
                                <span class="pill pill-warn">PERM</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="3"
                            style="font-size:.67rem;color:var(--text-muted);font-family:var(--font-mono);
                                   padding-top:0;padding-bottom:.55rem;">
                            <?= htmlspecialchars($s['path']) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Info sobre qué campos van a qué fichero -->
        <div class="ader-card">
            <div class="ader-card-title">
                <i class="bi bi-diagram-3"></i>
                MAPA DE CAMPOS
            </div>
            <div style="font-family:var(--font-mono);font-size:.72rem;line-height:1.9;">
                <?php
                $map_display = [
                    'Callsign'    => ['MMDVMHost [General]', 'MMDVMYSF [General]', 'MMDVMDSTAR [General]', 'MMDVMNXDN [General]'],
                    'Id'          => ['MMDVMHost [General]'],
                    'RXFrequency' => ['MMDVMHost [Info]'],
                    'TXFrequency' => ['MMDVMHost [Info]'],
                    'Latitude'    => ['MMDVMHost [Info]'],
                    'Longitude'   => ['MMDVMHost [Info]'],
                    'Location'    => ['MMDVMHost [Info]'],
                    'URL'         => ['MMDVMHost [Info]'],
                ];
                foreach ($map_display as $field => $targets):
                ?>
                <div style="margin-bottom:.6rem;">
                    <span style="color:var(--cyan);"><?= $field ?></span>
                    <div style="color:var(--text-muted);padding-left:.8rem;">
                        <?php foreach ($targets as $t): ?>
                        <div>↳ <?= $t ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Nota de permisos -->
        <div class="ader-card" style="border-color:rgba(255,179,0,.3);">
            <div class="ader-card-title" style="color:var(--amber);">
                <i class="bi bi-shield-exclamation"></i>
                PERMISOS NECESARIOS
            </div>
            <p style="font-family:var(--font-mono);font-size:.72rem;color:var(--text-muted);line-height:1.7;margin:0;">
                El usuario <code style="color:var(--amber);">www-data</code> debe tener
                permiso de escritura en los ficheros INI.<br><br>
                Ejecuta en el Pi:
            </p>
            <pre style="background:#0d1117;border:1px solid var(--border);border-radius:5px;
                        padding:.6rem;font-size:.68rem;color:var(--green);margin-top:.6rem;
                        overflow-x:auto;">sudo chown pi:www-data *.ini
sudo chmod 664 *.ini</pre>
        </div>

    </div><!-- /col-lg-4 -->
</div><!-- /row -->
</div><!-- /container -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
