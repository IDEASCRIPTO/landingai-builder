FROM php:8.2-apache

# curl necesario para proxy.php
RUN docker-php-ext-install curl && a2enmod rewrite

# Copiar archivos públicos
COPY index.html   /var/www/html/index.html
COPY proxy.php    /var/www/html/proxy.php
COPY proxy-copy.php /var/www/html/proxy-copy.php

# Variables de entorno leídas por proxy.php (se configuran en EasyPanel)
# SUPABASE_URL=https://xxxx.supabase.co
# SUPABASE_ANON_KEY=eyJhbGci...

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
CMD ["apache2-foreground"]