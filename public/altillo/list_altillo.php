<?php

declare(strict_types=1);

/* ===============================
| DEBUG (solo mientras pruebas)
|=============================== */
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

/* ===============================
| Timezone (CRÍTICO en producción)
|=============================== */
date_default_timezone_set('America/Santiago');

/* ===============================
| DB (NO SE MODIFICA db.php)
|=============================== */
require_once __DIR__ . '/../../config/db.php';

/* ===============================
| Helpers defensivos
|=============================== */
function h($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/* ===============================
| Filtros
|=============================== */
$filters = [
    'start_date' => $_GET['start_date'] ?? '',
    'end_date'   => $_GET['end_date'] ?? '',
    'codigo'     => $_GET['codigo'] ?? '',
    'lote'       => $_GET['lote'] ?? '',
];

/* ===============================
| Paginación
|=============================== */
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 20;
$offset = ($page - 1) * $limit;

/* ===============================
| WHERE dinámico (mysqli)
|=============================== */
$where  = [];
$params = [];

if ($filters['start_date'] !== '') {
    $where[]  = 'fecha >= ?';
    $params[] = $filters['start_date'];
}
if ($filters['end_date'] !== '') {
    $where[]  = 'fecha <= ?';
    $params[] = $filters['end_date'];
}
if ($filters['codigo'] !== '') {
    $where[]  = 'codigo LIKE ?';
    $params[] = '%' . $filters['codigo'] . '%';
}
if ($filters['lote'] !== '') {
    $where[]  = 'lote LIKE ?';
    $params[] = '%' . $filters['lote'] . '%';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

/* ===============================
| Total registros
|=============================== */
$sqlCount  = "SELECT COUNT(*) AS total FROM altillo_scan $whereSql";
$resCount  = db_select($sqlCount, $params);
$totalRows = (int)($resCount[0]['total'] ?? 0);
$totalPages = (int)ceil($totalRows / $limit);

/* ===============================
| Datos
| ⚠️ ORDEN POR created_at (NO por id)
|=============================== */
$sql = "
    SELECT *
    FROM altillo_scan
    $whereSql
    ORDER BY created_at DESC
    LIMIT $limit OFFSET $offset
";
$rows = db_select($sql, $params);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Registros Altillo</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../styles.css?v=20251105" rel="stylesheet">
</head>

<body class="bg-light">
    <div class="container-fluid py-4">

        <h1 class="mb-4">Registros Altillo</h1>

        <!-- ===============================
     FILTROS
    ================================ -->
        <form method="get" class="card card-body shadow-sm mb-3">
            <div class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label">Desde</label>
                    <input type="date" name="start_date" class="form-control" value="<?= h($filters['start_date']) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Hasta</label>
                    <input type="date" name="end_date" class="form-control" value="<?= h($filters['end_date']) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Código</label>
                    <input type="text" name="codigo" class="form-control" value="<?= h($filters['codigo']) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Lote</label>
                    <input type="text" name="lote" class="form-control" value="<?= h($filters['lote']) ?>">
                </div>
                <div class="col-md-4">
                    <button class="btn btn-primary">Filtrar</button>
                    <a href="list_altillo.php" class="btn btn-secondary ms-2">Limpiar</a>
                </div>
            </div>
        </form>

        <!-- ===============================
     ACCIONES
    ================================ -->
        <div class="mb-3">
            <a href="index.php" class="btn btn-success">Nuevo Registro</a>
            <a href="api/export_excel.php?<?= http_build_query($filters) ?>"
                class="btn btn-outline-secondary ms-2">
                Exportar Excel
            </a>
        </div>

        <!-- ===============================
     TABLA
    ================================ -->
        <form method="post" action="api/save_altillo_bulk.php">
            <button type="submit" class="btn btn-warning mb-3">Guardar cambios</button>

            <div class="table-responsive">
                <table class="table table-striped table-bordered table-sm align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th class="text-end">ID</th>
                            <th>Fecha / Hora</th>
                            <th>Nombre</th>
                            <th>Descripcion</th>
                            <th>Codigo</th>
                            <th class="text-end">Consumo</th>
                            <th>NP</th>
                            <th class="text-end">Unid. Tarja</th>
                            <th class="text-end">Saldo</th>
                            <th>Lote</th>
                            <th>Comentario</th>
                            <th>Estado</th>
                            <th>Extra</th>
                        </tr>
                    </thead>
                    <tbody>

                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <!-- ID -->
                                <td class="text-end">
                                    <?= (int)$r['id'] ?>
                                    <input type="hidden"
                                        name="rows[<?= (int)$r['id'] ?>][id]"
                                        value="<?= (int)$r['id'] ?>">
                                </td>

                                <!-- Fecha / Hora -->
                                <td>
                                    <?= h(
                                        isset($r['created_at'])
                                            ? date('d-m-Y H:i:s', strtotime((string)$r['created_at']))
                                            : ''
                                    ) ?>
                                </td>

                                <!-- Nombre -->
                                <td><?= h($r['nombre']) ?></td>

                                <!-- Descripcion -->
                                <td><?= h($r['descripcion']) ?></td>

                                <!-- Codigo -->
                                <td><?= h($r['codigo']) ?></td>

                                <!-- Consumo -->
                                <td class="text-end">
                                    <?= number_format((int)$r['consumo'], 0, ',', '.') ?>
                                </td>

                                <!-- NP -->
                                <td><?= h($r['np']) ?></td>

                                <!-- Unid. Tarja -->
                                <td class="text-end">
                                    <?= number_format((int)$r['unidades_tarja'], 0, ',', '.') ?>
                                </td>

                                <!-- Saldo -->
                                <td class="text-end">
                                    <?= number_format((int)($r['saldo'] ?? 0), 0, ',', '.') ?>
                                </td>

                                <!-- Lote -->
                                <td><?= h($r['lote']) ?></td>

                                <!-- Comentario -->
                                <td>
                                    <input class="form-control form-control-sm"
                                        name="rows[<?= (int)$r['id'] ?>][comentario]"
                                        value="<?= h($r['comentario'] ?? '') ?>">
                                </td>

                                <!-- Estado -->
                                <td>
                                    <input class="form-control form-control-sm"
                                        name="rows[<?= (int)$r['id'] ?>][estado]"
                                        value="<?= h($r['estado'] ?? '') ?>">
                                </td>

                                <!-- Extra -->
                                <td>
                                    <input class="form-control form-control-sm"
                                        name="rows[<?= (int)$r['id'] ?>][extra_post_estado]"
                                        value="<?= h($r['extra_post_estado'] ?? '') ?>">
                                </td>
                            </tr>
                        <?php endforeach; ?>

                    </tbody>
                </table>
            </div>

        </form>
    </div>

    <script>
        document.addEventListener('paste', function(e) {
            const input = e.target;
            if (input.tagName !== 'INPUT') return;

            const td = input.closest('td');
            if (!td) return;

            const text = e.clipboardData.getData('text');
            if (!text.includes('\n')) return;

            e.preventDefault();
            const values = text.replace(/\r/g, '').split('\n');
            let row = input.closest('tr');
            let col = td.cellIndex;

            values.forEach(v => {
                if (!row) return;
                const cell = row.cells[col];
                const inp = cell.querySelector('input');
                if (inp) inp.value = v;
                row = row.nextElementSibling;
            });
        });
    </script>

</body>

</html>