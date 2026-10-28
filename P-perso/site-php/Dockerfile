FROM php:8.3-apache

# Extension MySQL pour PDO
RUN docker-php-ext-install pdo_mysql

# Le site est servi depuis /public (le code dans src/ n'est pas accessible en HTTP)
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Ne pas divulguer la version de PHP
RUN echo "expose_php = Off" > /usr/local/etc/php/conf.d/security.ini
