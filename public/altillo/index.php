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
            background: #0d6efd;
            color: #fff;
            border: none;
            padding: 10px 16px;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
        }

        .scan-box button:active {
            transform: scale(.98);
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

            <div id="qr-reader" style="display:none; width:280px;"></div>

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

        <!-- ===============================
         FORMULARIO REGISTRO
    ================================ -->
        <section class="form-box">
            <h3>Datos del Registro</h3>

            <form id="formAltillo">

                <!-- Operador -->
                <div class="form-group">
                    <label for="operador">Operador</label>
                    <select id="operador" name="operador" required>
                        <option value="">Seleccione operador</option>
                        <!-- luego se llena dinámico -->
                        <option value="juan.perez">Juan Pérez</option>
                        <option value="maria.rojas">María Rojas</option>
                        <option value="carlos.soto">Carlos Soto</option>
                    </select>
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
                    <input type="text" id="consumo_unidades" readonly>
                </div>


                <hr>

                <!-- ===============================
                 DATOS DESDE QR (READONLY)
            ================================ -->

                <div class="form-group">
                    <label>Código Producto</label>
                    <input type="text" id="codigo_producto" readonly>
                </div>

                <div class="form-group">
                    <label>Descripción Producto</label>
                    <textarea id="descripcion_producto" rows="2" readonly></textarea>
                </div>

                <div class="form-group">
                    <label>Unidades Tarja</label>
                    <input type="text" id="unidades_tarja" readonly>
                </div>

                <div class="form-group">
                    <label>Lote</label>
                    <input type="text" id="lote" readonly>
                </div>

                <!-- DocNum ELIMINADO (no se usa) -->

                <button type="submit" class="btn-primary">
                    💾 Guardar Registro
                </button>

            </form>
        </section>

    </main>

    <!-- JS Altillo -->
    <script src="qr_scan_altillo.js"></script>

</body>

</html>