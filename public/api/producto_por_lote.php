<?php
declare(strict_types=1);

// ======== Cabeceras JSON ========
header('Content-Type: application/json; charset=utf-8');

/**
 * Endpoint de autocompletado para lector de tarjas.
 *
 * Modos de consulta:
 *  - ?docnum=NNNN              → busca el primer ítem del documento (numérico; acepta 0003889, 3889, etc.)
 *  - ?lote=XXXX-YY[&empresa=FARET|INNPACK] → busca por lote directamente en OBTN
 *  - ?qr=CODIGOQRCRUDO         → decodifica el QR, extrae docnum/lote y realiza la consulta automáticamente
 *
 * Respuesta esperada:
 * {
 *   ok: true,
 *   item_code: "1145SC...",
 *   item_name: "PAPEL TEST LINER ...",
 *   uom: "kg",
 *   lote: "3889-20",
 *   empresa: "INNPACK",
 *   source: "INNPACK"
 * }
 *
 * 🔵 NUEVO:
 * Si SAP no devuelve resultados, se intenta buscar el producto en la base local `catalogo_sap_local`
 * a partir del código decodificado del QR.
 */

// ===== Bootstrap mínimo =====
// Cargar autoload de Composer solo si existe. Esto permite que el endpoint
// funcione tanto en entornos donde Composer está disponible como en otros
// donde no se han instalado dependencias adicionales.
$__autoload = __DIR__ . '/../../vendor/autoload.php';
if (file_exists($__autoload)) {
    try {
        require_once $__autoload;
    } catch (Throwable $e) {
        // Ignorar errores de carga: el endpoint seguirá funcionando sin dependencias
    }
}
require_once __DIR__ . '/../../config/db.php';           // Carga conexión y sap_select()
require_once __DIR__ . '/../../models/SAPCatalog.php';   // Modelo de lectura SAP
require_once __DIR__ . '/../../helpers/qr_parser.php';   // Parser del QR

use Models\SAPCatalog;
use Helpers\parse_qr;

