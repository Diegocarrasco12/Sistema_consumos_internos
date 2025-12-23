<?php

/**
 * TarjaScan.php
 *
 * Modelo híbrido compatible con PDO y MySQLi.
 * Se adapta automáticamente según el entorno del servidor.
 *
 * Este modelo unifica las operaciones de acceso a la tabla tarjas_scan
 * para que funcionen en cualquier entorno (local o de producción) sin
 * depender de extensiones concretas. Si la extensión PDO para MySQL está
 * disponible y cargada, se usa por defecto. En caso contrario, se utiliza
 * la extensión MySQLi.
 */

declare(strict_types=1);

namespace Models;

require_once __DIR__ . '/../config/db.php';

use PDO;
use Throwable;

class TarjaScan
{
    /**
     * Indica si se utilizará PDO (true) o MySQLi (false)
     * @var bool
     */
    private static bool $usePDO = false;

    /**
     * Conexión PDO reutilizable
     * @var PDO|null
     */
    private static ?PDO $pdo = null;

    /**
     * Conexión MySQLi reutilizable
     * @var \mysqli|null
     */
    private static ?\mysqli $mysqli = null;

    /**
     * Inicializa la conexión a la base de datos detectando automáticamente
     * si se puede usar PDO o se debe usar MySQLi. Este método se ejecuta
     * una sola vez y reutiliza la misma conexión para futuras operaciones.
     */
    private static function initConnection(): void
    {
        if (self::$pdo || self::$mysqli) {
            return;
        }

        // Intentar usar PDO si está disponible y el driver mysql existe
        try {
            if (class_exists('PDO') && in_array('mysql', PDO::getAvailableDrivers(), true)) {
                $dsn = "mysql:host={$GLOBALS['MYSQL_HOST']};dbname={$GLOBALS['MYSQL_DB']};charset=utf8mb4";
                self::$pdo = new PDO(
                    $dsn,
                    $GLOBALS['MYSQL_USER'],
                    $GLOBALS['MYSQL_PASS'],
                    [
                        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES   => false,
                    ]
                );
                self::$usePDO = true;
                return;
            }
        } catch (Throwable $e) {
            // Ignorar y continuar con MySQLi
        }

        // Fallback a MySQLi si PDO no está disponible
        self::$mysqli = new \mysqli(
            $GLOBALS['MYSQL_HOST'],
            $GLOBALS['MYSQL_USER'],
            $GLOBALS['MYSQL_PASS'],
            $GLOBALS['MYSQL_DB']
        );
        if (self::$mysqli->connect_errno) {
            die("❌ Error de conexión MySQL: " . self::$mysqli->connect_error);
        }
        self::$mysqli->set_charset('utf8mb4');
        self::$usePDO = false;
    }

    /* =======================================================
       =================== OPERACIONES ========================
       =======================================================*/

