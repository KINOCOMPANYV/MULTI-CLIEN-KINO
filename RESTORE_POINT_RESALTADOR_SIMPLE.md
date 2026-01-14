# 🔖 Punto de Restauración: App con Resaltador Simple

**Fecha de creación:** 2025-12-05  
**Estado:** Funcional y desplegado en producción  
**Tag Git:** `app-con-resaltador-simple`

---

## 📋 Características en este Punto

### 1. Sistema de Resaltado Frontend (Simple)
- **Tecnología:** PDF.js (Client-side)
- **Archivos Clave:**
  - `bc/visor.html`: Visor dedicado que recibe `file` y `code` por URL.
  - `bc/index.html`: Interfaz pública actualizada con botón "Resaltar Código Hallado".
  - `index.html`: Panel de administración con botón "🖍️ Resaltar" en búsqueda.
- **Funcionamiento:** 
  - No usa Python ni librerías pesadas en el servidor.
  - Descarga el PDF en el navegador del cliente.
  - Busca texto capa por capa y le aplica un estilo CSS `.highlight`.
  - Color de resaltado: Verde fosforescente (`#4ade80`).

### 2. Estabilidad del Núcleo
- **Backend:** PHP 8.2 en Docker (Apache).
- **Base de Datos:** MySQL (soporta multi-cliente).
- **Importación:** Scripts de importación (`quick_import.php`, etc.) siguen disponibles.

---

## 🔄 Cómo Restaurar a este Punto

Si realizas cambios futuros que rompen la aplicación, usa estos comandos para volver aquí:

### Opción 1: Git (Recomendado)
```bash
# Volver al estado exacto de este tag
git checkout app-con-resaltador-simple

# Si quieres forzar la rama main a este punto (CUIDADO: borra cambios posteriores)
git reset --hard app-con-resaltador-simple
git push origin main --force
```

### Opción 2: Railway
1. Ve a "Deployments" en Railway.
2. Busca el commit asociado a este tag (aprox. `125b43e`).
3. Dale a "Redeploy".

---

## 📂 Archivos Modificados Recientemente
- `bc/visor.html` (Nuevo visor verde)
- `bc/index.html` (Botones grandes en cliente)
- `index.html` (Botón de resaltado en admin)
- `bc/highlight.html` (Versión alternativa, actualmente en desuso o secundaria)
