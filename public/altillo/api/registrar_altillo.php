<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/../../../config/db.php';

try {

    // ===============================
    // 1) DATOS POST
    // ===============================
    $operador   = trim($_POST['operador'] ?? '');
    $np         = trim($_POST['np'] ?? '');
    $codigo     = trim($_POST['codigo_producto'] ?? '');
    $descripcion= trim($_POST['descripcion_producto'] ?? '');
    $lote       = trim($_POST['lote'] ?? '');
    $rawQr      = trim($_POST['raw_qr'] ?? '');

    $saldo      = floatval($_POST['saldo_unidades'] ?? 0);
    $consumo    = floatval($_POST['consumo_unidades'] ?? 0);

    // ===============================
    // 2) VALIDACIONES BÁSICAS
    // ===============================
    if ($operador === '' || $np === '' || $codigo === '' || $consumo <= 0) {
        echo json_encode([
            'ok'  => false,
            'msg' => 'Datos obligatorios incompletos'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ===============================
    // 3) INSERT
    // ===============================
    $sql = "
        INSERT INTO altillo_scan
        (
            fecha,
            nombre,
            codigo,
            descripcion,
            cantidad,
            np,
            lote,
            docnum,
            comentario,
            estado,
            salida,
            raw_qr,
            created_at,
            updated_at
        )
        VALUES
        (
            CURDATE(),
            :nombre,
            :codigo,
            :descripcion,
            :cantidad,
            :np,
            :lote,
            NULL,
            NULL,
            'OK',
            1,
            :raw_qr,
            NOW(),
            NOW()
        )
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':nombre'      => $operador,
        ':codigo'      => $codigo,
        ':descripcion' => $descripcion,
        ':cantidad'    => $consumo,
        ':np'          => $np,
        ':lote'        => $lote,
        ':raw_qr'      => $rawQr,
    ]);

    // ===============================
    // 4) RESPUESTA OK
    // ===============================
    echo json_encode([
        'ok' => true,
        'msg' => 'Registro guardado correctamente'
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'msg' => 'Error al guardar registro',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
