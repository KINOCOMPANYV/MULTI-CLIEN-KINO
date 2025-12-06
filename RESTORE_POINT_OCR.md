# 🔖 Punto de Restauración: Resaltador con OCR y Ajustes Avanzados

**Fecha de creación:** 2025-12-06
**Estado:** Funcional y probado (Frontend OCR implementado)
**Tag Git:** `resaltador-con-ocr`

---

## 📋 Características en este Punto

### 1. Sistema de Extracción Mejorado (OCR)
- **Tecnología:** Tesseract.js v5 (CDN) + PDF.js.
- **Archivos Clave:** `index.html`.
- **Nuevas Funcionalidades:**
  - **Soporte de Imágenes:** Ahora permite subir archivos de imagen (JPG, PNG) y extrae texto automáticamente usando OCR en el navegador.
  - **Modal de Configuración:** Nuevo modal interactivo que permite al usuario:
    - Definir prefijos de búsqueda personalizados (ej: "Ref:", "Factura:").
    - Seleccionar un carácter de terminación para cortar el código (Espacio, /, -, ., Personalizado).
    - Activar/Desactivar unión de códigos con guiones partidos por salto de línea.
  - **Feedback Visual:** Indicadores de carga ("Analizando píxeles...", spinners) y advertencias de verificación claras.

### 2. Base del Resaltador (Heredado)
- Mantiene la funcionalidad de resaltado visual implementada en puntos anteriores (`bc/visor.html`).

### 3. Frontend
- **Framework:** Vanilla JS + TailwindCSS.
- **Lógica:** Refactorización masiva de `handleFileSelect`, `confirmExtraction`, y `runAdvancedExtraction` en `index.html`.

---

## 🔄 Cómo Restaurar a este Punto

Si realizas cambios futuros que rompen la aplicación, usa estos comandos para volver aquí:

### Opción 1: Git (Recomendado)
```bash
# Volver al estado exacto de este tag
git checkout resaltador-con-ocr

# Si quieres forzar la rama main a este punto (CUIDADO: borra cambios posteriores)
git reset --hard resaltador-con-ocr
git push origin main --force
```

### Opción 2: Railway
1. Ve a "Deployments" en Railway.
2. Busca el commit asociado a este tag.
3. Dale a "Redeploy".
