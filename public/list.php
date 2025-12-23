<?php

/**
 * list.php
 *
 * Muestra una lista de los registros de tarjas escaneadas. Permite filtrar
 * por fecha, código o lote, actualizar los campos estado y salida, y
 * exportar los resultados a CSV o XLSX.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../models/TarjaScan.php';

use Models\TarjaScan;

// Recoger filtros desde la query string
$filters = [];
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $filters['start_date'] = $_GET['start_date'] ?? '';
    $filters['end_date']   = $_GET['end_date']   ?? '';
    $filters['codigo']     = $_GET['codigo']     ?? '';
    $filters['lote']       = $_GET['lote']       ?? '';
}

// Obtener registros con filtros
// Paginación
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;
$rows = TarjaScan::fetchAll($filters, $limit, $offset);
$total_rows = TarjaScan::countAll($filters);
$total_pages = (int)ceil($total_rows / $limit);

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Registros de Tarjas</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Estilos personalizados -->
    <link href="styles.css?v=20251105" rel="stylesheet">
</head>

<body class="bg-light">
    <div class="container-fluid py-4">
        <h1 class="mb-4">Registros de Consumo Papel</h1>

        <form method="get" class="card card-body shadow-sm mb-3">
            <div class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label for="start_date" class="form-label">Desde</label>
                    <input type="date" id="start_date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($filters['start_date'] ?? ''); ?>">
                </div>
                <div class="col-md-2">
                    <label for="end_date" class="form-label">Hasta</label>
                    <input type="date" id="end_date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($filters['end_date'] ?? ''); ?>">
                </div>
                <div class="col-md-2">
                    <label for="codigo" class="form-label">Código</label>
                    <input type="text" id="codigo" name="codigo" class="form-control" value="<?php echo htmlspecialchars($filters['codigo'] ?? ''); ?>">
                </div>
                <div class="col-md-2">
                    <label for="lote" class="form-label">Lote</label>
                    <input type="text" id="lote" name="lote" class="form-control" value="<?php echo htmlspecialchars($filters['lote'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                    <a href="list.php" class="btn btn-secondary ms-2">Limpiar</a>
                </div>
            </div>
        </form>

        <div class="mb-3">
            <a href="index.php" class="btn btn-success">Nuevo Registro</a>

            <!-- Exportar CSV con filtros vigentes -->
            <a href="export_csv.php?<?php echo http_build_query($filters); ?>" class="btn btn-outline-secondary ms-2">
                Exportar CSV
            </a>

            <!-- NUEVO: Exportar XLSX con formato (usa export_xlsx.php que te pasé) 
        <a href="export_xlsx.php?<?php echo http_build_query($filters); ?>" class="btn btn-outline-success ms-2">
            Exportar XLSX
        </a>
    </div>-->

            <div class="table-responsive">
                <table class="table table-striped table-bordered table-sm align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Fecha</th>
                            <th>Descripción</th>
                            <th>Código</th>
                            <th class="text-end">Consumo KG</th>
                            <th>NP</th>
                            <th class="text-end">Tarja KG</th>
                            <th class="text-end">Saldo KG</th>
                            <th>Lote</th>
                            <th>Estado</th>
                            <th>Salida</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)): ?>
                            <tr>
                                <td colspan="12" class="text-center">No hay registros</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars((string)$row['id']); ?></td>
                                    <td><?php echo htmlspecialchars($row['fecha']); ?></td>
                                    <td><?php echo htmlspecialchars($row['descripcion']); ?></td>
                                    <td><?php echo htmlspecialchars($row['codigo']); ?></td>
                                    <td class="text-end"><?php echo htmlspecialchars(number_format((float)$row['consumo_kg'], 2, ',', '.')); ?></td>
                                    <td><?php echo htmlspecialchars($row['np']); ?></td>
                                    <td class="text-end"><?php echo htmlspecialchars(number_format((float)$row['tarja_kg'], 2, ',', '.')); ?></td>
                                    <td class="text-end"><?php echo htmlspecialchars(number_format((float)($row['saldo_kg'] ?? 0), 2, ',', '.')); ?></td>
                                    <td><?php echo htmlspecialchars($row['lote']); ?></td>
                                    <td>
                                        <form method="post" action="update_estado_salida.php" class="d-flex">
                                            <input type="hidden" name="id" value="<?php echo htmlspecialchars((string)$row['id']); ?>">
                                            <input type="text" name="estado" value="<?php echo htmlspecialchars((string)$row['estado']); ?>" class="form-control form-control-sm">
                                    </td>
                                    <td>
                                        <input type="text" name="salida" value="<?php echo htmlspecialchars((string)$row['salida']); ?>" class="form-control form-control-sm">
                                    </td>
                                    <td>
                                        <button type="submit" class="btn btn-sm btn-outline-primary">Guardar</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <nav>
                <ul class="pagination justify-content-center">

                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($filters, ['page' => $page - 1])); ?>">&laquo;</a>
                        </li>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo ($i === $page) ? 'active' : ''; ?>">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($filters, ['page' => $i])); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($filters, ['page' => $page + 1])); ?>">&raquo;</a>
                        </li>
                    <?php endif; ?>

                </ul>
            </nav>

        </div>
        <script>
            /************************************************************
             * MANTENER POSICIÓN DE SCROLL AL GUARDAR
             ************************************************************/

            // 1) Restaurar scroll al cargar la página
            document.addEventListener('DOMContentLoaded', function() {
                const savedScroll = sessionStorage.getItem('list_scroll_y');
                if (savedScroll !== null) {
                    window.scrollTo(0, parseInt(savedScroll, 10));
                    sessionStorage.removeItem('list_scroll_y');
                }
            });

            // 2) Guardar scroll antes de enviar cualquier form (Guardar)
            document.addEventListener('submit', function() {
                sessionStorage.setItem('list_scroll_y', window.scrollY.toString());
            });
        </script>

        <script>
            // Permite pegar valores verticales desde Excel en columnas ESTADO y SALIDA
            document.addEventListener('paste', function(e) {
                const target = e.target;

                // Solo activar para inputs de ESTADO o SALIDA
                if (target.tagName !== 'INPUT') return;
                if (target.name !== 'estado' && target.name !== 'salida') return;

                // Obtener texto pegado
                const clipboard = (e.clipboardData || window.clipboardData).getData('text');
                if (!clipboard.includes('\n')) return; // Si es una sola línea, no hacemos nada

                e.preventDefault();

                const lines = clipboard.trim().split(/\r?\n/);

                // Encontrar la fila base
                let row = target.closest('tr');
                let currentInputName = target.name;

                // Pegar línea 1 en la celda actual
                target.value = lines[0];

                // Pegar líneas siguientes en filas siguientes
                let nextRow = row.nextElementSibling;
                let index = 1;

                while (nextRow && index < lines.length) {
                    const input = nextRow.querySelector(`input[name="${currentInputName}"]`);
                    if (input) input.value = lines[index];

                    nextRow = nextRow.nextElementSibling;
                    index++;
                }
            });
        </script>

</body>

</html>