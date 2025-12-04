<?php
// helpers/tenant.php

function sanitize_code(?string $code): string
{
    $code = strtolower($code ?? '');
    return preg_replace('/[^a-z0-9_]/', '', $code);
}

function ensure_active_client(PDO $db, string $code): bool
{
    $stmt = $db->prepare('SELECT 1 FROM _control_clientes WHERE codigo = ? AND activo = 1');
    $stmt->execute([$code]);
    return (bool) $stmt->fetchColumn();
}

function table_docs(string $code): string
{
    // CORRECCIÓN: Si es kino, usar la tabla raíz. Si no, usar prefijo.
    if ($code === 'kino') {
        return 'documents';
    }
    return "{$code}_documents";
}

function table_codes(string $code): string
{
    // CORRECCIÓN: Si es kino, usar la tabla raíz.
    if ($code === 'kino') {
        return 'codes';
    }
    return "{$code}_codes";
}

// Función robusta para copiar archivos sin copiar subcarpetas (otros clientes)
function copy_dir_files_only(string $src, string $dst): bool
{
    if (!is_dir($src))
        return false;
    if (!is_dir($dst) && !mkdir($dst, 0777, true))
        return false;

    $dir = opendir($src);
    while (false !== ($file = readdir($dir))) {
        if ($file === '.' || $file === '..')
            continue;

        // CORRECCIÓN: Solo copiamos archivos (PDFs/Imágenes), NO carpetas
        // Esto evita que copies la carpeta de 'cliente1' dentro de 'cliente2'
        if (is_file($src . '/' . $file)) {
            copy($src . '/' . $file, $dst . '/' . $file);
        }
    }
    closedir($dir);
    return true;
}
?>