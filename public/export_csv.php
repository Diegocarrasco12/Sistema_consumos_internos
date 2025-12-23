<?php
/**
 * export_csv.php
 *
 * Genera un archivo CSV con los registros de tarjas en el orden de columnas:
 * FECHA | DESCRIPCION | CODIGO | CONSUMO KG | NP | TARJA KG | SALDO KG | LOTE | ESTADO | SALIDA
 *
 * Se admiten filtros por fecha, código y lote.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../models/TarjaScan.php';

use Models\TarjaScan;

// === Filtros desde la query ===
$filters = [
    'start_date' => $_GET['start_date'] ?? '',
    'end_date'   => $_GET['end_date']   ?? '',
    'codigo'     => $_GET['codigo']     ?? '',
    'lote'       => $_GET['lote']       ?? '',
];

$rows = TarjaScan::fetchAllNoLimit($filters);

// === Nombre de archivo ===
$filename = 'export_tarjas_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Salida estándar
$out = fopen('php://output', 'w');

// BOM UTF-8 para que Excel Windows abra correctamente
fwrite($out, "\xEF\xBB\xBF");

// === Encabezados (SIN columna vacía) ===
$headers = [
    'FECHA',
    'DESCRIPCION',
    'CODIGO',
    'CONSUMO KG',
    'NP',
    'TARJA KG',
    'SALDO KG',
    'LOTE',
    'ESTADO',
    'SALIDA',
];
fputcsv($out, $headers, ';');

// === Filas ===
foreach ($rows as $row) {
    // FECHA como dd/mm/yyyy
    $fecha = '';
    if (!empty($row['fecha'])) {
        $ts = strtotime((string)$row['fecha']);
        $fecha = $ts ? date('d/m/Y', $ts) : (string)$row['fecha'];
    }

    // Números con coma decimal y SIN separador de miles
    $consumo = number_format((float)($row['consumo_kg'] ?? 0), 2, ',', '');
    $tarja   = number_format((float)($row['tarja_kg'] ?? 0),   2, ',', '');
    $saldo   = number_format((float)($row['saldo_kg'] ?? 0),   2, ',', '');

    // Campos texto (evitar nulls)
    $descripcion = (string)($row['descripcion'] ?? '');
    $codigo      = (string)($row['codigo'] ?? '');
    $np          = (string)($row['np'] ?? '');
    $lote        = (string)($row['lote'] ?? '');
    $estado      = (string)($row['estado'] ?? '');
    $salida      = (string)($row['salida'] ?? '');

    $csvRow = [
        $fecha,
        $descripcion,
        $codigo,
        $consumo,
        $np,
        $tarja,
        $saldo,
        $lote,
        $estado,
        $salida,
    ];

    fputcsv($out, $csvRow, ';');
}

fclose($out);
exit;
