FROM php:8.2-apache

# curl necesario para proxy.php
RUN apt-get update && apt-get install -y libcurl4-openssl-dev && \
    docker-php-ext-install curl && \
    a2enmod rewrite && \
    rm -rf /var/lib/apt/lists/*

# Copiar todos los archivos del proyecto al servidor web
COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
CMD ["apache2-foreground"]
