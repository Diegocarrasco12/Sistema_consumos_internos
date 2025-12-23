<?php

declare(strict_types=1);

function parse_qr_altillo(string $raw): array
{
    $raw = preg_replace('/\s+/', '', trim($raw));

    if ($raw === '') {
        return ['ok' => false, 'msg' => 'QR vacío'];
    }

    /**
     * FORMATO REAL:
     * 020 + (CODIGO 13) + (UNIDADES_RAW) , 0000 + (LOTE) #DOC
     * Ej: 020200101150026037303,0000103143-1#9135412
     */
    if (!preg_match('/020(\d{13})(\d+),0*(\d{3,7}-\d{1,2})/u', $raw, $m)) {
        return ['ok' => false, 'msg' => 'QR no coincide con patrón esperado', 'raw' => $raw];
    }

    $codigo    = $m[1];  // 2001011500260
    $qtyRaw    = $m[2];  // 37303
    $lote      = $m[3];  // 3143-1 (o 4681-15, etc.)
    // Normalización de LOTE:
    // En los QR viene como 10XXXX-YY pero el lote real es XXXX-YY
    // Ej: 104681-15 -> 4681-15
    if (preg_match('/^10(\d{3,5}-\d{1,2})$/', $lote, $lm)) {
        $lote = $lm[1];
    }

    // Unidades: quitar prefijo interno "37" cuando aplica
    // 37303 -> 303 ; 37525 -> 525 ; 37140 -> 140 ; 37325 -> 325
    if (str_starts_with($qtyRaw, '37') && strlen($qtyRaw) > 3) {
        $qtyRaw = substr($qtyRaw, 2);
    }

    $qtyRaw = ltrim($qtyRaw, '0');
    if ($qtyRaw === '') $qtyRaw = '0';

    // Formato final tal como etiqueta: "303,00"
    $cantidad = $qtyRaw . ',00';

    return [
        'ok' => true,
        'data' => [
            'codigo'   => $codigo,
            'cantidad' => $cantidad,
            'lote'     => $lote,
        ]
    ];
}
