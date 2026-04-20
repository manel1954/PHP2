<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title style="color:#fff;">MENU EXTRA</title>
    <style>
    body { background-color: #120f0f;
    }
    .btn-header { background: cyan; padding: 10px; border: none; cursor: pointer; 
}
    </style>
</head>
<body>
    <h1><?php echo "MENU EXTRA"; ?></h1>
    
    <button class="btn-header" onclick="extraOpen()">⌨ DUMP1090</button>
    
    <script>
    function extraOpen() {
        window.open('/dump1090.php', '_blank');
    }
    </script>
</body>
</html>
