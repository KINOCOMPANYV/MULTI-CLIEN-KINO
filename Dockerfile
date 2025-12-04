FROM php:8.2-apache

# 1. Instalar dependencias del sistema y Python (Para tu buscador de PDFs)
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    python3 \
    python3-pip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pdo pdo_mysql mysqli

# 2. Instalar librería PyPDF2 para el script de búsqueda
RUN pip3 install PyPDF2 --break-system-packages

# 3. Habilitar mod_rewrite de Apache
RUN a2enmod rewrite

# 4. Copiar código
COPY . /var/www/html/

# 5. Permisos para uploads (Evita errores al subir archivos)
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/uploads

# 6. EL ARREGLO DEL ERROR 502 (Importante)
# Cambia el puerto 80 por el puerto de Railway ($PORT) justo al arrancar
CMD sed -i "s/80/$PORT/g" /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf && docker-php-entrypoint apache2-foreground
