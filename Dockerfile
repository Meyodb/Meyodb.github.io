FROM php:8.0-fpm-alpine

# Installation des dépendances
RUN apk add --no-cache nginx && \
    docker-php-ext-install pdo_mysql && \
    docker-php-ext-install xml && \
    docker-php-ext-install simplexml && \
    docker-php-ext-install curl && \
    docker-php-ext-install mbstring

# Création des répertoires
RUN mkdir -p /run/nginx && \
    mkdir -p /var/log/php-fpm

# Copie des fichiers du projet
COPY . /var/www/html
COPY nginx.conf /etc/nginx/http.d/default.conf

# Permissions
RUN chown -R www-data:www-data /var/www/html

# Port
EXPOSE 80

# Démarrage des services
CMD php-fpm -D && nginx -g 'daemon off;' 