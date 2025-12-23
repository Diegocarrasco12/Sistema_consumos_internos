<?php
declare(strict_types=1);

namespace Models;

/**
 * SAPCatalog — LECTURA DE SAP vía Linked Server CONEXIONSAP y/o catálogos locales
 *
 * Reglas:
 * - Los pre-ingresos (@ZZZPILC/@ZZZPILD) viven en FARET_PRODUCCION (consultamos esa base).
 * - U_Empresa define desde qué base se leen maestros (OITM/OBTN): FARET o INNPACK.
 * - También puede consultar un catálogo local MySQL (`catalogo_sap_local`) para operaciones offline.
 *
 * Dependencias:
 * - Helper global sap_select(string $sql, array $params = []): array
 *   (definido en config/db.php). SOLO permite SELECT y puede retornar [] si SAP no está disponible.
 * - Para consultas locales, usa conexión MySQL definida en config/db.php → \db()
 */
class SAPCatalog
{
    /** Genera variantes habituales de un lote para búsquedas flexibles */
    private static function loteVariants(string $lote): array
    {
        $lote = trim($lote);
        if ($lote === '') return [];

        $norm = strtoupper($lote);

        // separar número + sufijo (ej: 3889-20, 000103889-20, etc.)
        $num = preg_replace('/[^0-9]/', '', $norm);
        $suf = '';
        if (preg_match('/(\d+)[^\d]+(\d+)/', $norm, $m)) {
            $num = $m[1];
            $suf = $m[2];
        }

        $out = [];
        if ($suf !== '') {
            $out[] = "{$num}-{$suf}";
            $out[] = ltrim($num, '0') . "-{$suf}";
            $out[] = $num . $suf;
            $out[] = ltrim($num, '0') . $suf;
            $out[] = str_pad($num, max(6, strlen($num)), '0', STR_PAD_LEFT) . "-{$suf}";
        } else {
            $out[] = $num;
            $out[] = ltrim($num, '0');
        }

        $onlyNum = preg_replace('/[^0-9]/', '', $norm);
        if ($onlyNum !== '') $out[] = $onlyNum;

        return array_values(array_unique(array_filter($out)));
    }

    /**
     * Busca por DOCNUM en los pre-ingresos ([@ZZZPILC]/[@ZZZPILD]) y devuelve:
     *  - empresa (FARET|INNPACK)
     *  - lote     (B2.U_Lote)
     *  - item_code, item_name (B2.U_ItemCode/U_ItemName)
     *  - uom (desde OITM de la base correcta según U_Empresa)
     *
     * Usa TRY_CONVERT para comparar numéricamente (soporta '0003889' == 3889).
     */
    public static function findByDocnum(string|int $docnum, bool $debug = false): array
    {
        $docnumInt = (int) preg_replace('/\D+/', '', (string)$docnum);
        if ($docnumInt === 0) return [];

        $sql = "
            SELECT TOP 1
                B1.U_Empresa                                AS empresa,
                B2.U_Lote                                    AS lote,
                B2.U_ItemCode                                AS item_code,
                B2.U_ItemName                                AS item_name,
                CASE WHEN B1.U_Empresa = 'FARET' THEN
                    (SELECT i.InvntryUom FROM CONEXIONSAP.FARET_PRODUCCION.dbo.OITM i WHERE i.ItemCode = B2.U_ItemCode)
                ELSE
                    (SELECT i.InvntryUom FROM CONEXIONSAP.INNPACK_PRODUCCION.dbo.OITM i WHERE i.ItemCode = B2.U_ItemCode)
                END                                          AS uom
            FROM CONEXIONSAP.FARET_PRODUCCION.dbo.[@ZZZPILC]  B1
            INNER JOIN CONEXIONSAP.FARET_PRODUCCION.dbo.[@ZZZPILD] B2
                ON B1.DocEntry = B2.DocEntry
            WHERE TRY_CONVERT(INT, LTRIM(RTRIM(B1.DocNum))) = ?
            ORDER BY B1.CreateDate DESC
        ";

        $rows = \sap_select($sql, [$docnumInt]);

        if ($debug) {
            return ['sql' => $sql, 'params' => [$docnumInt], 'rows' => $rows];
        }

        if (!$rows) return [];
        $r = $rows[0];

        $empresa = strtoupper((string)($r['empresa'] ?? ''));
        $empresa = ($empresa === 'FARET') ? 'FARET' : 'INNPACK';

        return [
            'empresa'   => $empresa,
            'lote'      => $r['lote']       ?? null,
            'item_code' => $r['item_code']  ?? null,
            'item_name' => $r['item_name']  ?? null,
            'uom'       => $r['uom']        ?? null,
        ];
    }

