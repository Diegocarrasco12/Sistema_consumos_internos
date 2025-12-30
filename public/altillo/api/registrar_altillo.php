<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

/* ===============================
| Errores (NO mostrar en producción)
|=============================== */
error_reporting(E_ALL);
ini_set('display_errors', '0');

/* ===============================
| Timezone (CRÍTICO)
|=============================== */
date_default_timezone_set('America/Santiago');

/* ===============================
| DB
|=============================== */
require_once __DIR__ . '/../../../config/db.php';

try {

    /* ===============================
     * Verificar conexión disponible
     * =============================== */
    $hasPdo    = (isset($pdo) && $pdo instanceof PDO);
    $hasMysqli = (isset($db)  && $db  instanceof mysqli);

    if (!$hasPdo && !$hasMysqli) {
        throw new Exception('No hay conexión disponible (PDO ni mysqli)');
    }

    /* ===============================
     * 1) DATOS POST (NORMALIZADOS)
     * =============================== */
    $operadorRaw = trim((string)($_POST['operador'] ?? ''));
    $np          = trim((string)($_POST['np'] ?? ''));
    $codigo      = trim((string)($_POST['codigo'] ?? ''));
    $descripcion = trim((string)($_POST['descripcion'] ?? ''));
    $lote        = trim((string)($_POST['lote'] ?? ''));
    $rawQr       = trim((string)($_POST['raw_qr'] ?? ''));

    $unidadesTarja = (int) round((float)($_POST['unidades_tarja'] ?? 0));
    $saldo         = (int) round((float)($_POST['saldo_unidades'] ?? 0));
    $consumo       = (int) round((float)($_POST['consumo_unidades'] ?? 0));

    /* ===============================
     * 2) VALIDACIONES DURAS
     * =============================== */
    if ($operadorRaw === '' || $np === '' || $codigo === '') {
        throw new Exception('Operador, NP o Código vacío');
    }

    if ($unidadesTarja <= 0 || $consumo <= 0 || $saldo < 0) {
        throw new Exception('Valores numéricos inválidos');
    }

    // Limitar longitudes (evita errores SQL en producción)
    $operadorRaw = mb_substr($operadorRaw, 0, 100);
    $np          = mb_substr($np, 0, 50);
    $codigo      = mb_substr($codigo, 0, 50);
    $descripcion = mb_substr($descripcion, 0, 255);
    $lote        = mb_substr($lote, 0, 50);
    $rawQr       = mb_substr($rawQr, 0, 1000);

    /* ===============================
     * 3) Normalizar operador (SIN romper prod)
     * =============================== */
    if (function_exists('mb_strtolower')) {
        $operador = mb_strtolower($operadorRaw, 'UTF-8');
    } else {
        $operador = strtolower($operadorRaw); // fallback producción
    }

    /* =========================================================
     * 4) PDO (FLUJO PRINCIPAL)
     * ========================================================= */
    if ($hasPdo) {

        $pdo->beginTransaction();

        // Buscar operador
        $stmt = $pdo->prepare("SELECT id FROM operadores WHERE nombre = :nombre LIMIT 1");
        $stmt->execute([':nombre' => $operador]);
        $idOp = $stmt->fetchColumn();

        // Insertar operador si no existe
        if (!$idOp) {
            $stmt = $pdo->prepare("
                INSERT INTO operadores (nombre, activo, created_at)
                VALUES (:nombre, 1, NOW())
            ");
            $stmt->execute([':nombre' => $operador]);
        }

        // Insert altillo_scan
        $stmt = $pdo->prepare("
            INSERT INTO altillo_scan
            (fecha, nombre, codigo, descripcion, unidades_tarja, consumo, saldo, np, lote,
             comentario, estado, salida, raw_qr, created_at, updated_at)
            VALUES
            (CURDATE(), :nombre, :codigo, :descripcion, :unidades_tarja, :consumo, :saldo,
             :np, :lote, NULL, 'OK', 1, :raw_qr, NOW(), NOW())
        ");

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

        $pdo->commit();

        echo json_encode(['ok' => true, 'msg' => 'Registro guardado correctamente'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* =========================================================
     * 5) mysqli fallback (PRODUCCIÓN ANTIGUA)
     * ========================================================= */
    $db->set_charset('utf8mb4');

    // Buscar operador
    $stmt = $db->prepare("SELECT id FROM operadores WHERE nombre = ? LIMIT 1");
    $stmt->bind_param("s", $operador);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        $stmt = $db->prepare("
            INSERT INTO operadores (nombre, activo, created_at)
            VALUES (?, 1, NOW())
        ");
        $stmt->bind_param("s", $operador);
        $stmt->execute();
        $stmt->close();
    }

    // Insert altillo_scan
    $stmt = $db->prepare("
        INSERT INTO altillo_scan
        (fecha, nombre, codigo, descripcion, unidades_tarja, consumo, saldo, np, lote,
         comentario, estado, salida, raw_qr, created_at, updated_at)
        VALUES
        (CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, NULL, 'OK', 1, ?, NOW(), NOW())
    ");

    $stmt->bind_param(
        "sssiiisss",
        $operador,
        $codigo,
        $descripcion,
        $unidadesTarja,
        $consumo,
        $saldo,
        $np,
        $lote,
        $rawQr
    );

    $stmt->execute();
    $stmt->close();

    echo json_encode(['ok' => true, 'msg' => 'Registro guardado correctamente'], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {

    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'msg'   => 'Error al guardar registro',
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
