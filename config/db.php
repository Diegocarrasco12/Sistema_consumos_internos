<?php
/**
 * db.php — CONSUMO PAPEL (dual connections, SAP read-only)
 * - Conexión principal: MySQL (mysqli)
 * - Conexión secundaria: SQL Server (PDO_SQLSRV) SOLO LECTURA
 * - Helpers MySQL:  db_query(), db_select(), db_exec()
 * - Helper  SAP  :  sap_select()  (rechaza TODO lo que no sea SELECT)
 */

declare(strict_types=1);

/* ==================== CONFIG ==================== */
/** Mantén MySQL como motor principal para la app */
$DB_DRIVER  = 'mysql';
/** Habilita/Deshabilita uso de SAP (útil en dev/offline) */
$SAP_ENABLED = true;

/* ====== SQL Server (SAP) — PRODUCCIÓN (confirmado) ====== */
$MSSQL_HOST = '192.168.1.230,1433';
$MSSQL_DB = 'FARET_PRODUCCION';
$MSSQL_USER = 'lectura_app';
$MSSQL_PASS = 'LecturaSegura2025!';

/*
 * Configuración de la base de datos MySQL.
 *
 * Este archivo ha sido adaptado para funcionar de forma híbrida en distintos
 * entornos (local/desarrollo/producción) sin tener que editar manualmente los
 * valores cada vez que se despliega la aplicación.
 *
 * El comportamiento es el siguiente:
 *   1. Si existen variables de entorno MYSQL_HOST, MYSQL_USER, MYSQL_PASS y
 *      MYSQL_DB, se utilizan esas credenciales directamente.
 *   2. Si no existen variables de entorno, intenta conectar con las
 *      credenciales de producción por defecto (host 127.0.0.1, usuario
 *      tickera, contraseña admin123, base consumo_papel).
 *   3. Si la conexión falla (por ejemplo en un entorno local donde el
 *      usuario tickera no existe), se hace un segundo intento con un
 *      conjunto de credenciales típicas de XAMPP/local: host 127.0.0.1,
 *      usuario root, contraseña vacía, base consumo_papel.
 *
 * De esta manera la aplicación se puede ejecutar tanto en el servidor de la
 * empresa como en un entorno local sin tener que modificar este archivo.
 */

// Leer variables de entorno (si están definidas)
$MYSQL_HOST = getenv('MYSQL_HOST') ?: '127.0.0.1';
$MYSQL_USER = getenv('MYSQL_USER') ?: 'tickera';
$MYSQL_PASS = getenv('MYSQL_PASS') ?: 'admin123';
$MYSQL_DB   = getenv('MYSQL_DB')   ?: 'consumo_papel';

// Bandera interna para saber si se debe intentar con credenciales de XAMPP
$__attemptFallbackLocal = false;

// Comprobar conexión inicial utilizando las credenciales definidas
mysqli_report(MYSQLI_REPORT_OFF);
$__mysqli = @new mysqli($MYSQL_HOST, $MYSQL_USER, $MYSQL_PASS, $MYSQL_DB);
if ($__mysqli->connect_errno) {
    // Si falla la conexión con las credenciales actuales, intentar con XAMPP
    $__attemptFallbackLocal = true;
}

// Si es necesario, probar credenciales XAMPP (root/blank)
if ($__attemptFallbackLocal) {
    $__mysqli = @new mysqli('127.0.0.1', 'root', '', 'consumo_papel');
    if ($__mysqli->connect_errno) {
        // Mantener el error original para diagnóstico
        $errorMsg = "❌ Error de conexión MySQL ({$__mysqli->connect_errno}): {$__mysqli->connect_error}";
        // Establecer valores a las variables por si algún script intenta usarlas más adelante
        $MYSQL_HOST = '127.0.0.1';
        $MYSQL_USER = 'root';
        $MYSQL_PASS = '';
        $MYSQL_DB   = 'consumo_papel';
    } else {
        // Conexión con XAMPP exitosa: actualizar credenciales en variables globales
        $MYSQL_HOST = '127.0.0.1';
        $MYSQL_USER = 'root';
        $MYSQL_PASS = '';
        $MYSQL_DB   = 'consumo_papel';
    }
} else {
    // Conexión con credenciales iniciales exitosa
    // Los valores de MYSQL_* permanecen como están
}

/* ============ SESIÓN / CONTEXTO ============ */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$app_user_id   = isset($_SESSION['user_id'])   ? (int)$_SESSION['user_id'] : null;
$app_user_name = $_SESSION['user_name'] ?? null;
$app_user_rut  = $_SESSION['user_rut']  ?? null;
$app_user_ip   = $_SERVER['REMOTE_ADDR'] ?? null;

/* ============ CONEXIÓN PRINCIPAL: MySQL ============ */
mysqli_report(MYSQLI_REPORT_OFF);
// Utilizar la conexión evaluada anteriormente ($__mysqli) si está disponible y válida.
if (isset($__mysqli) && $__mysqli instanceof mysqli && $__mysqli->connect_errno === 0) {
    $db = $__mysqli;
} else {
    // Crear una nueva conexión con las credenciales actuales por si no se inicializó
    $db = @new mysqli($MYSQL_HOST, $MYSQL_USER, $MYSQL_PASS, $MYSQL_DB);
}

