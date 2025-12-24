<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido');
}

if (!isset($_POST['rows']) || !is_array($_POST['rows'])) {
    header('Location: list_altillo.php');
    exit;
}

try {
    $pdo->beginTransaction();

    $sql = "
        UPDATE altillo_scan SET
            extra_nombre_1     = :extra_nombre_1,
            extra_nombre_2     = :extra_nombre_2,
            comentario         = :comentario,
            estado             = :estado,
            extra_post_estado  = :extra_post_estado,
            updated_at         = NOW()
        WHERE id = :id
    ";

    $stmt = $pdo->prepare($sql);

    foreach ($_POST['rows'] as $id => $row) {

        // Seguridad mínima
        $id = (int)$id;
        if ($id <= 0) {
            continue;
        }

        $stmt->execute([
            ':extra_nombre_1'    => $row['extra_nombre_1']    ?? null,
            ':extra_nombre_2'    => $row['extra_nombre_2']    ?? null,
            ':comentario'        => $row['comentario']        ?? null,
            ':estado'            => $row['estado']            ?? null,
            ':extra_post_estado' => $row['extra_post_estado'] ?? null,
            ':id'                => $id,
        ]);
    }

    $pdo->commit();

    header('Location: list_altillo.php?ok=1');
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo 'Error al guardar cambios: ' . $e->getMessage();
    exit;
}
