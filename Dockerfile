FROM php:8.2-apache
COPY index.html /var/www/html/index.html
COPY proxy.php /var/www/html/proxy.php
EXPOSE 80
CMD ["apache2-foreground"]