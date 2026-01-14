# 🚨 SOLUCIÓN URGENTE - Error "php-fpm could not be found"

## El Problema

Railway tiene un **Start Command personalizado** configurado que dice `php-fpm`, pero la nueva imagen Docker usa `php:8.2-cli` que NO incluye `php-fpm`.

## ✅ Solución Inmediata

### Opción 1: Eliminar el Start Command (RECOMENDADO)

1. Ve a tu servicio **MULTI-CLIEN-KINO** en Railway
2. Haz clic en **Settings**
3. Busca la sección **Deploy**
4. Encuentra **Start Command** o **Custom Start Command**
5. **ELIMINA** el contenido (déjalo vacío)
6. Guarda los cambios

**Resultado:** Railway usará el comando del Dockerfile: `php migrate.php && php -S 0.0.0.0:3000`

### Opción 2: Actualizar el Start Command

Si prefieres mantener un Start Command personalizado:

1. Ve a **Settings** > **Deploy** > **Start Command**
2. Cámbialo a:
   ```bash
   php migrate.php && php -d display_errors=1 -S 0.0.0.0:$PORT
   ```
3. Guarda los cambios

> **Nota:** `$PORT` es una variable de entorno que Railway proporciona automáticamente.

## 🔄 Después de Hacer el Cambio

Railway redesplegar automáticamente y debería funcionar correctamente.

## ✅ Lo Que Deberías Ver en los Logs

```
🔍 [CONFIG] Intentando conectar a: mysql.railway.internal:3306 / DB: railway
🚀 Iniciando migración de base de datos...
✅ Migración completada exitosamente.
[Wed Dec  4 12:05:00 2025] PHP 8.2.x Development Server (http://0.0.0.0:3000) started
```

---

**IMPORTANTE:** El Dockerfile ya está correcto. Solo necesitas eliminar o actualizar el Start Command en Railway.
