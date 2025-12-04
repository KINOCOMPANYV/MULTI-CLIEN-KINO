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
CMD php migrate.php && php -S 0.0.0.0:3000
