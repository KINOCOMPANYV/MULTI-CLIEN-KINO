# 🚨 Solución al Error de Conexión en Railway

## Problema Actual
Tu aplicación está intentando conectarse a `sq1209.infinityfree.com` (servidor externo) en lugar del servicio MySQL de Railway porque las variables de entorno no están configuradas correctamente.

## ✅ Solución Paso a Paso

### 1. Verificar el Plugin MySQL en Railway

1. Ve a tu proyecto **protective-fascination** en Railway
2. Asegúrate de que el **plugin MySQL** esté añadido
3. Haz clic en el servicio **MySQL**
4. Ve a la pestaña **Variables** y anota estos valores:
   - `MYSQLHOST` (ejemplo: `mysql.railway.internal`)
   - `MYSQLDATABASE`
   - `MYSQLUSER`
   - `MYSQLPASSWORD`
   - `MYSQLPORT` (generalmente `3306`)

### 2. Agregar Referencias de Variables (CRÍTICO)

**El problema:** Las variables del servicio MySQL existen, pero NO están inyectadas en tu servicio MULTI-CLIEN-KINO.

**Solución paso a paso:**

1. Ve al servicio **MULTI-CLIEN-KINO** en Railway
2. Haz clic en la pestaña **Variables**
3. Haz clic en **+ New Variable**
4. Selecciona **Add Reference** (Agregar Referencia)
5. En el selector, elige el servicio **MySQL**
6. Railway inyectará automáticamente TODAS las variables:
   - `MYSQLHOST` = `mysql.railway.internal`
   - `MYSQLDATABASE` = `railway`
   - `MYSQLUSER` = `root`
   - `MYSQLPASSWORD` = (la contraseña generada)
   - `MYSQLPORT` = `3306`

**Verificación:** Después de añadir la referencia, deberías ver estas variables listadas en la pestaña Variables de MULTI-CLIEN-KINO.

> **IMPORTANTE:** Si ves variables `DB_HOST`, `DB_NAME`, etc. con valores del servidor externo (`sq1209.infinityfree.com`), **ELIMÍNALAS**. Solo deben existir las variables `MYSQL*`.

### 3. Configurar el Start Command (Opcional)

El Dockerfile ya incluye el comando correcto, pero si necesitas cambiarlo manualmente:

1. En el servicio **MULTI-CLIEN-KINO**, ve a **Settings** > **Deploy**
2. En **Start Command**, puedes dejar vacío (usará el CMD del Dockerfile) o configurar:
   ```bash
   php migrate.php && php -S 0.0.0.0:3000
   ```
   
   Esto ejecutará la migración y luego iniciará el servidor PHP en el puerto 3000 (que Railway espera).

> **Nota:** Railway espera que la aplicación escuche en el puerto 3000. El Dockerfile ya está configurado para esto.

### 4. Redesplegar

1. Después de verificar/corregir las variables, haz clic en **Deploy** > **Redeploy**
2. Railway reconstruirá el contenedor con las variables correctas

## 🔍 Verificación

Una vez desplegado, verifica los logs:
- **Build Logs:** Debe completar sin errores
- **Deploy Logs:** Deberías ver:
  - `🚀 Iniciando migración de base de datos...`
  - `✅ Migración completada exitosamente.`
  - Apache iniciando correctamente

## ⚠️ Problemas Comunes

### Error: "could not find driver"
- **Causa:** Falta el Dockerfile
- **Solución:** Ya está resuelto, el Dockerfile está en el repositorio

### Error: "apache2-foreground: command not found"
- **Causa:** Start Command incorrecto
- **Solución:** El Dockerfile ahora usa el servidor PHP integrado

### Error: "502 Bad Gateway"
- **Causa:** La aplicación no escucha en el puerto 3000 que Railway espera
- **Solución:** El Dockerfile actualizado usa `php -S 0.0.0.0:3000` que escucha en el puerto correcto

### Error: "No address associated with hostname"
- **Causa:** Variables de entorno apuntando al servidor externo
- **Solución:** Sigue los Pasos 1 y 2 arriba

## 📋 Checklist Final

- [ ] Plugin MySQL añadido en Railway
- [ ] Variables `MYSQL*` visibles en el servicio MULTI-CLIEN-KINO
- [ ] NO hay variables `DB_*` con valores del servidor externo
- [ ] Start Command configurado (opcional)
- [ ] Redespliegue realizado
- [ ] Logs muestran migración exitosa