try {
    // ====== Parámetros entrantes ======
    $docnum = isset($_GET['docnum']) ? trim((string) $_GET['docnum']) : '';
    $lote   = isset($_GET['lote'])   ? trim((string) $_GET['lote'])   : '';
    $qrRaw  = isset($_GET['qr'])     ? trim((string) $_GET['qr'])     : '';
    $debug  = isset($_GET['debug'])  ? (bool) intval($_GET['debug'])  : false;

    if (!function_exists('sap_select')) {
        throw new RuntimeException('El helper sap_select() no está cargado. Asegúrate de incluir config/db.php');
    }

    /**
     * 🔸 Helper local: búsqueda en catálogo_sap_local
     */
    function buscar_en_catalogo_local(string $codigo): ?array {
        global $conn; // conexión local definida en config/db.php

        if (!$conn || $codigo === '') return null;

        // Normalizar el código (quita espacios y ceros innecesarios)
        $codigo = trim($codigo);

        $sql = "SELECT empresa, item_code, item_name, uom 
                FROM catalogo_sap_local 
                WHERE item_code = ? OR codebars = ? 
                LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ss', $codigo, $codigo);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            return [
                'empresa'   => $row['empresa'] ?? 'LOCAL',
                'item_code' => $row['item_code'],
                'item_name' => $row['item_name'],
                'uom'       => $row['uom'] ?? 'kg',
                'source'    => 'LOCAL',
            ];
        }

        return null;
    }

    // ====== 1) Si se envía un QR completo ======
    if ($qrRaw !== '') {
        $parsed = \Helpers\parse_qr($qrRaw);

        // Intentamos extraer los datos relevantes del QR
        $docnum = $parsed['docnum'] ?? '';
        $lote   = $parsed['lote']   ?? '';
        $codigo = $parsed['codigo'] ?? null;

        // Si tenemos docnum, priorizamos esa búsqueda
        if (!empty($docnum)) {
            $docnumInt = (int) preg_replace('/\D+/', '', $docnum);
            $data = SAPCatalog::findByDocnum($docnumInt, $debug);
        }
        // Si no hay docnum pero sí lote
        elseif (!empty($lote)) {
            $data = SAPCatalog::findByBatchFlexible($lote, null, $debug);
        } else {
            echo json_encode(['ok' => false, 'error' => 'INVALID_QR', 'raw' => $qrRaw], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Modo debug
        if ($debug && is_array($data) && array_key_exists('sql', $data)) {
            echo json_encode(['ok' => true, 'debug' => $data, 'parsed' => $parsed], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Si SAP no devuelve datos → buscar localmente
        if (!$data || !isset($data['item_code'])) {
            if (!empty($codigo)) {
                $localData = buscar_en_catalogo_local($codigo);
                if ($localData) {
                    $localData['parsed'] = $parsed;
                    echo json_encode(['ok' => true] + $localData, JSON_UNESCAPED_UNICODE);
                    exit;
                }
            }

            echo json_encode(['ok' => false, 'error' => 'NOT_FOUND_QR', 'parsed' => $parsed], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Respuesta final SAP
        $data['source'] = !empty($data['empresa']) ? $data['empresa'] : 'SAP';
        $data['parsed'] = $parsed;
        echo json_encode(['ok' => true] + $data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ====== 2) Consulta por DocNum ======
    if ($docnum !== '') {
        $docnumInt = (int) preg_replace('/\D+/', '', $docnum);
        $data = SAPCatalog::findByDocnum($docnumInt, $debug);

        // Modo debug
        if ($debug && is_array($data) && array_key_exists('sql', $data)) {
            echo json_encode(['ok' => true, 'debug' => $data], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Si SAP no devuelve datos → buscar localmente
        if (!$data || !isset($data['item_code'])) {
            $localData = buscar_en_catalogo_local($docnum);
            if ($localData) {
                echo json_encode(['ok' => true] + $localData, JSON_UNESCAPED_UNICODE);
                exit;
            }

            echo json_encode(['ok' => false, 'error' => 'NOT_FOUND_DOCNUM', 'docnum' => $docnum], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $data['source'] = !empty($data['empresa']) ? $data['empresa'] : 'SAP';
        echo json_encode(['ok' => true] + $data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ====== 3) Consulta directa por Lote ======
    if ($lote !== '') {
        $empresa = null;
        if (isset($_GET['empresa'])) {
            $emp = strtoupper(trim((string) $_GET['empresa']));
            if (in_array($emp, ['FARET', 'INNPACK'], true)) {
                $empresa = $emp;
            }
        }

        $data = SAPCatalog::findByBatchFlexible($lote, $empresa, $debug);

        // Modo debug
        if ($debug && is_array($data) && array_key_exists('sql', $data)) {
            echo json_encode(['ok' => true, 'debug' => $data], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Si SAP no devuelve datos → buscar localmente
        if (!$data || !isset($data['item_code'])) {
            $localData = buscar_en_catalogo_local($lote);
            if ($localData) {
                echo json_encode(['ok' => true] + $localData, JSON_UNESCAPED_UNICODE);
                exit;
            }

            echo json_encode(['ok' => false, 'error' => 'NOT_FOUND_LOTE', 'lote' => $lote], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $data['source'] = $empresa ?: 'SAP';
        echo json_encode(['ok' => true] + $data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ====== 4) Falta de parámetros ======
    echo json_encode([
        'ok'    => false,
        'error' => 'MISSING_PARAMS',
        'hint'  => 'Use ?qr=CRUDO, ?docnum=NNNN o ?lote=XXXX-YY'
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => 'SERVER_ERROR',
        'msg'   => $e->getMessage(),
        'trace' => $e->getFile() . ':' . $e->getLine(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
