FROM php:8.3-apache
RUN docker-php-ext-install pdo_mysql \
    && sed -i 's/Listen 80/Listen 8080/' /etc/apache2/ports.conf \
    && sed -i 's/:80>/:8080>/' /etc/apache2/sites-available/000-default.conf
COPY --chown=www-data:www-data src/ /var/www/html/
USER www-data
EXPOSE 8080
