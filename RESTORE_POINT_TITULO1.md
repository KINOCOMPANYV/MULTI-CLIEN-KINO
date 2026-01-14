# 🔖 Punto de Restauración: Titulo1

**Fecha de creación:** 2025-12-05  
**Estado:** Funcional (Títulos Dinámicos + Correcciones Generador)  
**Tag Git:** `Titulo1`

---

## 📋 Características en este Punto

### 1. Títulos Dinámicos por Cliente
- **Base de Datos:** Nueva columna `titulo_app` en tabla `_control_clientes`.
- **Backend:** `api.php` devuelve el título personalizado.
- **Frontend:** `index.html` muestra el título en el encabezado y pestaña.
- **Gestión:** `client-generator.php` permite crear/editar clientes definiendo su título.
- **Migración:** Script `add_title_column.php` disponible para actualizar la BD.

### 2. Mejoras en Generador de Clientes
- **Creación Vacía:** Opción "Crear Cliente Vacío" (`create_empty`) que clona estructura pero no datos.
- **Corrección de Clonado:** Solucionado error de llaves foráneas (`FOREIGN_KEY_CHECKS`) al clonar/truncar tablas.
- **Feedback de Errores:** Mejor manejo de excepciones y mensajes de error en `client-generator.php`.

### 3. Visor Inteligente (Previa Integración)
- `visor.html` en raíz con validación inteligente de códigos (ignora sufijos) y resaltado verde.
- `index.html` apunta al visor en raíz.

---

## 🔄 Cómo Restaurar a este Punto

### Opción 1: Git
```bash
git checkout Titulo1
```

### Opción 2: Revertir Cambios
Si necesitas volver a este estado exacto, este tag marca el momento donde la funcionalidad de títulos dinámicos quedó completamente implementada y probada.

---

## 📂 Archivos Clave Modificados
- `client-generator.php`
- `api.php`
- `index.html`
- `add_title_column.php`