    /**
     * Inserta un nuevo registro en la tabla tarjas_scan.
     *
     * @param array $data Datos del registro a insertar.
     * @return int ID del registro insertado.
     */
    public static function create(array $data): int
    {
        self::initConnection();

        $sql = "INSERT INTO tarjas_scan
                (fecha, descripcion, codigo, consumo_kg, np, tarja_kg, saldo_kg, lote, estado, salida, raw_qr, id_usuario, created_at, updated_at)
                VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

        // Preparar parámetros en el orden adecuado
        $params = [
            $data['descripcion'] ?? '',
            $data['codigo']      ?? '',
            (float)($data['consumo_kg'] ?? 0),
            $data['np']          ?? null,
            (float)($data['tarja_kg'] ?? 0),
            (float)($data['saldo_kg'] ?? 0),
            $data['lote']        ?? '',
            $data['estado']      ?? null,
            $data['salida']      ?? null,
            $data['raw_qr']      ?? '',
            $data['id_usuario']  ?? null,
        ];

        if (self::$usePDO) {
            $stmt = self::$pdo->prepare($sql);
            $stmt->execute($params);
            return (int)self::$pdo->lastInsertId();
        }

        // MySQLi
        $stmt = self::$mysqli->prepare($sql);
        // Construir el string de tipos dinámicamente
        $types = '';
        foreach ($params as $p) {
            if (is_int($p)) {
                $types .= 'i';
            } elseif (is_float($p)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        return (int)self::$mysqli->insert_id;
    }

    /**
     * Actualiza los campos estado y salida de un registro.
     *
     * @param int $id      ID del registro a actualizar.
     * @param string|null $estado Nuevo estado.
     * @param string|null $salida Nuevo valor de salida.
     */
    public static function updateEstadoSalida(int $id, ?string $estado, ?string $salida): void
    {
        self::initConnection();

        $sql = "UPDATE tarjas_scan
                SET estado = ?, salida = ?, updated_at = NOW()
                WHERE id = ?";

        if (self::$usePDO) {
            $stmt = self::$pdo->prepare($sql);
            $stmt->execute([$estado, $salida, $id]);
            return;
        }

        $stmt = self::$mysqli->prepare($sql);
        // Construir tipos dinámicamente (estado, salida son strings o null, id es int)
        $types = '';
        foreach ([$estado, $salida, $id] as $p) {
            if (is_int($p)) {
                $types .= 'i';
            } elseif (is_float($p)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
        }
        $stmt->bind_param($types, $estado, $salida, $id);
        $stmt->execute();
    }
        /**
     * Devuelve registros con LIMIT y OFFSET (para la tabla paginada).
     *
     * @param array $filters
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public static function fetchAll(array $filters = [], int $limit = 20, int $offset = 0): array
    {
        self::initConnection();

        $sql = "SELECT * FROM tarjas_scan WHERE 1=1";
        $params = [];

        if (!empty($filters['start_date'])) {
            $sql .= " AND fecha >= ?";
            $params[] = $filters['start_date'] . ' 00:00:00';
        }
        if (!empty($filters['end_date'])) {
            $sql .= " AND fecha <= ?";
            $params[] = $filters['end_date'] . ' 23:59:59';
        }
        if (!empty($filters['codigo'])) {
            $sql .= " AND codigo = ?";
            $params[] = $filters['codigo'];
        }
        if (!empty($filters['lote'])) {
            $sql .= " AND lote = ?";
            $params[] = $filters['lote'];
        }

        $sql .= " ORDER BY id DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        // === PDO ===
        if (self::$usePDO) {
            $stmt = self::$pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // === MySQLi ===
        $stmt = self::$mysqli->prepare($sql);

        $types = '';
        foreach ($params as $p) {
            if (is_int($p))      $types .= 'i';
            elseif (is_float($p)) $types .= 'd';
            else                $types .= 's';
        }

        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }


    /**
     * Devuelve todos los registros SIN LIMIT (para exportar CSV completo).
     *
     * @param array $filters
     * @return array
     */
    public static function fetchAllNoLimit(array $filters = []): array
    {
        self::initConnection();

        $sql = "SELECT * FROM tarjas_scan WHERE 1=1";
        $params = [];

        if (!empty($filters['start_date'])) {
            $sql .= " AND fecha >= ?";
            $params[] = $filters['start_date'] . ' 00:00:00';
        }
        if (!empty($filters['end_date'])) {
            $sql .= " AND fecha <= ?";
            $params[] = $filters['end_date'] . ' 23:59:59';
        }
        if (!empty($filters['codigo'])) {
            $sql .= " AND codigo = ?";
            $params[] = $filters['codigo'];
        }
        if (!empty($filters['lote'])) {
            $sql .= " AND lote = ?";
            $params[] = $filters['lote'];
        }

        // IMPORTANTE: SIN LIMIT
        $sql .= " ORDER BY id DESC";

        // === PDO ===
        if (self::$usePDO) {
            $stmt = self::$pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // === MySQLi ===
        $stmt = self::$mysqli->prepare($sql);

        if (!empty($params)) {
            $types = '';
            foreach ($params as $p) {
                if (is_int($p))        $types .= 'i';
                elseif (is_float($p))  $types .= 'd';
                else                   $types .= 's';
            }
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }



    public static function countAll(array $filters = []): int
    {
        self::initConnection();

        $sql = "SELECT COUNT(*) AS total FROM tarjas_scan WHERE 1=1";
        $params = [];

        if (!empty($filters['start_date'])) {
            $sql .= " AND fecha >= ?";
            $params[] = $filters['start_date'] . ' 00:00:00';
        }
        if (!empty($filters['end_date'])) {
            $sql .= " AND fecha <= ?";
            $params[] = $filters['end_date'] . ' 23:59:59';
        }
        if (!empty($filters['codigo'])) {
            $sql .= " AND codigo = ?";
            $params[] = $filters['codigo'];
        }
        if (!empty($filters['lote'])) {
            $sql .= " AND lote = ?";
            $params[] = $filters['lote'];
        }

        if (self::$usePDO) {
            $stmt = self::$pdo->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch();
            return (int)$row['total'];
        }

        $stmt = self::$mysqli->prepare($sql);

        if (!empty($params)) {
            $types = '';
            foreach ($params as $p) {
                $types .= is_int($p) ? 'i' : 's';
            }
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        return (int)$row['total'];
    }
}
