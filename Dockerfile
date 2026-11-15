# Varianta pro Mac Mini (nebo jakýkoliv server s Dockerem)
FROM php:8.3-apache
RUN apt-get update && apt-get install -y --no-install-recommends libjpeg-dev libpng-dev libwebp-dev libzip-dev \
 && docker-php-ext-configure gd --with-jpeg --with-webp \
 && docker-php-ext-install gd exif zip \
 && a2enmod rewrite headers expires deflate && rm -rf /var/lib/apt/lists/*
ENV APACHE_DOCUMENT_ROOT=/app/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && sed -ri 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf \
 && printf "upload_max_filesize=256M\npost_max_size=300M\nmemory_limit=256M\nmax_execution_time=300\n" > /usr/local/etc/php/conf.d/p50.ini \
 && sed -ri 's!<Directory /var/www/>!<Directory /app/>!' /etc/apache2/apache2.conf
WORKDIR /app
COPY . /app
RUN chown -R www-data:www-data /app/data
