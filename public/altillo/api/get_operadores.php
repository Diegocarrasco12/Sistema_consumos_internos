<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../config/db.php';

try {
    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new Exception('Conexión PDO no disponible');
    }

    $sql = "
        SELECT nombre
        FROM operadores
        WHERE activo = 1
        ORDER BY nombre
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Normalizar salida (seguridad visual / consistencia)
    $out = [];
    foreach ($rows as $r) {
        if (!isset($r['nombre'])) {
            continue;
        }
        $out[] = [
            'nombre' => (string)$r['nombre']
        ];
    }

    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'msg'   => 'Error al obtener operadores',
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
