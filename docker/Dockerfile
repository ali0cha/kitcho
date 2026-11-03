FROM php:8.3-apache

LABEL org.opencontainers.image.title="Kitcho" \
      org.opencontainers.image.description="Planning de repas, liste de courses et foyers partagés" \
      org.opencontainers.image.source="https://github.com/ali0cha/kitcho"

RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite headers \
    && echo 'ServerName localhost' > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername

# Le .htaccess du projet doit être pris en compte (protège src/, views/, sql/…).
COPY docker/apache.conf /etc/apache2/conf-available/menu.conf
RUN a2enconf menu

COPY . /var/www/html/
COPY docker/entrypoint.sh /usr/local/bin/kitcho-entrypoint
# Version construite : commit Git et date, lus par l'appli (/version, page Profil) et par bin/check_deploy.sh.
ARG GIT_SHA=dev
ARG BUILD_DATE=
RUN printf '%s\n%s\n' "$GIT_SHA" "$BUILD_DATE" > /var/www/html/VERSION
# L'installeur web est inutile : la configuration vient des variables d'environnement.
RUN rm -f /var/www/html/install.php /var/www/html/config.php \
    && chmod +x /usr/local/bin/kitcho-entrypoint \
    && chown -R www-data:www-data /var/www/html

EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
    CMD php -r 'exit(@file_get_contents("http://127.0.0.1/login") === false ? 1 : 0);'

ENTRYPOINT ["kitcho-entrypoint"]
CMD ["apache2-foreground"]
