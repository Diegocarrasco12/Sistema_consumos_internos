<?php
/**
 * CatalogoMateriales.php
 *
 * Modelo para la tabla catalogo_materiales. Permite obtener la descripción
 * de un material a partir del código. Esta clase es útil para autocompletar
 * la descripción del material cuando se conoce el código a través del QR.
 */

declare(strict_types=1);

namespace Models;

use Config\Database;
use PDO;

class CatalogoMateriales
{
    /**
     * Devuelve la descripción de un material a partir de su código.
     *
     * @param string $codigo Código del material.
     * @return string|null Descripción si existe, o null.
     */
    public static function getDescripcion(string $codigo): ?string
    {
        $pdo = Database::getConnection();
        $sql = "SELECT descripcion FROM catalogo_materiales WHERE codigo = :codigo LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':codigo' => $codigo]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['descripcion'] ?? null;
    }
}
