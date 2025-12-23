<?php

/**
 * qr_parser.php
 *
 * Parser para QRs de tarjas enfocado en dejar "CÓDIGO" y "DESCRIPCIÓN"
 * para ingreso manual por el operador o, si es posible, decodificar el código
 * a partir del patrón numérico encontrado en el QR.
 *
 * Extrae automáticamente:
 *  - lote      (p.ej. 3821-15; acepta ceros a la izquierda en el QR)
 *  - tarja_kg  (p.ej. 1654,00; acepta "1.654,00", "1654,00", heurística desde bloque A)
 *  - docnum    (p.ej. 911000048; número de documento SAP después del #)
 *
 * Decodifica:
 *  - codigo    (a partir del bloque numérico inicial del QR)
 *
 * NUEVO:
 *  - Motor de lote inteligente que corrige casos como "000010108-04" → "108-04".
 *  - La lógica clásica sigue existiendo como fallback sin interferir.
 */

declare(strict_types=1);

namespace Helpers;

/**
 * Normaliza un string de QR (limpia saltos y espacios repetidos).
 */
function _qr_normalize(string $raw): string
{
    $s = str_replace(["\r", "\n", "\t"], ' ', $raw);
    $s = preg_replace('/\s+/', ' ', $s ?? '');
    return trim($s);
}

/**
 * MOTOR NUEVO PARA DETECTAR LOTES
 *
 * Detecta lotes con ceros, basura o formateos extraños:
 *   000010108-04   → 108-04
 *   0000103546-61  → 3546-61
 *   00001095-09    → 95-09
 *
 * Este motor es PRIORITARIO. Solo si NO detecta lote, se ocupa el motor clásico.
 */
function detectar_lote_inteligente(string $s): ?string
{
    // Buscamos patrones del tipo: ,000010xxx-yy   o   000010xxx-yy#
    if (!preg_match('/0+1?0*(\d{2,6})-(\d{1,4})/', $s, $m)) {
        return null;
    }

    $num = ltrim($m[1], '0');   // 0108 → 108
    $suf = $m[2];

    // Casos como 1095-09 → 95-09
    if (preg_match('/^10(\d{2,})$/', $num, $mm)) {
        $num = $mm[1];
    }

    return $num . '-' . $suf;
}

/**
 * Parser principal del QR.
 */
function parse_qr(string $raw): array
{
    $s = _qr_normalize($raw);

    $out = [
        'codigo'      => null,
        'descripcion' => null,
        'lote'        => null,
        'tarja_kg'    => null,
        'docnum'      => null,
    ];

    if ($s === '') {
        return $out;
    }

    // ============================================================
    // 1) DECODIFICAR CÓDIGO (ItemCode)
    // ============================================================

    if (strpos($s, ',') !== false) {
        [$bloqueA] = explode(',', $s, 2);
        $bloqueA = preg_replace('/\D+/', '', (string) $bloqueA);

        if ($bloqueA !== '') {
            $codigoDecodificado = decodificar_codigo_qr($bloqueA);
            if ($codigoDecodificado !== '' && $codigoDecodificado !== null) {
                $out['codigo'] = $codigoDecodificado;
            }
        }
    }


    // ============================================================
    // 2) LOTE — MOTOR NUEVO (PRIORITARIO)
    //     (solo si NO fue resuelto por excepción)
    // ============================================================

    if ($out['lote'] === null) {

        $loteNuevo = detectar_lote_inteligente($s);

        if ($loteNuevo !== null) {
            $out['lote'] = $loteNuevo;
        } else {

            // ============================================================
            // MOTOR CLÁSICO (FALLBACK)
            // ============================================================

            if (preg_match('/(?:^|[^\d])0*(\d{3,6}-\d{1,4})(?:[^\d]|$)/', $s, $m)) {

                $lote = ltrim($m[1], '0');
                [$num, $suf] = explode('-', $lote, 2);

                // Corrección 10xxx → xxx
                if (preg_match('/^10(\d{2,})$/', $num, $mm)) {
                    $num = $mm[1];
                }

                $out['lote'] = $num . '-' . $suf;
            }
        }
    }


    // ============================================================
    // 3) TARJA_KG explícito
    // ============================================================

    if ($out['tarja_kg'] === null) {
        if (preg_match('/\b([\d\.]{1,12},\d{2})\s*(?:kg)?\b/i', $s, $m)) {
            $num = str_replace('.', '', $m[1]);
            $num = str_replace(',', '.', $num);
            $out['tarja_kg'] = (float) $num;
        }
    }

    // ============================================================
    // 4) TARJA_KG heurística (desde bloque A)
    // ============================================================

    if ($out['tarja_kg'] === null && strpos($s, ',') !== false) {
        [$bloqueA] = explode(',', $s, 2);
        $bloqueA = preg_replace('/\D+/', '', (string) $bloqueA);

        if ($bloqueA !== '') {
            $last4 = (int) substr($bloqueA, -4);
            if ($last4 >= 100 && $last4 <= 5000) {
                $out['tarja_kg'] = (float) $last4;
            } else {
                $last3 = (int) substr($bloqueA, -3);
                if ($last3 >= 50 && $last3 <= 999) {
                    $out['tarja_kg'] = (float) $last3;
                }
            }
        }
    }

    // ============================================================
    // 5) DOCNUM
    // ============================================================

    if (preg_match('/#(\d{6,})/', $s, $m)) {
        $out['docnum'] = $m[1];
    }

    return $out;
}

