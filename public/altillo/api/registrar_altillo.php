<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/../../../config/db.php';

try {

    /* ===============================
     * 1) DATOS POST
     * =============================== */
    $operador       = trim($_POST['operador'] ?? '');
    $np             = trim($_POST['np'] ?? '');
    $codigo         = trim($_POST['codigo'] ?? '');
    $descripcion    = trim($_POST['descripcion'] ?? '');
    $lote           = trim($_POST['lote'] ?? '');
    $rawQr          = trim($_POST['raw_qr'] ?? '');

    // Valores numéricos (ENTEROS reales)
    $unidadesTarja  = (int) round(floatval($_POST['unidades_tarja'] ?? 0));
    $saldo          = (int) round(floatval($_POST['saldo_unidades'] ?? 0));
    $consumo        = (int) round(floatval($_POST['consumo_unidades'] ?? 0));

    /* ===============================
     * 2) VALIDACIONES
     * =============================== */
    if (
        $operador === '' ||
        $np === '' ||
        $codigo === '' ||
        $unidadesTarja <= 0 ||
        $consumo <= 0 ||
        $saldo < 0
    ) {
        echo json_encode([
            'ok'  => false,
            'msg' => 'Datos obligatorios incompletos o inválidos'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    /* ===============================
 * 2.5) ASEGURAR OPERADOR EN DB
 * =============================== */

    // normalizamos (evita duplicados por mayúsculas/espacios)
    $operador = mb_strtolower(trim($operador), 'UTF-8');

    // inserta solo si no existe (gracias al UNIQUE en operadores.nombre)
    $sqlOperador = "
    INSERT IGNORE INTO operadores (nombre, activo, created_at)
    VALUES (:nombre, 1, NOW())
";

    $stmtOp = $pdo->prepare($sqlOperador);
    $stmtOp->execute([
        ':nombre' => $operador
    ]);


    /* ===============================
     * 3) INSERT
     * =============================== */
    $sql = "
        INSERT INTO altillo_scan
        (
            fecha,
            nombre,
            codigo,
            descripcion,
            unidades_tarja,
            consumo,
            saldo,
            np,
            lote,
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
            :unidades_tarja,
            :consumo,
            :saldo,
            :np,
            :lote,
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
        ':nombre'         => $operador,
        ':codigo'         => $codigo,
        ':descripcion'    => $descripcion,
        ':unidades_tarja' => $unidadesTarja,
        ':consumo'        => $consumo,
        ':saldo'          => $saldo,
        ':np'             => $np,
        ':lote'           => $lote,
        ':raw_qr'         => $rawQr,
    ]);

    /* ===============================
     * 4) RESPUESTA OK
     * =============================== */
    echo json_encode([
        'ok'  => true,
        'msg' => 'Registro guardado correctamente'
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'msg'   => 'Error al guardar registro',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
