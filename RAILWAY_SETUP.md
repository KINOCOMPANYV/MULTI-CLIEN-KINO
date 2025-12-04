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

## 2. Base de Datos
Abre la herramienta de consulta de base de datos en Railway (o usa un cliente externo como DBeaver conectado a tu DB de Railway) y ejecuta el siguiente script SQL para crear la tabla necesaria:

```sql
CREATE TABLE IF NOT EXISTS _control_clientes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(50) UNIQUE,
  nombre VARCHAR(100) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  email VARCHAR(120) DEFAULT NULL,
  activo BOOLEAN DEFAULT 1,
  fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
  ultimo_acceso DATETIME NULL
);

CREATE INDEX IF NOT EXISTS idx_control_clientes_codigo ON _control_clientes (codigo);
```

## 3. Despliegue
1. Sube este código a tu repositorio de GitHub.
2. En Railway, crea un "New Project" -> "Deploy from GitHub repo".
3. Selecciona tu repositorio.
4. Railway detectará automáticamente que es un proyecto PHP y lo construirá.
