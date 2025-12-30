<?php
declare(strict_types=1);

/**
 * save_altillo_bulk.php
 * - Actualiza campos editables desde list_altillo.php
 * - Soporta PDO o mysqli (según lo que entregue config/db.php)
 * - No revienta si vienen campos vacíos: guarda NULL o string vacío según corresponda
 * - Siempre vuelve a list_altillo.php (PRG: Post/Redirect/Get)
 */

date_default_timezone_set('America/Santiago');

// IMPORTANTE: no imprimas nada antes de headers
ob_start();

require_once __DIR__ . '/../../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido');
}

// Base para redirigir SIEMPRE al listado
$redirectBase = dirname($_SERVER['HTTP_REFERER'] ?? '') ?: '../list_altillo.php';
$redirectOk   = '../list_altillo.php?ok=1';
$redirectErr  = '../list_altillo.php?err=1';

if (!isset($_POST['rows']) || !is_array($_POST['rows'])) {
    header('Location: ../list_altillo.php');
    exit;
}

// Detectar conexión disponible (según tu db.php)
$hasPdo    = (isset($pdo) && $pdo instanceof PDO);
$hasMysqli = (isset($db)  && $db  instanceof mysqli);

if (!$hasPdo && !$hasMysqli) {
    error_log('[ALTILLO] save_altillo_bulk: No hay PDO ni mysqli en db.php');
    header('Location: ' . $redirectErr);
    exit;
}

// Campos permitidos a actualizar desde el listado
$allowedFields = [
    'extra_nombre_1',
    'extra_nombre_2',
    'comentario',
    'estado',
    'extra_post_estado',
];

try {
    $updated = 0;

    /* =========================================================
     * PDO
     * ========================================================= */
    if ($hasPdo) {
        // Modo seguro
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $pdo->beginTransaction();

        foreach ($_POST['rows'] as $id => $row) {
            $id = (int)$id;
            if ($id <= 0 || !is_array($row)) continue;

            $setParts = [];
            $data     = [':id' => $id];

            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $row)) {
                    $setParts[] = "$field = :$field";

                    $val = trim((string)$row[$field]);

                    // Si viene vacío -> NULL (para que sea “libre”)
                    $data[":$field"] = ($val === '') ? null : $val;
                }
            }

            if (!$setParts) {
                continue;
            }

            $sql = "UPDATE altillo_scan SET " . implode(', ', $setParts) . ", updated_at = NOW() WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($data);

            $updated += $stmt->rowCount();
        }

        $pdo->commit();

        header('Location: ' . $redirectOk);
        exit;
    }

    /* =========================================================
     * mysqli fallback
     * ========================================================= */
    $db->set_charset('utf8mb4');

    // Preparamos 1 sentencia fija (más robusta). Si no existen esas columnas, aquí reventará:
    // en ese caso, el error_log te dirá exactamente cuál columna falta.
    $sql = "
        UPDATE altillo_scan SET
            extra_nombre_1    = ?,
            extra_nombre_2    = ?,
            comentario        = ?,
            estado            = ?,
            extra_post_estado = ?,
            updated_at        = NOW()
        WHERE id = ?
    ";

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('mysqli prepare failed: ' . $db->error);
    }

    foreach ($_POST['rows'] as $id => $row) {
        $id = (int)$id;
        if ($id <= 0 || !is_array($row)) continue;

        // “Libre”: si no viene el campo, lo dejamos como NULL para no forzar
        $v1 = array_key_exists('extra_nombre_1', $row) ? trim((string)$row['extra_nombre_1']) : null;
        $v2 = array_key_exists('extra_nombre_2', $row) ? trim((string)$row['extra_nombre_2']) : null;
        $v3 = array_key_exists('comentario', $row)     ? trim((string)$row['comentario'])     : null;
        $v4 = array_key_exists('estado', $row)         ? trim((string)$row['estado'])         : null;
        $v5 = array_key_exists('extra_post_estado', $row) ? trim((string)$row['extra_post_estado']) : null;

        // Vacío => NULL
        $v1 = ($v1 === '') ? null : $v1;
        $v2 = ($v2 === '') ? null : $v2;
        $v3 = ($v3 === '') ? null : $v3;
        $v4 = ($v4 === '') ? null : $v4;
        $v5 = ($v5 === '') ? null : $v5;

        $stmt->bind_param('sssssi', $v1, $v2, $v3, $v4, $v5, $id);

        if (!$stmt->execute()) {
            throw new RuntimeException('mysqli execute failed: ' . $stmt->error);
        }

        $updated += $stmt->affected_rows;
    }

    $stmt->close();

    header('Location: ' . $redirectOk);
    exit;

} catch (Throwable $e) {
    // rollback si aplica
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // log REAL del error (esto te va a decir por qué es el 500)
    error_log('[ALTILLO] save_altillo_bulk ERROR: ' . $e->getMessage());

    // Redirige igual al listado con error
    header('Location: ' . $redirectErr);
    exit;
}
