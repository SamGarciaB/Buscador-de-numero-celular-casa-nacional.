# Buscador de numeración telefónica de México

Este proyecto consulta a qué rango de numeración pertenece un número de 10 dígitos. Está hecho con PHP y MySQL, y puede mostrar la compañía, localidad, estado y modalidad que aparecen en la base de datos configurada.

La búsqueda trata sobre la asignación de rangos; no identifica al titular de un número. Es un proyecto independiente y no está afiliado al IFT.

## Qué incluye

- Búsqueda de rangos con PDO y consultas preparadas.
- Validación del número en el servidor.
- Inicio de sesión con contraseñas verificadas mediante `password_verify`.
- Protección CSRF en las acciones del formulario.
- Un conjunto pequeño de datos ficticios para probarlo localmente.

## Tecnologías

PHP 8+, MySQL, PDO, HTML, Tailwind CSS y Apache.

## Probarlo en local

El archivo `demo.sql` crea una base pequeña para probar el flujo. Sus datos son ficticios y no representan asignaciones reales.

1. Importa `demo.sql` en MySQL.
2. Crea un usuario local con permiso de lectura:
   ```sql
   CREATE USER 'db_app_local'@'localhost' IDENTIFIED BY 'CAMBIA_ESTA_PASSWORD';
   GRANT SELECT ON db_callcenter_demo.* TO 'db_app_local'@'localhost';
   ```
3. Copia `config.example.php` como `config.php` y configura la conexión local.
4. Abre el proyecto con Apache y asegúrate de que `.htaccess` esté habilitado.
5. Entra a `login.php` con el usuario `demo` y la contraseña `DemoBuscador-Local!`. Prueba con `0000000000`.

La cuenta y la contraseña de ejemplo son públicas porque vienen documentadas aquí. Úsalas solo con la base ficticia en tu computadora; no las habilites en un servidor público.

## Demo en línea

[Probar la demo](https://samgarciab.github.io/Buscador-de-numero-celular-casa-nacional./)

La demo estática de GitHub Pages solo reconoce el rango ficticio `0000000000–0000000999`. No usa PHP, no se conecta a MySQL y no envía ni guarda las búsquedas. No introduzcas números reales.

## Usar una base de datos real

El repositorio no incluye una base nacional. Para consultar registros reales, hay que importar una fuente pública y autorizada, revisar sus condiciones de uso y mantenerla actualizada. La versión completa espera estas tablas:

- `pnn_datos`: `numeracion_inicial`, `numeracion_final`, `empresa`, `poblacion`, `modalidad` y, si la fuente los tiene, `estado` o `entidad_federativa`.
- `operadores`: `razon_social` y `nombre_comercial`.
- `usuarios`: `usuario`, `password_hash` y `rol`.

Los resultados describen la asignación de un rango. Por la portabilidad, no necesariamente indican qué operador presta actualmente el servicio a una línea individual.

## Seguridad antes de publicarlo

El proyecto incluye consultas preparadas, validación en servidor, contraseñas con hash, protección CSRF y opciones de seguridad para las sesiones. Aun así, está preparado como prototipo local: antes de ponerlo en Internet habría que configurar HTTPS, limitar intentos de acceso, añadir encabezados de seguridad, retirar la cuenta de demostración y revisar los permisos de la fuente de datos.

No subas `config.php`, bases de datos ni información de suscriptores al repositorio.

## Archivos principales

- `index.php`: búsqueda autenticada.
- `login.php`: inicio de sesión.
- `config.example.php`: plantilla de configuración local.
- `demo.sql`: base de prueba con datos ficticios.
- `.htaccess` y `.gitignore`: reglas para Apache y archivos que no deben subirse.

