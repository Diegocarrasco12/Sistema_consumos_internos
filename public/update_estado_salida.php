<?php
/**
 * update_estado_salida.php
 *
 * Procesa la actualización de los campos estado y salida de un registro
 * existente. Redirige de vuelta a la página de listado conservando los
 * filtros originales.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../models/TarjaScan.php';

use Models\TarjaScan;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $estado = isset($_POST['estado']) ? trim($_POST['estado']) : null;
    $salida = isset($_POST['salida']) ? trim($_POST['salida']) : null;

    if ($id > 0) {
        TarjaScan::updateEstadoSalida($id, $estado !== '' ? $estado : null, $salida !== '' ? $salida : null);
    }
    // Construir cadena de consulta de la referer para volver con filtros
    $queryString = '';
    if (!empty($_SERVER['HTTP_REFERER'])) {
        $parts = parse_url($_SERVER['HTTP_REFERER']);
        if (isset($parts['query'])) {
            $queryString = $parts['query'];
        }
    }
    header('Location: list.php' . ($queryString ? ('?' . $queryString) : ''));
    exit;
}
// Si no es POST, redirigir al listado sin cambios
header('Location: list.php');
exit;