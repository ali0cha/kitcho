FROM php:8.3-apache

RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite headers \
    && echo 'ServerName localhost' > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername

# Le .htaccess du projet doit être pris en compte (protège src/, views/, sql/…).
COPY docker/apache.conf /etc/apache2/conf-available/menu.conf
RUN a2enconf menu

COPY . /var/www/html/
# L'installeur web est inutile : la configuration vient des variables d'environnement.
RUN rm -f /var/www/html/install.php /var/www/html/config.php \
    && chown -R www-data:www-data /var/www/html

EXPOSE 80