    /**
     * Busca por LOTE directamente en OBTN + OITM (FARET/INNPACK, o ambas).
     * Devuelve item_code, item_name, uom, lote.
     */
    public static function findByBatchFlexible(string $lote, ?string $empresa = null, bool $debug = false): array
    {
        $variants = self::loteVariants($lote);
        if (empty($variants)) return [];

        $ph   = implode(',', array_fill(0, count($variants), '?'));
        $like = preg_replace('/[^0-9]/', '', $variants[0]) . '%';

        if ($empresa === 'FARET' || $empresa === 'INNPACK') {
            $db = ($empresa === 'FARET') ? 'FARET_PRODUCCION' : 'INNPACK_PRODUCCION';
            $sql = "
                SELECT TOP 1
                    i.ItemCode   AS item_code,
                    i.ItemName   AS item_name,
                    i.InvntryUom AS uom,
                    b.DistNumber AS lote
                FROM CONEXIONSAP.{$db}.dbo.OBTN b
                INNER JOIN CONEXIONSAP.{$db}.dbo.OITM i ON i.ItemCode = b.ItemCode
                WHERE REPLACE(LTRIM(RTRIM(b.DistNumber)),'-','') IN ({$ph})
                   OR REPLACE(LTRIM(RTRIM(b.DistNumber)),'-','') LIKE ?
                ORDER BY b.CreateDate DESC
            ";
            $params = array_merge(array_map(fn($v) => str_replace('-', '', $v), $variants), [str_replace('-', '', $like)]);
        } else {
            $sql = "
                SELECT TOP 1
                    i.ItemCode   AS item_code,
                    i.ItemName   AS item_name,
                    i.InvntryUom AS uom,
                    b.DistNumber AS lote
                FROM (
                    SELECT DistNumber, ItemCode, CreateDate
                    FROM CONEXIONSAP.INNPACK_PRODUCCION.dbo.OBTN
                    UNION ALL
                    SELECT DistNumber, ItemCode, CreateDate
                    FROM CONEXIONSAP.FARET_PRODUCCION.dbo.OBTN
                ) b
                INNER JOIN (
                    SELECT ItemCode, ItemName, InvntryUom
                    FROM CONEXIONSAP.INNPACK_PRODUCCION.dbo.OITM
                    UNION ALL
                    SELECT ItemCode, ItemName, InvntryUom
                    FROM CONEXIONSAP.FARET_PRODUCCION.dbo.OITM
                ) i ON i.ItemCode = b.ItemCode
                WHERE REPLACE(LTRIM(RTRIM(b.DistNumber)),'-','') IN ({$ph})
                   OR REPLACE(LTRIM(RTRIM(b.DistNumber)),'-','') LIKE ?
                ORDER BY b.CreateDate DESC
            ";
            $params = array_merge(array_map(fn($v) => str_replace('-', '', $v), $variants), [str_replace('-', '', $like)]);
        }

        $rows = \sap_select($sql, $params);

        if ($debug) {
            return ['sql' => $sql, 'params' => $params, 'rows' => $rows];
        }

        if (!$rows) return [];
        $r = $rows[0];

        return [
            'item_code' => $r['item_code'] ?? null,
            'item_name' => $r['item_name'] ?? null,
            'uom'       => $r['uom']       ?? null,
            'lote'      => $r['lote']      ?? null,
        ];
    }

    // ============================================================
    // NUEVO BLOQUE: CONSULTA LOCAL A catalogo_sap_local (MySQL)
    // ============================================================

    /**
     * Busca un producto en la base local `catalogo_sap_local`
     * a partir de item_code o codebars.
     *
     * Devuelve: empresa, item_code, item_name, uom.
     *
     * Este método se usa cuando SAP no está disponible
     * o para lecturas locales desde PHP (más rápidas).
     */
    public static function findByCodeOrBarcode(string $codigo): array
{
    $codigo = trim($codigo);
    if ($codigo === '') return [];

    // ✅ Usar la conexión global $conn definida en config/db.php
    global $conn;
    if (!$conn) return [];

    $sql = "
        SELECT empresa, item_code, item_name, uom
        FROM catalogo_sap_local
        WHERE item_code = ? OR codebars = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) return [];

    $stmt->bind_param('ss', $codigo, $codigo);
    $stmt->execute();
    $res = $stmt->get_result();

    if (!$res || $res->num_rows === 0) {
        $stmt->close();
        return [];
    }

    $row = $res->fetch_assoc();
    $stmt->close();

    return [
        'empresa'   => $row['empresa']   ?? null,
        'item_code' => $row['item_code'] ?? null,
        'item_name' => $row['item_name'] ?? null,
        'uom'       => $row['uom']       ?? null,
    ];
}

}
