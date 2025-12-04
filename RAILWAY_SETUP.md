# Guía de Despliegue en Railway

## 1. Configuración de Variables de Entorno
En tu proyecto de Railway, ve a la pestaña **Variables** y agrega las siguientes (Railway suele agregar las de MySQL automáticamente si añades el plugin de MySQL, pero verifica que los nombres coincidan o usa los nombres que Railway provee):

Si usas el servicio de MySQL de Railway, las variables se inyectan automáticamente como:
- `MYSQLHOST`
- `MYSQLDATABASE`
- `MYSQLUSER`
- `MYSQLPASSWORD`
- `MYSQLPORT`

El archivo `config.php` ha sido actualizado para buscar estas variables primero.

## 2. Base de Datos (Automatizado)
El proyecto incluye un script de migración automática (`migrate.php`) que crea las tablas necesarias (`_control_clientes`, `documents`, `codes`) si no existen.

### Opción 1: Ejecución Manual (Recomendado para primera vez)
Después de desplegar en Railway, ejecuta una sola vez desde la consola de Railway:
```bash
php migrate.php
```

### Opción 2: Ejecución Automática en cada despliegue
Configura el **Start Command** en Railway (Settings > Deploy > Start Command):
```bash
php migrate.php && apache2-foreground
```
(Nota: Si Railway usa Nginx u otro servidor, ajusta el comando final)

El archivo `database/init.sql` contiene la definición completa de las tablas.

## 3. Despliegue
1. Sube este código a tu repositorio de GitHub.
2. En Railway, crea un "New Project" -> "Deploy from GitHub repo".
3. Selecciona tu repositorio.
4. Railway detectará automáticamente que es un proyecto PHP y lo construirá.
