<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hola</title>
    <style>
        .btn-header { background: cyan; padding: 10px; border: none; cursor: pointer; }
    </style>
</head>
<body>
    <h1></h1>

    <?php
    if (isset($_GET['action']) && $_GET['action'] === 'ejecutar_dump') {
        header('Content-Type: application/json');
        $script = '/home/pi/A108/ejecutar_dump1090.sh';

        if (!file_exists($script)) {
            echo json_encode(['ok' => false, 'error' => 'Script no encontrado: ' . $script]);
            exit;
        }
        if (!is_executable($script)) {
            echo json_encode(['ok' => false, 'error' => 'Script sin permiso de ejecución']);
            exit;
        }

        $output = shell_exec("sudo bash $script 2>&1");

        if ($output === null) {
            echo json_encode(['ok' => false, 'error' => 'shell_exec devolvió null (¿sudoers mal configurado o shell_exec deshabilitado?)']);
            exit;
        }

        echo json_encode(['ok' => true, 'output' => $output]);
        exit;
    }
    ?>

    <button class="btn-header" onclick="ejecutarDump()">⌨ EJECUTAR DUMP1090</button>

    <script>
    function ejecutarDump() {
    fetch('/ejecutar_dump.php')   // <-- ruta absoluta al nuevo fichero
        .then(r => r.text())
        .then(raw => {
            console.log('Respuesta raw:', raw);
            const data = JSON.parse(raw);
            if (data.ok) {
                alert('✅ Script ejecutado:\n' + (data.output || '(sin salida)'));
            } else {
                alert('❌ Error: ' + data.error);
            }
        })
        .catch(err => {
            alert('❌ Error: ' + err);
        });
}
    </script>
</body>
</html>