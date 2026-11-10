FROM php:8.2-apache

# Installation des extensions nécessaires pour MySQL (PDO MySQL)
RUN docker-php-ext-install pdo pdo_mysql \
    && a2enmod rewrite

# Copie des fichiers du projet dans le dossier web d'Apache
COPY . /var/www/html/

# Droits d'accès appropriés
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 80

