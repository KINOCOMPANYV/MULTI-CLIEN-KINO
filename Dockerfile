FROM php:8.2-cli

# Instalar extensiones PDO MySQL
RUN docker-php-ext-install pdo pdo_mysql

# Copiar archivos de la aplicación
COPY . /var/www/html/

# Establecer directorio de trabajo
WORKDIR /var/www/html

# Exponer puerto 3000 (Railway espera este puerto)
EXPOSE 3000

# Comando de inicio: ejecutar migración y luego servidor PHP
# Usa la variable $PORT de Railway (por defecto 3000 si no está definida)
CMD php migrate.php && echo "🚀 Iniciando servidor PHP en puerto ${PORT:-3000}..." && php -d display_errors=1 -S 0.0.0.0:${PORT:-3000}
