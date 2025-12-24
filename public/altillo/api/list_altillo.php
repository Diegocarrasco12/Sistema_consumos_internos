<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config/db.php';

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
| WHERE dinámico
|=============================== */
$where  = [];
$params = [];

if ($filters['start_date']) {
    $where[] = 'fecha >= :start_date';
    $params[':start_date'] = $filters['start_date'];
}
if ($filters['end_date']) {
    $where[] = 'fecha <= :end_date';
    $params[':end_date'] = $filters['end_date'];
}
if ($filters['codigo']) {
    $where[] = 'codigo LIKE :codigo';
    $params[':codigo'] = '%' . $filters['codigo'] . '%';
}
if ($filters['lote']) {
    $where[] = 'lote LIKE :lote';
    $params[':lote'] = '%' . $filters['lote'] . '%';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

/* ===============================
| Total registros
|=============================== */
$stmt = $pdo->prepare("SELECT COUNT(*) FROM altillo_scan $whereSql");
$stmt->execute($params);
$totalRows  = (int)$stmt->fetchColumn();
$totalPages = (int)ceil($totalRows / $limit);

/* ===============================
| Datos
|=============================== */
$sql = "
    SELECT *
    FROM altillo_scan
    $whereSql
    ORDER BY id DESC
    LIMIT :limit OFFSET :offset
";
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
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

        <form method="get" class="card card-body shadow-sm mb-3">
            <div class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label">Desde</label>
                    <input type="date" name="start_date" class="form-control"
                        value="<?= htmlspecialchars((string)$filters['start_date']) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Hasta</label>
                    <input type="date" name="end_date" class="form-control"
                        value="<?= htmlspecialchars((string)$filters['end_date']) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Código</label>
                    <input type="text" name="codigo" class="form-control"
                        value="<?= htmlspecialchars((string)$filters['codigo']) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Lote</label>
                    <input type="text" name="lote" class="form-control"
                        value="<?= htmlspecialchars((string)$filters['lote']) ?>">
                </div>
                <div class="col-md-4">
                    <button class="btn btn-primary">Filtrar</button>
                    <a href="list_altillo.php" class="btn btn-secondary ms-2">Limpiar</a>
                </div>
            </div>
        </form>

        <div class="mb-3">
            <a href="../index.php" class="btn btn-success">Nuevo Registro</a>
            <a href="export_excel.php?<?= http_build_query($filters) ?>"
                class="btn btn-outline-secondary ms-2">
                Exportar Excel
            </a>
        </div>


        <form method="post" action="save_altillo_bulk.php">
            <button type="submit" class="btn btn-warning mb-3">Guardar cambios</button>

            <div class="table-responsive">
                <table class="table table-striped table-bordered table-sm align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Fecha</th>
                            <th>Nombre</th>
                            <th>Descripción</th>
                            <th>Código</th>
                            <th class="text-end">Consumo</th>
                            <th>NP</th>
                            <th class="text-end">Unid. Tarja</th>
                            <th class="text-end">Saldo</th>
                            <th>Lote</th>
                            <th>Comentario</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>

                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <input type="hidden" name="rows[<?= $r['id'] ?>][id]" value="<?= $r['id'] ?>">

                                <td><?= htmlspecialchars((string)$r['fecha']) ?></td>
                                <td><?= htmlspecialchars((string)$r['nombre']) ?></td>

                                <td><?= htmlspecialchars((string)$r['descripcion']) ?></td>
                                <td><?= htmlspecialchars((string)$r['codigo']) ?></td>
                                <td class="text-end"><?= number_format((float)$r['consumo'], 0, ',', '.') ?></td>
                                <td><?= htmlspecialchars((string)$r['np']) ?></td>
                                <td class="text-end"><?= number_format((float)$r['unidades_tarja'], 0, ',', '.') ?></td>
                                <td class="text-end"><?= number_format((float)($r['saldo'] ?? 0), 0, ',', '.') ?></td>
                                <td><?= htmlspecialchars((string)$r['lote']) ?></td>

                                <td>
                                    <input class="form-control form-control-sm"
                                        name="rows[<?= $r['id'] ?>][comentario]"
                                        value="<?= htmlspecialchars((string)($r['comentario'] ?? '')) ?>">
                                </td>
                                <td>
                                    <input class="form-control form-control-sm"
                                        name="rows[<?= $r['id'] ?>][estado]"
                                        value="<?= htmlspecialchars((string)($r['estado'] ?? '')) ?>">
                                </td>
                                <td>
                                    <input class="form-control form-control-sm"
                                        name="rows[<?= $r['id'] ?>][extra_post_estado]"
                                        value="<?= htmlspecialchars((string)($r['extra_post_estado'] ?? '')) ?>">
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