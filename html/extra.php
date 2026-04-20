<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MENU EXTRA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body class="bg-dark text-white">

<div class="container py-4">

<h1 class="mb-4 text-center">
    <i class="bi bi-grid-3x3-gap-fill me-2" style="color: #ff6600;"></i>
    <?php echo "MENU EXTRA"; ?>
</h1>

    <div class="row g-3">

        <div class="col-12 col-sm-6 col-md-4">
            <div class="card bg-secondary border-0 h-100">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title">
                        <i class="bi bi-airplane-fill me-2 text-info"></i>DUMP1090
                    </h5>
                    <p class="card-text text-white-50 small flex-grow-1">Receptor ADS-B · Seguimiento de aeronaves en tiempo real.</p>
                    <a href="/dump1090.php" target="_blank" class="btn btn-info btn-sm mt-2 text-dark fw-bold">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Abrir
                    </a>
                </div>
            </div>
        </div>

        <!-- Añade más tarjetas aquí con el mismo bloque col -->

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
