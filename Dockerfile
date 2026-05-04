FROM php:8.2-apache
COPY index.html /var/www/html/index.html
COPY proxy.php /var/www/html/proxy.php
COPY proxy-copy.php /var/www/html/proxy-copy.php
EXPOSE 80
CMD ["apache2-foreground"]