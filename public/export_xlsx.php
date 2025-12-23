<?php
/**
 * export_xlsx.php
 *
 * Exporta los registros de tarjas en formato Excel (.xlsx) o CSV universal (modo híbrido).
 * Compatible con PhpSpreadsheet 1.29+ pero totalmente funcional incluso sin extensiones ZIP/XML.
 */

declare(strict_types=1);

// ====== CARGA DE DEPENDENCIAS ======
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../models/TarjaScan.php';

use Models\TarjaScan;

// ====== CARGA CONDICIONAL DE PHPSPREADSHEET ======
$__autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($__autoloadPath)) {
    try {
        require_once $__autoloadPath;
    } catch (Throwable $e) {
        // Ignorar errores si Composer no está disponible
    }
}

// Determinar si PhpSpreadsheet está disponible y usable
$__hasSpreadsheet =
    class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet') &&
    extension_loaded('zip') &&
    extension_loaded('xmlwriter');

// ====== FILTROS ======
$filters = [
    'start_date' => $_GET['start_date'] ?? '',
    'end_date'   => $_GET['end_date']   ?? '',
    'codigo'     => $_GET['codigo']     ?? '',
    'lote'       => $_GET['lote']       ?? '',
];

// Obtener registros
$rows = TarjaScan::fetchAll($filters);

// Nombre base del archivo (sin extensión)
$filenameBase = 'export_tarjas_' . date('Ymd_His');

// ====== FUNCION AUXILIAR PARA EXPORTAR CSV/XLS UNIVERSAL ======
function exportFallbackCSV(array $rows, string $filenameBase, string $modo = 'XLSX_FALLBACK'): void {
    // Limpiar buffer previo
    while (ob_get_level() > 0) { ob_end_clean(); }

    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filenameBase . '.xls"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: public');

    $out = fopen('php://output', 'w');
    // BOM UTF-8 (para compatibilidad Excel)
    fwrite($out, "\xEF\xBB\xBF");

    // Encabezados
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

    // Filas
    foreach ($rows as $row) {
        $fecha = '';
        if (!empty($row['fecha'])) {
            $ts = strtotime((string)$row['fecha']);
            $fecha = $ts ? date('d/m/Y', $ts) : (string)$row['fecha'];
        }

        $consumo = number_format((float)($row['consumo_kg'] ?? 0), 2, ',', '');
        $tarja   = number_format((float)($row['tarja_kg']   ?? 0), 2, ',', '');
        $saldo   = number_format((float)($row['saldo_kg']   ?? 0), 2, ',', '');
        $csvRow = [
            $fecha,
            (string)($row['descripcion'] ?? ''),
            (string)($row['codigo']      ?? ''),
            $consumo,
            (string)($row['np']          ?? ''),
            $tarja,
            $saldo,
            (string)($row['lote']        ?? ''),
            (string)($row['estado']      ?? ''),
            (string)($row['salida']      ?? ''),
        ];
        fputcsv($out, $csvRow, ';');
    }

    fclose($out);
    exit;
}

// ====== MODO FALLBACK: SI NO HAY PHPSPREADSHEET DISPONIBLE ======
if (!$__hasSpreadsheet) {
    exportFallbackCSV($rows, $filenameBase, 'NO_SPREADSHEET');
}

// ====== MODO EXCEL REAL (.XLSX) ======
try {
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet       = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Tarjas');

    // Encabezados
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
    $sheet->fromArray($headers, null, 'A1');

    // Contenido
    $rowIdx = 2;
    foreach ($rows as $r) {
        $fechaStr = '';
        if (!empty($r['fecha'])) {
            $ts = strtotime((string)$r['fecha']);
            $fechaStr = $ts ? date('d/m/Y', $ts) : (string)$r['fecha'];
        }

        $consumo = (float)($r['consumo_kg'] ?? 0);
        $tarja   = (float)($r['tarja_kg']   ?? 0);
        $saldo   = (float)($r['saldo_kg']   ?? 0);

        $sheet->fromArray([
            $fechaStr,
            (string)($r['descripcion'] ?? ''),
            (string)($r['codigo']      ?? ''),
            $consumo,
            (string)($r['np']          ?? ''),
            $tarja,
            $saldo,
            (string)($r['lote']        ?? ''),
            (string)($r['estado']      ?? ''),
            (string)($r['salida']      ?? ''),
        ], null, 'A' . $rowIdx);
        $rowIdx++;
    }

    $lastRow = max(2, $rowIdx - 1);

    // ====== ESTILOS ======
    $headerRange = "A1:J1";
    $sheet->getStyle($headerRange)->getFont()
        ->setBold(true)
        ->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFFFF'));
    $sheet->getStyle($headerRange)->getFill()
        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
        ->getStartColor()->setARGB('FF0B5ED7');
    $sheet->getStyle($headerRange)->getAlignment()
        ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
        ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    $sheet->getRowDimension(1)->setRowHeight(24);

    $allRange = "A1:J{$lastRow}";
    $sheet->getStyle($allRange)->getBorders()->getAllBorders()
        ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)
        ->getColor()->setARGB('FFCCCCCC');

    foreach (range('A', 'J') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    $sheet->setAutoFilter($allRange);
    $sheet->freezePane('A2');

    if ($lastRow >= 2 && class_exists('PhpOffice\\PhpSpreadsheet\\Worksheet\\Table')) {
        try {
            $table = new \PhpOffice\PhpSpreadsheet\Worksheet\Table("A1:J{$lastRow}");
            $table->setName('TablaTarjas');
            $table->setStyle(\PhpOffice\PhpSpreadsheet\Worksheet\Table::TABLE_STYLE_MEDIUM9);
            $sheet->addTable($table);
        } catch (Throwable $e) { /* ignorar */ }
    }

    // ====== SALIDA XLSX ======
    while (ob_get_level() > 0) { ob_end_clean(); }

    $filename = $filenameBase . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (Throwable $e) {
    // ====== FALLBACK AUTOMÁTICO SI FALLA XLSX ======
    exportFallbackCSV($rows, $filenameBase, 'THROWABLE_FALLBACK');
}
