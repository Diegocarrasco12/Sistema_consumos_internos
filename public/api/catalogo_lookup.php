<?php
/**
 * catalogo_lookup.php
 *
 * Endpoint API — Busca descripción y código de producto a partir del QR leído.
 *
 * Flujo:
 * 1. Recibe el texto crudo del QR (POST['qr_raw']).
 * 2. Llama a Helpers\parse_qr() para obtener el código.
 * 3. Consulta en la base local (Models\SAPCatalog::findByCodeOrBarcode()).
 * 4. Devuelve JSON con los datos: código, descripción, empresa, uom.
 *
 * Si no se encuentra el producto, devuelve { "success": false }.
 */

declare(strict_types=1);

use Helpers;
use Models\SAPCatalog;

// ===============================
// AUTOLOAD & HEADERS
// ===============================
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/qr_parser.php';
require_once __DIR__ . '/../../models/SAPCatalog.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// ===============================
// VALIDACIÓN DE ENTRADA
// ===============================
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || empty($data['qr_raw'])) {
    echo json_encode([
        'success' => false,
        'error' => 'QR no recibido o formato inválido'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ===============================
// 1️⃣ PARSEAR QR
// ===============================
$parsed = Helpers\parse_qr($data['qr_raw']);
$codigo = $parsed['codigo'] ?? null;

if (!$codigo) {
    echo json_encode([
        'success' => false,
        'error' => 'No se pudo extraer código del QR'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ===============================
// 2️⃣ CONSULTAR BASE LOCAL
// ===============================
$result = SAPCatalog::findByCodeOrBarcode($codigo);

// ===============================
// 3️⃣ RESPUESTA FINAL
// ===============================
if ($result && !empty($result['item_code'])) {
    echo json_encode([
        'success'     => true,
        'codigo'      => $result['item_code'],
        'descripcion' => $result['item_name'] ?? null,
        'empresa'     => $result['empresa'] ?? null,
        'uom'         => $result['uom'] ?? null,
        'qr_raw'      => $data['qr_raw'], // opcional: para debugging
    ], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        'success' => false,
        'error'   => 'Producto no encontrado en catálogo local',
        'codigo_detectado' => $codigo
    ], JSON_UNESCAPED_UNICODE);
}
