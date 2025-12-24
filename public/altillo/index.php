<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Altillo · Consumo Papel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Estilos base del sistema -->
    <link rel="stylesheet" href="../styles.css">

    <!-- Estilos específicos Altillo -->
    <style>
        .scan-box,
        .result-box,
        .form-box {
            background: #ffffff;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, .08);
        }

        .scan-box button {
            width: 100%;
            background: #0d6efd;
            color: #fff;
            border: none;
            padding: 18px 20px;
            border-radius: 12px;
            font-size: 20px;
            font-weight: 600;
            cursor: pointer;

            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;

            box-shadow: 0 4px 10px rgba(13, 110, 253, 0.35);
        }

        .scan-box button:active {
            transform: scale(.97);
        }


        #qr-reader {
            margin: 15px auto 0;
            border-radius: 10px;
            overflow: hidden;
        }

        .form-group {
            margin-bottom: 12px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 9px;
            border-radius: 6px;
            border: 1px solid #ccc;
            font-size: 15px;
        }

        .form-group input[readonly],
        .form-group textarea[readonly] {
            background: #f3f3f3;
        }

        .btn-primary {
            width: 100%;
            background: #198754;
            color: #fff;
            border: none;
            padding: 12px;
            font-size: 17px;
            border-radius: 8px;
            cursor: pointer;
        }

        .btn-primary:active {
            transform: scale(.98);
        }

        pre {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 6px;
            font-size: 13px;
            overflow-x: auto;
        }

        /* =========================================================
   FIX RESPONSIVE: achicar celdas del formulario "Datos del Registro"
   (styles.css está metiendo height/padding grandes)
========================================================= */
        #formAltillo .form-group input,
        #formAltillo .form-group select,
        #formAltillo .form-group textarea {
            box-sizing: border-box !important;
            height: 38px !important;
            /* <-- achica el "cuadro" */
            padding: 6px 10px !important;
            /* <-- menos padding */
            font-size: 14px !important;
            line-height: 1.2 !important;
        }

        /* textarea debe ser más alta que un input */
        #formAltillo .form-group textarea {
            height: auto !important;
            min-height: 72px !important;
            /* <-- controlado */
            max-height: 110px !important;
            resize: none !important;
        }

        /* opcional: labels un poco más compactas */
        #formAltillo .form-group label {
            margin-bottom: 3px !important;
            font-size: 14px !important;
        }

        /* opcional: menos separación entre campos */
        #formAltillo .form-group {
            margin-bottom: 10px !important;
        }

        /* ====== Operador: dropdown seguro (no datalist) ====== */
        .op-wrap {
            position: relative;
        }

        .op-sugerencias {
            position: absolute;
            top: 42px;
            left: 0;
            right: 0;
            background: #fff;
            border: 1px solid #ccc;
            border-radius: 8px;
            max-height: 220px;
            overflow: auto;
            z-index: 9999;
            box-shadow: 0 6px 18px rgba(0, 0, 0, .12);
        }

        .op-item {
            padding: 10px 12px;
            cursor: pointer;
            font-size: 14px;
        }

        .op-item:hover {
            background: #f1f5ff;
        }

        /* ====== Botón listado (compacto y centrado) ====== */
        .btn-listado {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin: 10px auto 0;
            padding: 6px 14px;
            font-size: 13px;
            font-weight: 500;
            color: #333;
            background: #f1f3f5;
            border: 1px solid #d0d4d9;
            border-radius: 6px;
            text-decoration: none;
            cursor: pointer;
            max-width: fit-content;
        }

        .btn-listado:hover {
            background: #e4e7eb;
        }
    </style>

    <!-- Librería QR (CÁMARA TELÉFONO) -->
    <script src="https://unpkg.com/html5-qrcode"></script>
</head>

<body>

    <main class="container">
        <h1>📦 Registro Altillo</h1>

        <!-- ===============================
         ESCANEO QR
    ================================ -->
        <section class="scan-box">
            <label>Escaneo QR</label>

            <button id="btnScan" type="button">
                📷 Iniciar escaneo
            </button>

            <div id="qr-reader" style="display:none; width:100%;"></div>

            <small style="display:block; margin-top:8px; color:#555;">
                Use la cámara del teléfono para escanear la etiqueta
            </small>
        </section>

        <!-- ===============================
         RESULTADO QR
    ================================ -->
        <section class="result-box">
            <h3>Resultado QR</h3>
            <pre id="resultado">Esperando escaneo...</pre>
        </section>
        <section class="result-box">
            <div id="feedback"></div>
        </section>

        <!-- ===============================
         FORMULARIO REGISTRO
    ================================ -->
        <section class="form-box">
            <h3>Datos del Registro</h3>

            <form id="formAltillo">

                <!-- Operador -->
                <div class="form-group">
                    <label for="operador">Operador</label>

                    <div class="op-wrap">
                        <input
                            type="text"
                            id="operador"
                            name="operador"
                            placeholder="Escriba operador (ej: jose)"
                            autocomplete="off"
                            required>

                        <!-- acá aparecerán las sugerencias -->
                        <div id="op-sugerencias" class="op-sugerencias" style="display:none;"></div>
                    </div>

                </div>

                <!-- NP -->
                <div class="form-group">
                    <label for="np">NP</label>
                    <input type="text" id="np" name="np" placeholder="Ingrese NP" required>
                </div>

                <!-- Cantidad a descontar -->
                <div class="form-group">
                    <label for="saldo_unidades">Saldo de unidades</label>
                    <input type="number" id="saldo_unidades" name="saldo_unidades" min="0" step="1" required>
                </div>

                <div class="form-group">
                    <label>Consumo calculado</label>
                    <input type="hidden" id="consumo_unidades" name="consumo_unidades">
                </div>


                <hr>

                <!-- ===============================
                 DATOS DESDE QR (READONLY)
            ================================ -->

                <input type="hidden" id="codigo_producto" name="codigo_producto">
                <input type="hidden" id="descripcion_producto" name="descripcion_producto">
                <input type="hidden" id="unidades_tarja" name="unidades_tarja">
                <input type="hidden" id="lote" name="lote">


                <button type="submit" class="btn-primary">
                    💾 Guardar Registro
                </button>
                <div style="text-align:center;">
                    <a href="api/list_altillo.php" class="btn-listado">
                        📋 Ver registros
                    </a>
                </div>


            </form>
        </section>

    </main>

    <!-- JS Altillo -->
    <script src="qr_scan_altillo.js"></script>
    <script src="operadores.js"></script>


</body>

</html>