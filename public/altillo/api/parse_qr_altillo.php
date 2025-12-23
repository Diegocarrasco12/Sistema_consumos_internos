<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

// No mostrar errores en pantalla
error_reporting(E_ALL);
ini_set('display_errors', '0');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST'], true)) {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = $_POST['qr'] ?? $_GET['qr'] ?? '';
$raw = trim((string)$raw);

if ($raw === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'QR vacío'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Ruta REAL al helper
$helper = realpath(__DIR__ . '/../../../helpers/qr_parser_altillo.php');
if (!$helper || !is_file($helper)) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'msg' => 'No se encuentra helper qr_parser_altillo.php',
        'expected' => __DIR__ . '/../../../helpers/qr_parser_altillo.php',
        'realpath' => $helper ?: null,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once $helper;

try {
    // 🔴 USAMOS EL HELPER TAL CUAL (CON data)
    $parsed = parse_qr_altillo($raw);

    // 🔴 NO APLANAR
    // 🔴 NO array_merge
    // 🔴 NO unset(data)

    echo json_encode($parsed, JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'msg' => 'Error servidor',
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
