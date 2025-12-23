# 📦 Lectura de Tarjas QR

Este proyecto es un **prototipo PHP** diseñado para automatizar el **registro de tarjas de consumo de papel** mediante la lectura de **códigos QR**.  
Funciona en un entorno local (por ejemplo **XAMPP** o **LAMP**) y utiliza la base de datos `lectura_tarjas`, la cual contiene las tablas `tarjas_scan`, `catalogo_materiales` y `usuarios`.

---

## 🧩 Estructura del proyecto

- **`config/db.php`**  
  Configura la conexión a MySQL mediante PDO. Ajuste el *host*, nombre de base de datos, usuario y contraseña según su entorno local.

- **`helpers/qr_parser.php`**  
  Contiene las funciones que extraen **lote**, **código de material** y **peso de la tarja (tarja_kg)** a partir del texto del QR.  
  Puede ajustar las expresiones regulares según el formato real de sus códigos.

- **`models/TarjaScan.php`**  
  Modelo principal del sistema. Administra la inserción, actualización y consulta de registros en la tabla `tarjas_scan`, incluyendo los nuevos campos:
  - `saldo_kg` → ingresado manualmente por el operador.  
  - `consumo_kg` → calculado automáticamente como `tarja_kg - saldo_kg`.

- **`models/CatalogoMateriales.php`**  
  Permite obtener la descripción del material desde la tabla `catalogo_materiales`, según su código.

- **`public/index.php`**  
  Formulario principal para el operador.  
  Permite:
  - Pegar el texto del QR.  
  - Ingresar manualmente los campos `NP` y `Saldo KG`.  
  - Calcular automáticamente `Consumo KG` antes de guardar el registro.  

- **`public/list.php`**  
  Lista todos los registros de `tarjas_scan`.  
  Permite:
  - Filtrar por rango de fechas, código o lote.  
  - Ver los campos `Consumo KG`, `Saldo KG` y `Tarja KG`.  
  - Editar `estado` y `salida` directamente en línea.

- **`public/export_csv.php`**  
  Exporta los registros filtrados a un archivo CSV con el siguiente orden de columnas:
  ```
  FECHA | (columna en blanco) | DESCRIPCIÓN | CÓDIGO | CONSUMO KG | NP | TARJA KG | SALDO KG | LOTE | ESTADO | SALIDA
  ```
  Usa `;` como separador y formato numérico con coma decimal (para compatibilidad con Excel).

- **`public/update_estado_salida.php`**  
  Procesa la actualización de los campos `estado` y `salida` al guardar cambios desde el listado.

---

## ⚙️ Instrucciones de uso

1. Coloque la carpeta `lectura_tarjas_project` en el directorio de documentos del servidor local (`htdocs` en XAMPP, o `/var/www/html` en LAMP).  
2. Cree la base de datos `lectura_tarjas` ejecutando el archivo `lectura_tarjas.sql` incluido.  
   Esto generará las tablas necesarias (`tarjas_scan`, `catalogo_materiales`, `usuarios`).  
3. Ajuste las credenciales de conexión en `config/db.php`.  
4. Acceda a la aplicación desde su navegador:
   ```
   http://localhost/lectura_tarjas_project/public/index.php
   ```
5. Pegue el texto del QR en el formulario, ingrese los valores de `NP` y `Saldo KG`.  
   El sistema calculará automáticamente el **Consumo KG (tarja_kg - saldo_kg)** y almacenará todos los datos.
6. Visite:
   ```
   http://localhost/lectura_tarjas_project/public/list.php
   ```
   para visualizar, filtrar o exportar los registros a CSV.

---

## 📄 Notas técnicas

- La función `parse_qr()` es un modelo de ejemplo. Si los QR de su planta incluyen información diferente (por ejemplo, distinto orden o separadores), deberá ajustar las expresiones regulares en `helpers/qr_parser.php`.
- Todos los valores decimales se exportan con formato **español (coma como separador decimal)**.
- Se agregó el campo **`saldo_kg`** para replicar el flujo real de registro de operadores.
- **`consumo_kg` se calcula automáticamente** antes de guardar, evitando errores manuales.
- La interfaz visual está diseñada para ser **simple, clara y moderna**, con estilo corporativo adaptable.