// Verificar si hubo un error de conexión
if ($db->connect_errno) {
    die("❌ Error de conexión MySQL ({$db->connect_errno}): {$db->connect_error}\n" .
        "Host: {$MYSQL_HOST} | Usuario: {$MYSQL_USER} | BD: {$MYSQL_DB}");
}

// Asegurar conjunto de caracteres
if (!$db->set_charset('utf8mb4')) {
    $db->query("SET NAMES utf8mb4");
}

/** Alias histórico para compatibilidad */
$conexion = $db;
$conn = $db;

/* Variables @app_* para auditoría (MySQL) */
$uid_sql = is_null($app_user_id)   ? "NULL" : (string)$app_user_id;
$un_sql  = is_null($app_user_name) ? "NULL" : ("'".$db->real_escape_string($app_user_name)."'");
$rut_sql = is_null($app_user_rut)  ? "NULL" : ("'".$db->real_escape_string($app_user_rut)."'");
$ip_sql  = is_null($app_user_ip)   ? "NULL" : ("'".$db->real_escape_string($app_user_ip)."'");
$db->query("SET @app_user_id={$uid_sql}, @app_user_name={$un_sql}, @app_user_rut={$rut_sql}, @app_user_ip={$ip_sql}");

/* ============ CONEXIÓN SECUNDARIA: SQL Server (SAP, RO) ============ */
$sap = null;
if ($SAP_ENABLED) {
    // Timeouts bajos + UTF-8 + canal cifrado (confía en certificado interno)
    $dsn = "sqlsrv:Server={$MSSQL_HOST};Database={$MSSQL_DB};"
         . "TrustServerCertificate=Yes;Encrypt=Yes;"
         . "LoginTimeout=5;CharacterSet=UTF-8";
    try {
        $sap = new PDO($dsn, $MSSQL_USER, $MSSQL_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            // Nota: si tu build soporta timeout por consulta, podrías añadirlo aquí.
        ]);
        // Formato de fecha consistente (opcional)
        // $sap->exec("SET DATEFORMAT ymd;");
    } catch (Throwable $e) {
        // No interrumpas la app si SAP no está disponible.
        $sap = null;
        // TODO: opcional -> escribir a log
    }
}

/* =================== HELPERS — MYSQL =================== */
/** Ejecuta consulta preparada (firma histórica). */
if (!function_exists('db_query')) {
    function db_query(string $sql, string $types = '', array $params = [])
    {
        global $db;
        $stmt = $db->prepare($sql);
        if ($stmt === false) { return false; }
        if ($types !== '' && !empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        if (!$stmt->execute()) { $stmt->close(); return false; }
        $result = $stmt->get_result();
        if ($result === false) {
            $ok = ($db->affected_rows >= 0);
            $stmt->close();
            return $ok;
        }
        return $result; // mysqli_result
    }
}

/** SELECT (MySQL) -> array */
if (!function_exists('db_select')) {
    function db_select(string $sql, array $params = []): array
    {
        global $db;
        $types = '';
        if ($params) {
            foreach ($params as $p) {
                $types .= is_int($p) ? 'i' : (is_float($p) ? 'd' : 's');
            }
        }
        $res = db_query($sql, $types, $params);
        if ($res === false) return [];
        $rows = [];
        while ($row = $res->fetch_assoc()) { $rows[] = $row; }
        $res->free();
        return $rows;
    }
}

/** INSERT/UPDATE/DELETE (MySQL) -> filas afectadas */
if (!function_exists('db_exec')) {
    function db_exec(string $sql, array $params = []): int
    {
        global $db;
        $types = '';
        if ($params) {
            foreach ($params as $p) {
                $types .= is_int($p) ? 'i' : (is_float($p) ? 'd' : 's');
            }
        }
        $stmt = $db->prepare($sql);
        if ($stmt === false) return 0;
        if ($types !== '' && !empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        if (!$stmt->execute()) { $stmt->close(); return 0; }
        $affected = (int)$stmt->affected_rows;
        $stmt->close();
        return max(0, $affected);
    }
}

/* =================== HELPER — SAP (SOLO LECTURA) =================== */
/**
 * sap_select(): Ejecuta SOLO SELECT sobre SQL Server (SAP).
 * - Si SAP no está disponible -> retorna [].
 * - Rechaza cualquier instrucción que no comience con SELECT.
 */
if (!function_exists('sap_select')) {
    function sap_select(string $sql, array $params = []): array
    {
        global $sap;
        if (!$sap) { return []; }

        // Guardia: SOLO se permite SELECT (ignora espacios y comentarios simples)
        $check = ltrim($sql);
        // Remueve comentarios de línea iniciales si los hubiera
        while (preg_match('/^(--[^\n]*\n|\s+|\/\*.*?\*\/)+/s', $check)) {
            $check = preg_replace('/^(--[^\n]*\n|\s+|\/\*.*?\*\/)+/s', '', $check, 1);
        }
        if (!preg_match('/^SELECT\b/i', $check)) {
            // Intento de instrucción no permitida.
            return []; 
        }

        $stmt = $sap->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows ?: [];
    }
}
