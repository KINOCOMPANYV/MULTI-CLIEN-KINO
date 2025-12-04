FROM php:8.2-apache

# 1. Instalar dependencias del sistema y extensiones PHP necesarias (incluyendo Python si lo usas)
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

# 2. Habilitar mod_rewrite de Apache (crítico para tus rutas)
RUN a2enmod rewrite

# 3. Copiar los archivos de tu proyecto al contenedor
COPY . /var/www/html/

# 4. Ajustar permisos (Railway a veces tiene problemas si root es dueño estricto)
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/uploads

# 5. Instalar dependencias de Python (si usas pdf_search.py)
# COPY requirements.txt .
# RUN pip3 install -r requirements.txt --break-system-packages

# --- SOLUCIÓN DEL ERROR 502 ---
# 6. Cambiar el puerto de Apache para usar la variable $PORT de Railway
#    Esto reemplaza "80" por el valor de $PORT en los archivos de configuración al iniciar.
CMD sed -i "s/80/$PORT/g" /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf && docker-php-entrypoint apache2-foreground
