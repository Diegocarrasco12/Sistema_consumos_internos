<?php
/**
 * index.php
 *
 * Flujo con cámara:
 *  - Botón INICIAR ESCANEO abre la cámara (trasera en móviles).
 *  - Al detectar un QR, autocompleta raw_qr (oculto) y muestra el texto leído.
 *  - Operador ingresa NP, SALDO KG y ahora también DESCRIPCIÓN y CÓDIGO manuales.
 *  - CONSUMO = TARJA - SALDO.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/qr_parser.php';
require_once __DIR__ . '/../models/TarjaScan.php';
require_once __DIR__ . '/../models/SAPCatalog.php'; // 🔄 reemplazo del modelo local antiguo

use function Helpers\parse_qr;
use Models\TarjaScan;
use Models\SAPCatalog;

$message = '';
$errors  = [];

// Valores para repintar el formulario
$raw_qr         = $_POST['raw_qr']        ?? '';
$np             = $_POST['np']            ?? '';
$saldo_in       = $_POST['saldo_kg']      ?? '';
$descripcion_in = $_POST['descripcion']   ?? '';
$codigo_in      = $_POST['codigo']        ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $raw_qr         = trim((string)$raw_qr);
    $np             = trim((string)$np);
    $descripcion_in = trim((string)$descripcion_in);
    $codigo_in      = strtoupper(trim((string)$codigo_in));

    $saldo_kg = ($saldo_in !== '' && $saldo_in !== null)
        ? (float) str_replace(',', '.', (string) $saldo_in)
        : 0.0;

    if ($raw_qr === '') {
        $errors[] = 'Debe escanear un código QR.';
    }

    if (empty($errors)) {
        $parsed   = parse_qr($raw_qr);
        $codigoQR = $parsed['codigo']   ?? null;
        $lote     = $parsed['lote']     ?? null;
        $tarja_kg = $parsed['tarja_kg'] ?? null;

        $faltantes = [];
        if ($lote === null)     { $faltantes[] = 'lote'; }
        if ($tarja_kg === null) { $faltantes[] = 'peso de la tarja'; }

        if (!empty($faltantes)) {
            $errors[] = 'No se pudo extraer correctamente el ' . implode(', ', $faltantes) . ' del QR. Verifique el formato y vuelva a intentar.';
        }

        if (empty($errors)) {
            $tarja_kg = (float)$tarja_kg;
            $saldo_kg = max(0.0, (float)$saldo_kg);

            $consumo_kg = $tarja_kg - $saldo_kg;
            if ($consumo_kg < 0) {
                $consumo_kg = 0.0;
                $errors[] = 'El SALDO KG ingresado es mayor al peso de la tarja. Se ha ajustado el CONSUMO KG a 0. Revise el dato.';
            }

            if (empty($errors)) {
                $codigoFinal      = $codigo_in !== '' ? $codigo_in : ($codigoQR ?? null);
                $descripcionFinal = $descripcion_in;

                // 🔵 Actualización: búsqueda de descripción en SAPCatalog (base local)
if ($descripcionFinal === '' && $codigoFinal !== null) {
    $producto = SAPCatalog::findByCodeOrBarcode($codigoFinal);
    if (!empty($producto['item_name'])) {
        $descripcionFinal = $producto['item_name'];
    }
}


                $id_usuario = null;
                TarjaScan::create([
                    'descripcion' => $descripcionFinal,
                    'codigo'      => $codigoFinal,
                    'consumo_kg'  => $consumo_kg,
                    'np'          => $np,
                    'tarja_kg'    => $tarja_kg,
                    'saldo_kg'    => $saldo_kg,
                    'lote'        => $lote,
                    'estado'      => null,
                    'salida'      => null,
                    'raw_qr'      => $raw_qr,
                    'id_usuario'  => $id_usuario,
                ]);

                $message  = 'Registro guardado correctamente. ';
                $message .= 'TARJA KG: ' . number_format($tarja_kg, 2, ',', '.') .
                            ' | SALDO KG: ' . number_format($saldo_kg, 2, ',', '.') .
                            ' | CONSUMO KG: ' . number_format($consumo_kg, 2, ',', '.');

                $raw_qr         = '';
                $np             = '';
                $saldo_in       = '';
                $descripcion_in = '';
                $codigo_in      = '';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Lectura de Tarjas QR</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="styles.css?v=20251105" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
    <h1 class="mb-4">CONSUMO PAPEL QR</h1>

    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" class="card card-body shadow-sm">
        <div class="mb-3">
            <label class="form-label"><strong>Escaneo de Código QR</strong></label>
            <div class="p-3 scan-wrap">
                <button type="button" id="btnToggleScan" class="btn btn-primary btn-scan">▶️ Iniciar escaneo</button>
                <div class="mt-3">
                    <video id="qrVideo" playsinline muted class="d-none"></video>
                    <canvas id="qrCanvas" class="d-none"></canvas>
                </div>
                <div class="mt-3">
                    <div class="muted">Resultado del último QR:</div>
                    <div id="scanText" class="scan-result text-break small border rounded p-2 bg-white">—</div>
                </div>
            </div>
        </div>

        <textarea id="raw_qr" name="raw_qr" class="d-none"><?php echo htmlspecialchars($raw_qr); ?></textarea>

        <div class="mb-3">
            <label for="np" class="form-label"><strong>NP</strong></label>
            <input type="text" id="np" name="np" class="form-control" value="<?php echo htmlspecialchars($np); ?>">
        </div>

        <div class="mb-3">
            <label for="saldo_kg" class="form-label"><strong>SALDO KG</strong></label>
            <input type="number" step="0.01" id="saldo_kg" name="saldo_kg" class="form-control"
                   value="<?php echo htmlspecialchars((string)$saldo_in); ?>" placeholder="0,00">
        </div>

        <div class="mb-3">
            <label for="descripcion" class="form-label"><strong>Descripción</strong></label>
            <textarea id="descripcion" name="descripcion" class="form-control" rows="2"><?php echo htmlspecialchars($descripcion_in); ?></textarea>
        </div>

        <div class="mb-3">
            <label for="codigo" class="form-label"><strong>Código</strong></label>
            <input type="text" id="codigo" name="codigo" class="form-control"
                   value="<?php echo htmlspecialchars($codigo_in); ?>">
        </div>

        <button type="submit" class="btn btn-primary">Guardar Registro</button>
        <a href="list.php" class="btn btn-secondary ms-2">Ver Registros</a>
    </form>
</div>

<script src="https://unpkg.com/jsqr@1.4.0/dist/jsQR.js"></script>
<script src="qr_scan.js?v=20251105"></script>

<!-- 🔵 Integración SAP automática mejorada -->
<script>
document.addEventListener('sap_autofill_ready', function(e) {
    const { lote, docnum } = e.detail;
    if (!lote && !docnum) return;

    // Construir URL con ambos parámetros si existen
    let url = `api/producto_por_lote.php?`;
    if (docnum) url += `docnum=${encodeURIComponent(docnum)}&`;
    if (lote) url += `lote=${encodeURIComponent(lote)}`;

    fetch(url)
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                document.getElementById('codigo').value = data.item_code || '';
                document.getElementById('descripcion').value = data.item_name || '';
                const info = document.createElement('div');
                info.className = 'alert alert-info mt-2';
                info.textContent = '✅ Datos completados desde SAP (' + data.source + ')';
                document.querySelector('form').prepend(info);
                setTimeout(() => info.remove(), 4000);
            } else {
                console.warn('SAP sin coincidencia:', data.error);
            }
        })
        .catch(err => console.error('Error SAP:', err));
});
</script>
</body>
</html>
