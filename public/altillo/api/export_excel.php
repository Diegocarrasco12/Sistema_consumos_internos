<?php

declare(strict_types=1);

ob_start();
date_default_timezone_set('America/Santiago');

require_once __DIR__ . '/../../../config/db.php';

/* ===============================
| Filtros
|=============================== */
$filters = [
    'start_date' => $_GET['start_date'] ?? '',
    'end_date'   => $_GET['end_date']   ?? '',
    'codigo'     => $_GET['codigo']     ?? '',
    'lote'       => $_GET['lote']       ?? '',
];

/* ===============================
| WHERE dinámico (MISMO PATRÓN list_altillo)
|=============================== */
$where  = [];
$params = [];

if ($filters['start_date'] !== '') {
    $where[]  = 'DATE(created_at) >= ?';
    $params[] = $filters['start_date'];
}

if ($filters['end_date'] !== '') {
    $where[]  = 'DATE(created_at) <= ?';
    $params[] = $filters['end_date'];
}

if ($filters['codigo'] !== '') {
    $where[]  = 'codigo LIKE ?';
    $params[] = '%' . $filters['codigo'] . '%';
}

if ($filters['lote'] !== '') {
    $where[]  = 'lote LIKE ?';
    $params[] = '%' . $filters['lote'] . '%';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

/* ===============================
| CONSULTA (MISMA DE SIEMPRE)
|=============================== */
$sql = "
    SELECT
        created_at,
        nombre,
        descripcion,
        codigo,
        consumo,
        np,
        unidades_tarja,
        saldo,
        lote,
        comentario,
        estado
    FROM altillo_scan
    $whereSql
    ORDER BY created_at DESC
";

$rows = db_select($sql, $params);

/* ===============================
| HEADERS CSV (Excel-safe)
|=============================== */
ob_clean();

$filename = 'altillo_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=Windows-1252');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');

// Forzar separador en Excel
fwrite($out, "sep=;\n");

// Encabezados
fputcsv($out, [
    'Fecha / Hora',
    'Nombre',
    'Descripcion',
    'Codigo',
    'Consumo',
    'NP',
    'Unid. Tarja',
    'Saldo',
    'Lote',
    'Comentario',
    'Estado'
], ';');

function excel_text($value): string
{
    if ($value === null) {
        return '';
    }

    // Convertir de UTF-8 → Windows-1252 (Excel friendly)
    return mb_convert_encoding((string)$value, 'Windows-1252', 'UTF-8');
}

/* ===============================
| Datos
|=============================== */
foreach ($rows as $r) {
    fputcsv($out, [
        isset($r['created_at'])
            ? date('d-m-Y H:i:s', strtotime((string)$r['created_at']))
            : '',
        excel_text($r['nombre'] ?? ''),
        excel_text($r['descripcion'] ?? ''),
        // Código como TEXTO para Excel
        isset($r['codigo']) ? '="' . $r['codigo'] . '"' : '',
        $r['consumo'] ?? '',
        excel_text($r['np'] ?? ''),
        $r['unidades_tarja'] ?? '',
        $r['saldo'] ?? '',
        excel_text($r['lote'] ?? ''),
        excel_text($r['comentario'] ?? ''),
        excel_text($r['estado'] ?? ''),
    ], ';');
}


fclose($out);
exit;
