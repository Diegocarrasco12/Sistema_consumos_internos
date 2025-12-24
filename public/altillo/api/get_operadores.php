<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../config/db.php';

$sql = "SELECT nombre FROM operadores WHERE activo = 1 ORDER BY nombre";
$res = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($res, JSON_UNESCAPED_UNICODE);
