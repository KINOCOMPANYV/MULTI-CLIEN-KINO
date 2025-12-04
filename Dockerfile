FROM php:8.2-fpm

# Instalar extensiones PDO MySQL
RUN docker-php-ext-install pdo pdo_mysql

# Copiar archivos de la aplicación
COPY . /var/www/html/

# Establecer permisos
RUN chown -R www-data:www-data /var/www/html

# Exponer puerto 9000 (PHP-FPM)
EXPOSE 9000

# Comando de inicio
CMD ["php-fpm"]
