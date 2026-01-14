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
# 5. Permisos para uploads (Evita errores al subir archivos)
RUN mkdir -p /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/uploads

# 6. Arreglar MPM + Puerto de Railway al arrancar
# Se elimina cualquier MPM extra y se configura el puerto dinámico
CMD rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.* 2>/dev/null; \
    a2enmod mpm_prefork 2>/dev/null || true; \
    sed -i "s/80/$PORT/g" /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf; \
    docker-php-entrypoint apache2-foreground
