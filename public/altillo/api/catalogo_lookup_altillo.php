<?php
header('Content-Type: application/json; charset=utf-8');

// ================== SEGURIDAD BÁSICA ==================
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST'], true)) {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Método no permitido']);
    exit;
}

// ================== INPUT ==================
$codigo = $_GET['codigo'] ?? $_POST['codigo'] ?? '';
$codigo = trim($codigo);

if ($codigo === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'Falta parámetro: codigo']);
    exit;
}

// Limpiar a solo dígitos
$codigoLimpio = preg_replace('/\D+/', '', $codigo);

if ($codigoLimpio === '' || strlen($codigoLimpio) < 6) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'msg' => 'Código inválido',
        'codigo_recibido' => $codigo
    ]);
    exit;
}

// ================== DB ==================
require_once __DIR__ . '/../../../config/db.php';

try {
    // Usamos el helper oficial del sistema
    $rows = db_select(
        "SELECT caja, codigo, descripcion, activo
         FROM altillo_catalogo
         WHERE codigo = ?
         LIMIT 1",
        [$codigoLimpio]
    );

    if (empty($rows)) {
        echo json_encode([
            'ok' => true,
            'found' => false,
            'codigo' => $codigoLimpio
        ]);
        exit;
    }

    $row = $rows[0];

    echo json_encode([
        'ok' => true,
        'found' => true,
        'data' => [
            'caja' => $row['caja'],
            'codigo' => $row['codigo'],
            'descripcion' => $row['descripcion'],
            'activo' => (int)$row['activo'],
        ]
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'msg' => 'Error servidor',
        'error' => $e->getMessage()
    ]);
}
