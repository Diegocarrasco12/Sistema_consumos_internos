<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/db.php';

/* ===============================
| Filtros (IGUALES al listado)
|=============================== */
$filters = [
    'start_date' => $_GET['start_date'] ?? '',
    'end_date'   => $_GET['end_date'] ?? '',
    'codigo'     => $_GET['codigo'] ?? '',
    'lote'       => $_GET['lote'] ?? '',
];

/* ===============================
| WHERE dinámico
|=============================== */
$where  = [];
$params = [];

if ($filters['start_date']) {
    $where[] = 'fecha >= :start_date';
    $params[':start_date'] = $filters['start_date'];
}
if ($filters['end_date']) {
    $where[] = 'fecha <= :end_date';
    $params[':end_date'] = $filters['end_date'];
}
if ($filters['codigo']) {
    $where[] = 'codigo LIKE :codigo';
    $params[':codigo'] = '%' . $filters['codigo'] . '%';
}
if ($filters['lote']) {
    $where[] = 'lote LIKE :lote';
    $params[':lote'] = '%' . $filters['lote'] . '%';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

/* ===============================
| Consulta (SIN paginación)
|=============================== */
$sql = "
    SELECT
        fecha,
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
    ORDER BY fecha DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

/* ===============================
| HEADERS CORRECTOS PARA EXCEL
|=============================== */
$filename = 'altillo_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename=' . $filename);
header('Pragma: no-cache');
header('Expires: 0');

/* ===============================
| SALIDA
|=============================== */
echo "\xEF\xBB\xBF";              // BOM UTF-8 (CLAVE)
$out = fopen('php://output', 'w');

fwrite($out, "sep=;\n");          // FORZAR separador en Excel (CLAVE)

// Encabezados
fputcsv($out, [
    'Fecha',
    'Nombre',
    'Descripción',
    'Código',
    'Consumo',
    'NP',
    'Unidades Tarja',
    'Saldo',
    'Lote',
    'Comentario',
    'Estado'
], ';');

// Datos
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($out, [
        $row['fecha'],
        $row['nombre'],
        $row['descripcion'],
        $row['codigo'],
        $row['consumo'],
        $row['np'],
        $row['unidades_tarja'],
        $row['saldo'],
        $row['lote'],
        $row['comentario'],
        $row['estado']
    ], ';');
}

fclose($out);
exit;