/**
 * decodificar_codigo_qr()
 *
 * Convierte el bloque numérico del QR al formato SAP (ItemCode) usando:
 *  - Eliminación del prefijo 02
 *  - Traducción 00→SC y 01→SP
 *  - Fix especial para CORDILLERA 135 GRS ("1352600000XXX")
 *  - Patrón genérico clásico 4 + (00|01) + 2 + 6
 */
function decodificar_codigo_qr(string $codigoNumerico): string
{
    $codigo = preg_replace('/\D/', '', $codigoNumerico ?? '');

    if ($codigo === '') {
        return '';
    }

    // 1) QUITAR prefijo 02 si viene
    if (str_starts_with($codigo, '02')) {
        $codigo = substr($codigo, 2);
    }
    // 1.A) EXCEPCIÓN PAPEL ONDA 145 GRS BOB 96 CM CPP MOSTAZAL
    //      QR comienza con: 1145030001009637...
    //      Código SAP correcto: 1145SC03010096
    if (preg_match('/^1145030001009637/', $codigo)) {
        return '1145SC03010096';
    }


    // 2) EXCEPCIÓN OBLIGATORIA PARA PAPEL ONDA 110 GRS HP CORDILLERA
    //    Catálogo SAP: 1110110000000
    //
    //    Todos los QR comienzan con:
    //      021110110000000...
    //    o sin el "02":
    //      1110110000000...
    if (preg_match('/^111011\d{7}/', $codigo)) {
        return '1110110000000';
    }

    // 3) FIX ESPECIAL: PAPEL ONDA 135 GRS CORDILLERA
    if (preg_match('/1352600000(\d{3})/', $codigo, $mm)) {
        return '1135SC26000' . $mm[1];
    }

    // 4) PATRÓN GENÉRICO
    if (preg_match('/(\d{4})(00|01)(\d{2})(\d{6})/', $codigo, $m)) {
        $prefijo = $m[1];
        $grupo  = ($m[2] === '00') ? 'SC' : 'SP';
        return $prefijo . $grupo . $m[3] . $m[4];
    }

    // 5) DEFAULT (mostrar tal cual)
    return $codigoNumerico;
}


/**
 * EXTRA: extracción de candidatos (para catálogo SAP).
 */
function qr_extract_candidates(string $raw): array
{
    $s = _qr_normalize($raw);

    $cands = [];

    if (preg_match_all('/\b\d{12,18}\b/', $s, $nums)) {
        foreach ($nums[0] as $n) $cands[] = $n;
    }

    if (preg_match_all('/\b(?!NULL\b)[A-Z0-9\-_\.]{8,}\b/i', $s, $alnums)) {
        foreach ($alnums[0] as $t) {
            if (strcasecmp($t, 'NULL') !== 0) $cands[] = $t;
        }
    }

    if (strpos($s, ';') !== false) {
        foreach (explode(';', $s) as $p) {
            $p = trim($p);
            if ($p !== '' && strcasecmp($p, 'NULL') !== 0 && strlen($p) >= 5) {
                $cands[] = $p;
            }
        }
    }

    $cands = array_map(fn($x) => trim(str_replace([",", "\t"], "", $x)), $cands);
    $cands = array_values(array_unique(array_filter($cands)));

    return [$s, $cands];
}
