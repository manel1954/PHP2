<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hola</title>
    <style>
        body{
            background: #222;
        }
        .btn-header { background: cyan; padding: 10px; border: none; cursor: pointer; }
    </style>
</head>
<body>
    <h1></h1>

    <?php
    // Si se recibe la petición de ejecutar el script
    if (isset($_GET['action']) && $_GET['action'] === 'ejecutar_dump') {
        $script = '/home/pi/A108/ejecutar_dump1090.sh';
        $output = shell_exec("bash $script 2>&1");
        echo json_encode(['ok' => true, 'output' => $output]);
        exit;
    }
    ?>

    <button class="btn-header" onclick="ejecutarDump()">⌨ EJECUTAR DUMP1090</button>

    <script>
    function ejecutarDump() {
        fetch('?action=ejecutar_dump')
            .then(r => r.json())
            .then(data => {
                console.log('Script ejecutado:', data.output);
                alert('Script ejecutado correctamente');
            })
            .catch(err => {
                console.error('Error:', err);
                alert('Error al ejecutar el script');
            });
    }
    </script>
</body>
</html>