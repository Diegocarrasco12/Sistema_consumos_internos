<?php
// test_sqlsrv.php — Prueba de conexión a SQL Server con PDO_SQLSRV
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

// ======= AJUSTA ESTOS 4 VALORES =======
$server   = '192.168.1.230,1433';     // IP de tu SQL Server (puedes usar "IP,PUERTO")
$database = 'INNPACK_PRODUCCION';     // Base de datos a la que quieres entrar
$username = 'lectura_app';            // Usuario creado para la app
$password = 'LecturaSegura2025!';     // *** cámbiala ***
// =====================================

$dsn = "sqlsrv:Server=$server;Database=$database;TrustServerCertificate=true";

try {
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    echo "<h2>✅ Conexión exitosa a SQL Server</h2>";

    // Comprobaciones rápidas
    $info = $pdo->query("SELECT DB_NAME() AS db, SUSER_SNAME() AS login, @@SERVERNAME AS server_name, CONVERT(varchar(19), GETDATE(), 120) AS now")->fetch();
    echo "<pre>"; print_r($info); echo "</pre>";

    // Muestra algunas bases (verifica permisos)
    $stmt = $pdo->query("SELECT TOP 5 name, state_desc FROM sys.databases ORDER BY name");
    $rows = $stmt->fetchAll();
    echo "<h3>sys.databases (TOP 5)</h3><pre>"; print_r($rows); echo "</pre>";

} catch (PDOException $e) {
    echo "<h2>❌ Error de conexión</h2>";
    echo "<pre>" . $e->getMessage() . "</pre>";
}
